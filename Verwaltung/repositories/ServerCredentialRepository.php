<?php
declare(strict_types=1);

class ServerCredentialRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listByCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, label, host, username, created_at, updated_at FROM server_credentials WHERE customer_id = ? ORDER BY label ASC, id ASC'
        );
        $stmt->execute([$customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function findMeta(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, label, host, username FROM server_credentials WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findEncrypted(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, secret_ciphertext, secret_nonce, secret_tag FROM server_credentials WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(int $customerId, string $label, string $host, string $username, array $enc): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO server_credentials (customer_id, label, host, username, secret_ciphertext, secret_nonce, secret_tag) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $customerId,
            $label,
            $host,
            $username,
            $enc['ciphertext_b64'],
            $enc['nonce_b64'],
            $enc['tag_b64'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateMeta(int $id, string $label, string $host, string $username): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE server_credentials SET label = ?, host = ?, username = ? WHERE id = ?'
        );
        $stmt->execute([$label, $host, $username, $id]);
    }

    public function updateSecret(int $id, array $enc): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE server_credentials SET secret_ciphertext = ?, secret_nonce = ?, secret_tag = ? WHERE id = ?'
        );
        $stmt->execute([
            $enc['ciphertext_b64'],
            $enc['nonce_b64'],
            $enc['tag_b64'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM server_credentials WHERE id = ?');
        $stmt->execute([$id]);
    }
}
