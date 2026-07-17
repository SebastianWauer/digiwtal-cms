<?php
namespace App\Repositories;

use PDO;

final class SiteSettingsRepositoryDb implements SiteSettingsRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getAll(): array
    {
        $rows = $this->pdo->query("SELECT `key`,`value` FROM site_settings")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    public function set(string $key, ?string $value): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO site_settings (`key`,`value`)
             VALUES (:k,:v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $st->execute([':k'=>$key, ':v'=>$value]);
    }
}
