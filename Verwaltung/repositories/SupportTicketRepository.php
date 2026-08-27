<?php
declare(strict_types=1);

/**
 * Meldungen aus den Kunden-CMS.
 *
 * Ein Kunde sieht nur seine eigenen, die Verwaltung sieht alle - das ist der
 * ganze Zweck: ein Posteingang statt drei Postfaecher.
 */
class SupportTicketRepository
{
    public const STATUS = ['neu', 'in_arbeit', 'beantwortet', 'erledigt'];
    public const KINDS  = ['problem', 'vorschlag', 'frage'];

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $context */
    public function create(
        int $customerId,
        string $kind,
        string $subject,
        string $body,
        string $reporterName,
        string $reporterEmail,
        array $context
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO support_tickets
                (customer_id, kind, subject, body, reporter_name, reporter_email, context_json, created_at, updated_at)
             VALUES (:cid, :kind, :subject, :body, :rname, :remail, :ctx, NOW(), NOW())'
        );
        $stmt->execute([
            ':cid'     => $customerId,
            ':kind'    => in_array($kind, self::KINDS, true) ? $kind : 'problem',
            ':subject' => $subject,
            ':body'    => $body,
            ':rname'   => $reporterName,
            ':remail'  => $reporterEmail,
            ':ctx'     => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Alle Meldungen, optional gefiltert. Fuer die Verwaltung.
     *
     * @return list<array<string,mixed>>
     */
    public function all(string $status = '', int $customerId = 0, int $limit = 300): array
    {
        $where  = [];
        $params = [];

        if ($status !== '' && in_array($status, self::STATUS, true)) {
            $where[]           = 't.status = :status';
            $params[':status'] = $status;
        }
        if ($customerId > 0) {
            $where[]        = 't.customer_id = :cid';
            $params[':cid'] = $customerId;
        }

        $sql = 'SELECT t.*, c.name AS customer_name
                FROM support_tickets t
                LEFT JOIN customers c ON c.id = t.customer_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        // Offene zuerst, dann nach Alter - was neu ist, soll oben stehen.
        $sql .= " ORDER BY FIELD(t.status, 'neu', 'in_arbeit', 'beantwortet', 'erledigt'), t.created_at DESC"
              . ' LIMIT ' . max(1, min(1000, $limit));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, c.name AS customer_name
             FROM support_tickets t
             LEFT JOIN customers c ON c.id = t.customer_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Meldungen eines Kunden - das, was seine Instanz zurueckliest, um Status
     * und Antwort anzuzeigen.
     *
     * @return list<array<string,mixed>>
     */
    public function forCustomer(int $customerId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, kind, subject, body, status, answer, answered_at, created_at
             FROM support_tickets
             WHERE customer_id = :cid
             ORDER BY created_at DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute([':cid' => $customerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,int> Status => Anzahl */
    public function countsByStatus(): array
    {
        $out = array_fill_keys(self::STATUS, 0);
        $rows = $this->pdo->query('SELECT status, COUNT(*) AS c FROM support_tickets GROUP BY status')
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (isset($out[$status])) {
                $out[$status] = (int)($row['c'] ?? 0);
            }
        }

        return $out;
    }

    public function countOpen(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('neu', 'in_arbeit')")
            ->fetchColumn();
    }

    /** Meldungen eines Kunden in der letzten Stunde - Bremse gegen Dauerfeuer. */
    public function countRecent(int $customerId, int $minutes = 60): int
    {
        // Minuten fest in die Abfrage: Ein Platzhalter in INTERVAL wird nicht
        // von jedem Treiber akzeptiert. Der Wert stammt aus dem Code, nicht
        // von aussen.
        $minutes = max(1, min(1440, $minutes));
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM support_tickets
             WHERE customer_id = :cid AND created_at > (NOW() - INTERVAL ' . $minutes . ' MINUTE)'
        );
        $stmt->execute([':cid' => $customerId]);

        return (int)$stmt->fetchColumn();
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUS, true)) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE support_tickets SET status = :s, updated_at = NOW() WHERE id = :id LIMIT 1');
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /** Antwort speichern. Der Status wandert dabei automatisch auf "beantwortet". */
    public function answer(int $id, string $answer): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE support_tickets
             SET answer = :a, answered_at = NOW(), status = 'beantwortet', updated_at = NOW()
             WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':a' => $answer, ':id' => $id]);
    }
}
