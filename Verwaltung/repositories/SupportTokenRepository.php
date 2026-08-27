<?php
declare(strict_types=1);

/**
 * Das Token, mit dem sich eine Instanz beim Melden ausweist.
 *
 * Es haengt am Serverzugang des Kunden, nicht an einer eigenen Tabelle: Eine
 * Instanz gibt es nur dort, wo ein Serverzugang hinterlegt ist, und beides soll
 * gemeinsam entstehen und gemeinsam verschwinden.
 *
 * Erzeugt wird es automatisch beim ersten Bedarf. Die vier Anlaeufe, die der
 * erste Rollout gekostet hat, gingen alle auf leer gelassene Felder zurueck -
 * ein Token, das der Mensch eintragen muss, waere der fuenfte gewesen.
 */
class SupportTokenRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Liefert das Token des Kunden im Klartext und legt es an, falls es fehlt.
     * Gibt null zurueck, wenn der Kunde keinen Serverzugang hat.
     */
    public function ensureFor(int $customerId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT support_token_enc, support_token_nonce, support_token_tag
             FROM server_access WHERE customer_id = :cid LIMIT 1'
        );
        $stmt->execute([':cid' => $customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $vorhanden = $this->decrypt($row, $customerId);
        if ($vorhanden !== null) {
            return $vorhanden;
        }

        return $this->rotate($customerId);
    }

    /** Erzeugt ein neues Token, verwirft das alte und liefert das neue im Klartext. */
    public function rotate(int $customerId): ?string
    {
        $token = bin2hex(random_bytes(32));

        try {
            $enc = VaultCrypto::encrypt($token, 'cust:' . $customerId);
        } catch (Throwable) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE server_access
             SET support_token_hash  = :hash,
                 support_token_enc   = :enc,
                 support_token_nonce = :nonce,
                 support_token_tag   = :tag
             WHERE customer_id = :cid LIMIT 1'
        );
        $stmt->execute([
            ':hash'  => hash('sha256', $token),
            ':enc'   => $enc['ciphertext_b64'],
            ':nonce' => $enc['nonce_b64'],
            ':tag'   => $enc['tag_b64'],
            ':cid'   => $customerId,
        ]);

        return $stmt->rowCount() > 0 ? $token : null;
    }

    /** Welcher Kunde gehoert zu diesem Token? Nachschlagen ueber den Hash, nicht ueber Entschluesseln. */
    public function findCustomerIdByToken(string $plainToken): ?int
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT sa.customer_id
             FROM server_access sa
             JOIN customers c ON c.id = sa.customer_id
             WHERE sa.support_token_hash = :hash AND c.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':hash' => hash('sha256', $plainToken)]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    /** @param array<string,mixed> $row */
    private function decrypt(array $row, int $customerId): ?string
    {
        $enc   = (string)($row['support_token_enc'] ?? '');
        $nonce = (string)($row['support_token_nonce'] ?? '');
        $tag   = (string)($row['support_token_tag'] ?? '');
        if ($enc === '' || $nonce === '' || $tag === '') {
            return null;
        }

        try {
            $klartext = VaultCrypto::decrypt($enc, $nonce, $tag, 'cust:' . $customerId);
        } catch (Throwable) {
            return null;
        }

        return $klartext === '' ? null : $klartext;
    }
}
