<?php
declare(strict_types=1);


/**
 * Tokens, mit denen eine CI-Pipeline Deploy-Zugangsdaten abholen darf.
 * Gespeichert wird nur der SHA-256-Hash - der Klartext ist nach der Erstellung
 * nicht wieder herstellbar.
 */
class CiTokenRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findByPlainToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, label, last_used_at, revoked_at
             FROM ci_tokens
             WHERE token_hash = ? AND revoked_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** Erzeugt ein neues Token und gibt den Klartext zurueck - einmalig. */
    public function create(string $label): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO ci_tokens (label, token_hash) VALUES (?, ?)'
        );
        $stmt->execute([mb_substr($label, 0, 100), hash('sha256', $token)]);

        return $token;
    }

    public function markUsed(int $id, string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ci_tokens SET last_used_at = NOW(), last_used_ip = ? WHERE id = ?'
        );
        $stmt->execute([substr($ip, 0, 45), $id]);
    }

    public function revoke(int $id): void
    {
        $this->pdo->prepare('UPDATE ci_tokens SET revoked_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, label, last_used_at, last_used_ip, revoked_at, created_at
             FROM ci_tokens ORDER BY created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }
}
