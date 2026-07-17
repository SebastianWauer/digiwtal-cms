<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepositoryDb implements UserRepositoryInterface
{
    private const SYSTEM_ROLE_KEY = 'admin';
    private const SYSTEM_USERNAME = 'Admin';

    public function __construct(private PDO $pdo) {}

    /**
     * SQL-Fragment: System-User (roles.key='admin') konsequent aus normalen Listen/Statistiken ausschließen.
     *
     * Definition:
     * - Ein User ist System-User, wenn er mindestens eine Rolle mit roles.key = 'admin' besitzt.
     * - Rollen-Soft-Delete wird respektiert (roles.is_deleted).
     */
    private function sqlExcludeSystemUser(string $userAlias = 'u'): string
    {
        $a = preg_replace('/[^a-z0-9_]/i', '', $userAlias);
        if ($a === '') $a = 'u';

        return "\n              AND NOT EXISTS (\n                    SELECT 1\n                    FROM user_roles ur\n                    JOIN roles r ON r.id = ur.role_id\n                    WHERE ur.user_id = {$a}.id\n                      AND (r.is_deleted = 0 OR r.is_deleted IS NULL)\n                      AND r.`key` = '" . self::SYSTEM_ROLE_KEY . "'\n                )\n        ";
    }

    /**
     * Zählung für verwaltbare, aktive CMS-User:
     * - nicht gelöscht
     * - nicht gesperrt (enabled=1)
     * - kein System-User
     */
    public function countActiveEnabledNonSystem(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*)\n" .
            "FROM users u\n" .
            "WHERE u.is_deleted = 0\n" .
            "  AND u.enabled = 1\n" .
            $this->sqlExcludeSystemUser('u')
        );
        return (int)$stmt->fetchColumn();
    }

    /**
     * Backwards-kompatible Zählung: enabled & nicht gelöscht (ohne System-User).
     */
    public function countEnabled(): int
    {
        return $this->countActiveEnabledNonSystem();
    }

    /** @return array<int,array> */
    public function listActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, username, name, email, enabled, created_at, updated_at, is_deleted, deleted_at\n" .
            "FROM users u\n" .
            "WHERE u.is_deleted = 0\n" .
            $this->sqlExcludeSystemUser('u') .
            "ORDER BY u.id DESC"
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,array> */
    public function listDeleted(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, username, name, email, enabled, created_at, updated_at, is_deleted, deleted_at\n" .
            "FROM users u\n" .
            "WHERE u.is_deleted = 1\n" .
            $this->sqlExcludeSystemUser('u') .
            "ORDER BY u.deleted_at DESC, u.id DESC"
        );
        $rows = $stmt ? $stmt->fetchAll() : [];
        return is_array($rows) ? $rows : [];
    }

    public function countDeleted(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*)\n" .
            "FROM users u\n" .
            "WHERE u.is_deleted = 1\n" .
            $this->sqlExcludeSystemUser('u')
        );
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, name, email, enabled, created_at, updated_at, is_deleted, deleted_at\n" .
            "FROM users\n" .
            "WHERE id = :id\n" .
            "LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, name, email, enabled, created_at, updated_at, is_deleted, deleted_at\n" .
            "FROM users\n" .
            "WHERE username = :u\n" .
            "LIMIT 1"
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByEmail(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') return null;

        $stmt = $this->pdo->prepare(
            "SELECT id, username, name, email, enabled, created_at, updated_at, is_deleted, deleted_at\n" .
            "FROM users\n" .
            "WHERE email = :e\n" .
            "LIMIT 1"
        );
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function isAdminUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)\n" .
            "FROM user_roles ur\n" .
            "JOIN roles r ON r.id = ur.role_id\n" .
            "JOIN users u ON u.id = ur.user_id\n" .
            "WHERE ur.user_id = :uid\n" .
            "  AND u.is_deleted = 0\n" .
            "  AND (r.is_deleted = 0 OR r.is_deleted IS NULL)\n" .
            "  AND r.`key` = :rk"
        );
        $stmt->execute([':uid' => $userId, ':rk' => self::SYSTEM_ROLE_KEY]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function countActiveAdmins(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(DISTINCT u.id)\n" .
            "FROM users u\n" .
            "JOIN user_roles ur ON ur.user_id = u.id\n" .
            "JOIN roles r ON r.id = ur.role_id\n" .
            "WHERE u.is_deleted = 0\n" .
            "  AND (r.is_deleted = 0 OR r.is_deleted IS NULL)\n" .
            "  AND r.`key` = '" . self::SYSTEM_ROLE_KEY . "'"
        );
        return (int)$stmt->fetchColumn();
    }

    public function isSystemUserAnyState(int $userId): bool
    {
        if ($userId <= 0) return false;

        $stmt = $this->pdo->prepare(
            "SELECT 1\n" .
            "FROM user_roles ur\n" .
            "JOIN roles r ON r.id = ur.role_id\n" .
            "WHERE ur.user_id = :uid\n" .
            "  AND (r.is_deleted = 0 OR r.is_deleted IS NULL)\n" .
            "  AND r.`key` = :rk\n" .
            "LIMIT 1"
        );
        $stmt->execute([':uid' => $userId, ':rk' => self::SYSTEM_ROLE_KEY]);
        return (bool)$stmt->fetchColumn();
    }

    /** @return array{ok:bool,id?:int,flash?:array} */
    public function save(?int $id, string $username, string $name, ?string $email, bool $enabled, ?string $newPassword): array
    {
        $username = trim($username);
        $name = trim($name);

        if (mb_strtolower($username) === mb_strtolower(self::SYSTEM_USERNAME)) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Der Benutzername "Admin" ist reserviert und kann nicht verwendet werden.']];
        }

        $email = $email !== null ? trim($email) : null;
        if ($email === '') $email = null;

        if ($username === '' || mb_strlen($username) > 190) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Benutzername ist ungültig.']];
        }
        if (mb_strlen($name) > 190) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Name ist zu lang.']];
        }
        if ($email !== null) {
            if (mb_strlen($email) > 190) {
                return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'E-Mail ist zu lang.']];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'E-Mail ist ungültig.']];
            }
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        $existingId = (int)$stmt->fetchColumn();
        if ($existingId > 0 && $existingId !== (int)($id ?? 0)) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Benutzername ist bereits vergeben.']];
        }

        if ($email !== null) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
            $stmt->execute([':e' => $email]);
            $existingEmailId = (int)$stmt->fetchColumn();
            if ($existingEmailId > 0 && $existingEmailId !== (int)($id ?? 0)) {
                return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'E-Mail ist bereits vergeben.']];
            }
        }

        $pwHash = null;
        if ($newPassword !== null && trim($newPassword) !== '') {
            $newPassword = (string)$newPassword;
            if (mb_strlen($newPassword) < 8) {
                return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Passwort muss mindestens 8 Zeichen lang sein.']];
            }
            $pwHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (!is_string($pwHash) || $pwHash === '') {
                return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Passwort-Hashing fehlgeschlagen.']];
            }
        }

        if ($id && $id > 0) {
            if ($pwHash !== null) {
                $stmt = $this->pdo->prepare(
                    "UPDATE users\n" .
                    "SET username = :u, name = :n, email = :e, enabled = :en, password_hash = :ph, updated_at = NOW()\n" .
                    "WHERE id = :id"
                );
                $stmt->execute([':u' => $username, ':n' => $name, ':e' => $email, ':en' => $enabled ? 1 : 0, ':ph' => $pwHash, ':id' => $id]);
            } else {
                $stmt = $this->pdo->prepare(
                    "UPDATE users\n" .
                    "SET username = :u, name = :n, email = :e, enabled = :en, updated_at = NOW()\n" .
                    "WHERE id = :id"
                );
                $stmt->execute([':u' => $username, ':n' => $name, ':e' => $email, ':en' => $enabled ? 1 : 0, ':id' => $id]);
            }

            return ['ok' => true, 'id' => $id, 'flash' => ['type' => 'ok', 'msg' => 'Benutzer gespeichert.']];
        }

        if ($pwHash === null) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Passwort ist erforderlich.']];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, password_hash, enabled, created_at, updated_at, is_deleted, name, email)\n" .
            "VALUES (:u, :ph, :en, NOW(), NOW(), 0, :n, :e)"
        );
        $stmt->execute([':u' => $username, ':ph' => $pwHash, ':en' => $enabled ? 1 : 0, ':n' => $name, ':e' => $email]);

        $newId = (int)$this->pdo->lastInsertId();
        if ($newId <= 0) {
            return ['ok' => false, 'flash' => ['type' => 'error', 'msg' => 'Benutzer konnte nicht angelegt werden.']];
        }

        return ['ok' => true, 'id' => $newId, 'flash' => ['type' => 'ok', 'msg' => 'Benutzer angelegt.']];
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0");
        $stmt->execute([':id' => $id]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET is_deleted = 0, deleted_at = NULL WHERE id = :id AND is_deleted = 1");
        $stmt->execute([':id' => $id]);
    }
    public function purgeDeleted(): int
    {
        // Defense-in-depth: System-User "admin" nie hard-deleten
        $st = $this->pdo->prepare("DELETE FROM users WHERE is_deleted = 1 AND username <> 'admin'");
        $st->execute();
        return (int)$st->rowCount();
    }
}
