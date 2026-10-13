<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\SiteSettingsRepositoryDb;
use PDO;

final class OnepagerService
{
    private const KEY = 'onepager_pages';
    public function __construct(private PDO $pdo) {}

    public function snapshot(bool $lock = false): array
    {
        $suffix = $lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $rows = $this->pdo->query("SELECT id, slug, title, status, is_home, redirect_type FROM pages WHERE is_deleted = 0 ORDER BY CASE WHEN nav_order > 0 THEN 0 ELSE 1 END, nav_order, title, id" . $suffix)->fetchAll(PDO::FETCH_ASSOC);
        $statement = $this->pdo->prepare('SELECT `value` FROM site_settings WHERE `key` = ?' . $suffix);
        $statement->execute([self::KEY]);
        $raw = $statement->fetchColumn();
        $config = $raw === false ? null : json_decode((string)$raw, true);
        if ($raw !== false && (!is_array($config) || !array_is_list($config))) throw new \RuntimeException('Onepager-Konfiguration ist ungueltig.');
        $byId = [];
        foreach ($rows as $row) {
            $row['id'] = (int)$row['id'];
            $row['is_home'] = !empty($row['is_home']) || $row['slug'] === '/';
            $row['eligible'] = $row['status'] === 'live' && $row['redirect_type'] === 'none';
            $row['visible'] = false;
            $byId[(int)$row['id']] = $row;
        }
        $ordered = [];
        foreach ($config ?? [] as $item) {
            if (!is_array($item) || !is_int($item['id'] ?? null) || !is_bool($item['visible'] ?? null)) throw new \RuntimeException('Onepager-Konfiguration ist ungueltig.');
            $id = $item['id'];
            if (!isset($byId[$id])) continue;
            $row = $byId[$id];
            $row['visible'] = $item['visible'] && $row['eligible'];
            $ordered[] = $row;
            unset($byId[$id]);
        }
        foreach ($byId as $row) {
            if ($config === null) {
                $leaf = strtolower(basename(trim((string)$row['slug'], '/')));
                $title = mb_strtolower(trim((string)$row['title']));
                $row['visible'] = $row['eligible'] && !in_array($leaf, ['impressum','datenschutz','datenschutzerklaerung','imprint','privacy','privacy-policy'], true)
                    && !in_array($title, ['impressum','datenschutz','datenschutzerklärung'], true);
            }
            $ordered[] = $row;
        }
        $rank = static fn(array $row): int => $row['is_home'] ? 0 : ($config === null && (strtolower(basename(trim($row['slug'], '/'))) === 'kontakt' || mb_strtolower(trim($row['title'])) === 'kontakt') ? 2 : 1);
        usort($ordered, static fn(array $a, array $b): int => $rank($a) <=> $rank($b));
        foreach ($ordered as &$row) if ($row['is_home'] && $row['eligible']) $row['visible'] = true;
        unset($row);
        return ['pages' => $ordered, 'revision' => hash('sha256', $raw === false ? 'unset' : (string)$raw)];
    }

    public function publicPageIds(): array
    {
        return array_values(array_map(static fn(array $row): int => (int)$row['id'], array_filter($this->snapshot()['pages'], static fn(array $row): bool => $row['visible'])));
    }

    public function save(array $order, array $selected, string $revision): void
    {
        foreach ([$order, $selected] as $ids) {
            if (!array_is_list($ids) || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) throw new \DomainException('Ungueltige Seitenauswahl.');
            foreach ($ids as $id) if (!is_int($id) || $id <= 0) throw new \DomainException('Ungueltige Seiten-ID.');
        }
        $this->pdo->beginTransaction();
        try {
            $snapshot = $this->snapshot(true);
            $expected = array_column($snapshot['pages'], 'id');
            $submitted = $order;
            sort($expected); sort($submitted);
            if ($expected !== $submitted || !hash_equals($snapshot['revision'], $revision)) throw new \DomainException('Die Seiten oder Einstellungen haben sich geaendert. Bitte neu laden.');
            $eligible = array_column(array_filter($snapshot['pages'], static fn(array $row): bool => $row['eligible']), 'id');
            if (array_diff($selected, $eligible) !== []) throw new \DomainException('Nur veroeffentlichte Inhaltsseiten sind auswaehlbar.');
            $homeIds = array_column(array_filter($snapshot['pages'], static fn(array $row): bool => $row['is_home'] && $row['eligible']), 'id');
            $items = array_map(static fn(int $id): array => ['id' => $id, 'visible' => in_array($id, $selected, true) || in_array($id, $homeIds, true)], $order);
            (new SiteSettingsRepositoryDb($this->pdo))->set(self::KEY, json_encode($items, JSON_THROW_ON_ERROR));
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
