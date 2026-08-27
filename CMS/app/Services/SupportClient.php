<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use Throwable;

/**
 * Verbindung zur DIGIWTAL-Verwaltung fuer die Hilfe-Funktion.
 *
 * Eine Instanz meldet damit Probleme, Vorschlaege und Fragen an eine Stelle,
 * statt dass sie per E-Mail zwischen anderen Nachrichten untergehen. Die
 * Antwort kommt denselben Weg zurueck und erscheint hier im CMS.
 *
 * Ist die Installation nicht angebunden (kein SUPPORT_URL/SUPPORT_TOKEN in der
 * .env), meldet sich die Klasse als "nicht verbunden" - das ist kein Fehler,
 * sondern der Normalzustand einer Installation ohne Wartungsvertrag.
 */
final class SupportClient
{
    private string $baseUrl;
    private string $token;

    public function __construct(?string $baseUrl = null, ?string $token = null)
    {
        $this->baseUrl = rtrim(trim($baseUrl ?? (string)Env::get('SUPPORT_URL', '')), '/');
        $this->token   = trim($token ?? (string)Env::get('SUPPORT_TOKEN', ''));
    }

    public function istVerbunden(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '' && function_exists('curl_init');
    }

    /**
     * Schickt eine Meldung an die Verwaltung.
     *
     * @param  array<string,scalar> $kontext
     * @return array{ok: bool, fehler: string}
     */
    public function melden(
        string $art,
        string $betreff,
        string $text,
        string $name,
        string $email,
        array $kontext = []
    ): array {
        if (!$this->istVerbunden()) {
            return ['ok' => false, 'fehler' => 'Diese Installation ist nicht mit der Verwaltung verbunden.'];
        }

        $antwort = $this->request('POST', '/api/support/tickets', [
            'kind'           => $art,
            'subject'        => $betreff,
            'body'           => $text,
            'reporter_name'  => $name,
            'reporter_email' => $email,
            'context'        => $kontext,
        ]);

        if ($antwort['status'] === 201) {
            return ['ok' => true, 'fehler' => ''];
        }

        return ['ok' => false, 'fehler' => self::fehlertext($antwort['status'], $antwort['body'])];
    }

    /**
     * Eigene Meldungen samt Status und Antwort.
     *
     * @return list<array<string,mixed>>
     */
    public function meldungen(): array
    {
        if (!$this->istVerbunden()) {
            return [];
        }

        $antwort = $this->request('GET', '/api/support/tickets', null);
        if ($antwort['status'] !== 200) {
            return [];
        }

        $daten = json_decode($antwort['body'], true);
        if (!is_array($daten) || !is_array($daten['tickets'] ?? null)) {
            return [];
        }

        return array_values(array_filter($daten['tickets'], 'is_array'));
    }

    /**
     * @param  array<string,mixed>|null $daten
     * @return array{status: int, body: string}
     */
    private function request(string $methode, string $pfad, ?array $daten): array
    {
        $ch = curl_init($this->baseUrl . $pfad);
        if ($ch === false) {
            return ['status' => 0, 'body' => ''];
        }

        $header = [
            'X-Support-Token: ' . $this->token,
            'Accept: application/json',
            'User-Agent: DIGIWTAL-CMS/' . self::cmsVersion(),
        ];

        $optionen = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($methode === 'POST') {
            $optionen[CURLOPT_POST]       = true;
            $optionen[CURLOPT_POSTFIELDS] = json_encode($daten ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $header[]                     = 'Content-Type: application/json';
        }

        $optionen[CURLOPT_HTTPHEADER] = $header;
        curl_setopt_array($ch, $optionen);

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }

    private static function fehlertext(int $status, string $body): string
    {
        $daten  = json_decode($body, true);
        $schluessel = is_array($daten) ? (string)($daten['error'] ?? '') : '';

        return match (true) {
            $status === 0             => 'Die Verwaltung war nicht erreichbar. Bitte später erneut versuchen.',
            $schluessel === 'invalid_token'
                                      => 'Diese Installation ist bei der Verwaltung nicht angemeldet. Bitte den Betreuer informieren.',
            $schluessel === 'rate_limited'
                                      => 'Zu viele Meldungen in kurzer Zeit. Bitte in einer Stunde erneut versuchen.',
            $schluessel === 'subject_and_body_required'
                                      => 'Betreff und Beschreibung dürfen nicht leer sein.',
            default                   => 'Die Verwaltung hat mit HTTP ' . $status . ' geantwortet.',
        };
    }

    /** Umgebungsangaben, die bei jeder Meldung mitgehen. Erspart Rueckfragen. */
    public static function kontext(string $benutzer = ''): array
    {
        $kontext = [
            'cms_version' => self::cmsVersion(),
            'php'         => PHP_VERSION,
            'host'        => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'benutzer'    => $benutzer,
        ];

        $herkunft = trim((string)($_POST['from_url'] ?? ''));
        if ($herkunft !== '') {
            $kontext['zuletzt_besucht'] = $herkunft;
        }

        return $kontext;
    }

    private static function cmsVersion(): string
    {
        try {
            $datei = dirname(__DIR__, 2) . '/config/version.php';
            if (is_file($datei)) {
                $config = include $datei;
                if (is_array($config)) {
                    return (string)($config['cms_version'] ?? 'unbekannt');
                }
            }
        } catch (Throwable) {
            // Version ist nett zu haben, aber kein Grund, die Meldung zu verhindern.
        }

        return 'unbekannt';
    }
}
