<?php
declare(strict_types=1);

class AdminUserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, totp_secret, role, is_active
             FROM admin_users
             WHERE email = ? AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, role, password_hash, totp_secret, created_at, last_login_at
             FROM admin_users
             WHERE id = ? AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function listAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, email, role, is_active, last_login_at, created_at
             FROM admin_users
             WHERE deleted_at IS NULL
             ORDER BY role DESC, email ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function create(string $email, string $passwordHash, string $role = 'operator'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (email, password_hash, role) VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $passwordHash, $role]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET deleted_at = NOW(), is_active = 0 WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function updateTotpSecret(int $id, ?string $secret): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET totp_secret = ? WHERE id = ?'
        );
        $stmt->execute([$secret, $id]);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users SET password_hash = ? WHERE id = ?'
        );
        $stmt->execute([$passwordHash, $id]);
    }
}
