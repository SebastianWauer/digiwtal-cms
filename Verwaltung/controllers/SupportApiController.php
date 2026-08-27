<?php
declare(strict_types=1);

/**
 * Schnittstelle fuer die Hilfe-Funktion in den Kunden-CMS.
 *
 * Der Kunde drueckt in seinem CMS auf "Hilfe", schreibt auf, was nicht geht -
 * und es landet hier. Ein Posteingang fuer alle Instanzen statt einer E-Mail,
 * die zwischen anderen untergeht.
 *
 * Ausgewiesen wird sich mit dem Support-Token der Instanz (Header
 * X-Support-Token). Das Token entscheidet zugleich, welcher Kunde meldet - es
 * gibt keinen Weg, im Namen eines anderen zu schreiben oder dessen Meldungen
 * zu lesen.
 */
class SupportApiController
{
    private const MAX_PRO_STUNDE = 20;

    public function __construct(
        private SupportTicketRepository $tickets,
        private SupportTokenRepository $tokens,
        private AuditLogger $audit
    ) {}

    /** POST /api/support/tickets */
    public function store(): void
    {
        $customerId = $this->authenticate();

        $daten = $this->jsonBody();

        $subject = trim((string)($daten['subject'] ?? ''));
        $body    = trim((string)($daten['body'] ?? ''));
        if ($subject === '' || $body === '') {
            $this->json(['ok' => false, 'error' => 'subject_and_body_required'], 422);
        }

        if ($this->tickets->countRecent($customerId) >= self::MAX_PRO_STUNDE) {
            $this->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $kontext = is_array($daten['context'] ?? null) ? $daten['context'] : [];
        // Nur Skalares uebernehmen und kuerzen: Was hier ankommt, kommt von
        // einer fremden Installation.
        $sauber = [];
        foreach ($kontext as $schluessel => $wert) {
            if (!is_string($schluessel) || is_array($wert) || is_object($wert)) {
                continue;
            }
            $sauber[substr($schluessel, 0, 40)] = substr((string)$wert, 0, 300);
            if (count($sauber) >= 20) {
                break;
            }
        }

        $id = $this->tickets->create(
            $customerId,
            (string)($daten['kind'] ?? 'problem'),
            $this->kuerzen($subject, 190),
            $this->kuerzen($body, 20000),
            $this->kuerzen(trim((string)($daten['reporter_name'] ?? '')), 190),
            $this->kuerzen(trim((string)($daten['reporter_email'] ?? '')), 190),
            $sauber
        );

        $this->audit->log('support.ticket_received', 'customer', $customerId, 'Meldung #' . $id);

        $this->json(['ok' => true, 'id' => $id], 201);
    }

    /** GET /api/support/tickets - eigene Meldungen samt Status und Antwort. */
    public function index(): void
    {
        $customerId = $this->authenticate();

        $this->json([
            'ok'      => true,
            'tickets' => $this->tickets->forCustomer($customerId),
        ], 200);
    }

    /** Liefert die Kunden-ID oder beendet die Anfrage. */
    private function authenticate(): int
    {
        if (!$this->isSecure()) {
            $this->json(['ok' => false, 'error' => 'https_required'], 400);
        }

        $customerId = $this->tokens->findCustomerIdByToken($this->headerToken());
        if ($customerId === null) {
            $this->json(['ok' => false, 'error' => 'invalid_token'], 403);
        }

        return $customerId;
    }

    private function headerToken(): string
    {
        $token = trim((string)($_SERVER['HTTP_X_SUPPORT_TOKEN'] ?? ''));
        if ($token !== '' || !function_exists('getallheaders')) {
            return $token;
        }

        $headers = getallheaders();
        if (!is_array($headers)) {
            return '';
        }
        foreach ($headers as $name => $wert) {
            if (is_string($name) && strcasecmp($name, 'X-Support-Token') === 0) {
                return trim((string)$wert);
            }
        }

        return '';
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $roh = (string)file_get_contents('php://input');
        if ($roh === '') {
            return [];
        }
        // Sicherheitsnetz gegen versehentlich riesige Anfragen.
        if (strlen($roh) > 200000) {
            $this->json(['ok' => false, 'error' => 'payload_too_large'], 413);
        }

        $daten = json_decode($roh, true);

        return is_array($daten) ? $daten : [];
    }

    private function kuerzen(string $wert, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($wert, 0, $max);
        }

        return substr($wert, 0, $max);
    }

    private function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }

        return in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
