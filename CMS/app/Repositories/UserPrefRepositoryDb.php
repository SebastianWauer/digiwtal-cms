<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserPrefRepositoryDb implements UserPrefRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \db();
    }

    public function get(int $userId, string $key, ?string $default = null): ?string
    {
        $sql = 'SELECT pref_value
                FROM admin_user_prefs
                WHERE user_id = :uid AND pref_key = :k
                LIMIT 1';

        $st = $this->pdo->prepare($sql);
        $st->execute(['uid' => $userId, 'k' => $key]);

        $row = $st->fetch();
        if (!$row) return $default;

        $val = (string)($row['pref_value'] ?? '');
        return ($val === '') ? $default : $val;
    }

    public function set(int $userId, string $key, string $value): void
    {
        // Erwartet UNIQUE(user_id, pref_key)
        $sql = 'INSERT INTO admin_user_prefs (user_id, pref_key, pref_value)
                VALUES (:uid, :k, :v)
                ON DUPLICATE KEY UPDATE
                    pref_value = VALUES(pref_value)';

        $st = $this->pdo->prepare($sql);
        $st->execute(['uid' => $userId, 'k' => $key, 'v' => $value]);
    }

    public function delete(int $userId, string $key): void
    {
        $sql = 'DELETE FROM admin_user_prefs WHERE user_id = :uid AND pref_key = :k';
        $st  = $this->pdo->prepare($sql);
        $st->execute(['uid' => $userId, 'k' => $key]);
    }
}
