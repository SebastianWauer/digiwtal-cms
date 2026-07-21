<?php
declare(strict_types=1);

class ModuleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, key_name, display_name, description, created_at FROM modules ORDER BY display_name ASC'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, key_name, display_name, description
             FROM modules
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if (!isset($row['slug'])) {
            $row['slug'] = (string)($row['key_name'] ?? '');
        }
        return $row;
    }

    public function create(string $key, string $name, string $desc): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO modules (key_name, display_name, description) VALUES (?, ?, ?)'
        );
        $stmt->execute([$key, $name, $desc]);
        return (int)$this->pdo->lastInsertId();
    }
}
