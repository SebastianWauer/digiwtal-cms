<?php
declare(strict_types=1);

class PushSubscriptionRepository
{
    public function __construct(private PDO $pdo) {}

    public function upsert(int $adminUserId, string $endpoint, string $p256dh, string $auth, string $userAgent = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO push_subscriptions (admin_user_id, endpoint, p256dh, auth, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                user_agent = VALUES(user_agent)'
        );
        $stmt->execute([$adminUserId, $endpoint, $p256dh, $auth, $userAgent]);
    }

    public function deleteByEndpoint(string $endpoint): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
        $stmt->execute([$endpoint]);
    }

    public function listAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, admin_user_id, endpoint, p256dh, auth FROM push_subscriptions ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function count(): int
    {
        $row = $this->pdo->query('SELECT COUNT(*) FROM push_subscriptions')->fetch(PDO::FETCH_NUM);
        return (int)($row[0] ?? 0);
    }
}
