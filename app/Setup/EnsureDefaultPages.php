<?php
declare(strict_types=1);

namespace App\Setup;

use PDO;
use Throwable;

final class EnsureDefaultPages
{
    public static function run(PDO $pdo): void
    {
        // Nur wenn Tabelle existiert
        if (!function_exists('db_table_exists') || !db_table_exists('pages')) {
            return;
        }

        // Wenn bereits Seiten vorhanden (auch gelöschte zählen), nix machen
        $count = (int)$pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
        if ($count > 0) return;

        $seedFile = __DIR__ . '/../Seeds/default_pages.php';
        $seed = is_file($seedFile) ? require $seedFile : [];
        if (!is_array($seed) || count($seed) === 0) return;

        $stmt = $pdo->prepare("
            INSERT INTO pages (slug, title, content_json, is_deleted, deleted_at)
            VALUES (:slug, :title, CAST(:content AS JSON), 0, NULL)
        ");

        $pdo->beginTransaction();
        try {
            foreach ($seed as $p) {
                if (!is_array($p)) continue;
                $slug = self::normalizeSlug((string)($p['slug'] ?? ''));
                if ($slug === '') continue;

                $title = trim((string)($p['title'] ?? ''));
                $content = $p['content'] ?? ['blocks' => []];
                if (!is_array($content)) $content = ['blocks' => []];

                $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($json) || $json === '') $json = '{}';

                $stmt->execute([
                    ':slug' => $slug,
                    ':title' => $title,
                    ':content' => $json,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') return '';

        $slug = parse_url($slug, PHP_URL_PATH) ?: $slug;
        if ($slug === '') return '';

        if ($slug[0] !== '/') $slug = '/' . $slug;
        if ($slug !== '/') $slug = rtrim($slug, '/');

        return $slug === '' ? '/' : $slug;
    }
}
