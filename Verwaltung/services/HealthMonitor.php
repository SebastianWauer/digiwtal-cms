<?php
declare(strict_types=1);

/**
 * Prueft Kundeninstanzen und schreibt das Ergebnis in die Verwaltung.
 *
 * Warum ein Service und nicht Code im Cron-Skript: Geprueft wird von zwei
 * Seiten - vom Cron der Verwaltung und von GitHub Actions (geplanter Lauf und
 * Rollout). Wuerde jede Seite ihr eigenes Urteil faellen, liefen Anzeige und
 * Alarmierung auseinander. Hier liegt beides an einer Stelle; scripts/health_check.php
 * ist nur noch ein duenner Aufruf darum herum, genauso wie scripts/migrate.php
 * um den MigrationRunner.
 *
 * Zwei Lehren aus dem August 2026 stecken in dieser Klasse:
 *
 *  1. Die API des CMS haengt hinter api.php. Ein Aufruf von /api/health landet
 *     beim Admin-Front-Controller und antwortet 404 - die Instanz galt dann als
 *     "down", obwohl sie lief.
 *  1a. Gemessen wird mit denselben statischen Methoden, egal wer misst -
 *      der Cron hier oder .github/scripts/health_sweep.php im Runner.
 *  2. Ein Monitoring, das still stirbt, ist schlimmer als keines: Es zeigt
 *     wochenlang einen eingefrorenen Wert. Deshalb protokolliert jeder Lauf
 *     seinen Zeitpunkt in monitor_runs, und das Dashboard sagt es, wenn dort
 *     nichts Neues mehr ankommt.
 */
final class HealthMonitor
{
    /** Ab wann ein Pruflauf als ausgeblieben gilt (Sekunden). */
    public const RUN_STALE_AFTER = 1200;

    /** @var array<int,string> */
    public const SOURCES = ['cron', 'ci', 'rollout', 'manual'];

    private const TIMEOUT = 10;

    public function __construct(
        private PDO $pdo,
        private string $alertEmail = '',
        private ?PushService $push = null,
        private ?PushSubscriptionRepository $pushRepo = null
    ) {
    }

    // ---------------------------------------------------------------
    // Ziele
    // ---------------------------------------------------------------

    /**
     * Aktive Kunden, die ueberwacht werden koennen.
     *
     * Frueher fiel die Abfrage auf sa.host zurueck, wenn keine Health-URL
     * gesetzt war. Das ist der SFTP-Host, keine Website - der Check ging
     * zwangslaeufig ins Leere. Wer keine Health-CMS-URL hinterlegt hat, wird
     * jetzt ehrlich als nicht ueberwachbar gefuehrt.
     *
     * @return array<int, array{id:int,name:string,cms_url:string,frontend_url:string,token:string}>
     */
    public function targets(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.id, c.name, sa.health_cms_url, sa.health_frontend_url,
                   sa.health_token_enc, sa.health_token_nonce, sa.health_token_tag
            FROM customers c
            INNER JOIN server_access sa ON c.id = sa.customer_id
            WHERE c.abo_status = 'active'
              AND c.is_active = 1
              AND sa.health_cms_url != ''
              AND sa.health_token_enc != ''
            ORDER BY c.id ASC
        ");

        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (!is_array($rows)) {
            return [];
        }

        $targets = [];
        foreach ($rows as $row) {
            $customerId = (int)$row['id'];
            $token = $this->decryptToken($row, $customerId);
            if ($token === null || $token === '') {
                // Ein leeres Token wandert sonst als leerer Wert in die .env der
                // Instanz - und das CMS behandelt eine leere Erwartung als
                // "verboten". Der Check bekaeme dauerhaft 403.
                FileLogger::channel('verwaltung')->error("[HC] token_missing customer_id={$customerId}");
                continue;
            }

            $targets[] = [
                'id'           => $customerId,
                'name'         => (string)$row['name'],
                'cms_url'      => (string)$row['health_cms_url'],
                'frontend_url' => (string)($row['health_frontend_url'] ?? ''),
                'token'        => $token,
            ];
        }

        return $targets;
    }

    /** @param array<string,mixed> $row */
    private function decryptToken(array $row, int $customerId): ?string
    {
        $enc   = (string)($row['health_token_enc'] ?? '');
        $nonce = (string)($row['health_token_nonce'] ?? '');
        $tag   = (string)($row['health_token_tag'] ?? '');
        if ($enc === '' || $nonce === '' || $tag === '') {
            return null;
        }

        try {
            return VaultCrypto::decrypt($enc, $nonce, $tag, 'cust:' . $customerId);
        } catch (Throwable) {
            FileLogger::channel('verwaltung')->error("[HC] decrypt_failed customer_id={$customerId}");
            return null;
        }
    }

    // ---------------------------------------------------------------
    // Adressen
    // ---------------------------------------------------------------

    public static function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        return rtrim($value, '/');
    }

    /**
     * Adresse des Health-Endpunkts einer Instanz.
     *
     * Die .htaccess des CMS leitet alles, was keine echte Datei ist, an
     * index.php - den Admin-Router. Die API haengt deshalb hinter api.php,
     * genau wie CMS_API_URL im Frontend (".../api.php/api/v1").
     */
    public static function cmsHealthUrl(string $base): string
    {
        $base = self::normalizeBaseUrl($base);
        if ($base === '') {
            return '';
        }
        // Fertige Adresse unveraendert durchreichen: /api/ci/instances liefert
        // sie bereits vollstaendig, damit die Pipeline nichts zusammenbauen muss.
        if (str_ends_with($base, '/api/health')) {
            return $base;
        }
        if (!str_ends_with($base, '/api.php')) {
            $base .= '/api.php';
        }

        return $base . '/api/health';
    }

    // ---------------------------------------------------------------
    // Messen
    // ---------------------------------------------------------------

    /**
     * Ruft den Health-Endpunkt einer Instanz auf.
     *
     * @return array{url:string,http_code:int,response_ms:int,error:?string,body:?array<string,mixed>}
     */
    public static function probeCms(string $cmsBaseUrl, string $token): array
    {
        $url = self::cmsHealthUrl($cmsBaseUrl);
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            // Token im Header: der Query-Parameter ist im CMS als deprecated
            // markiert und stuende im Access-Log jedes Aufrufs.
            CURLOPT_HTTPHEADER     => ['X-Health-Token: ' . $token, 'Accept: application/json'],
        ]);

        $body      = curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        $responseMs = (int)((microtime(true) - $start) * 1000);

        $decoded = null;
        if (is_string($body) && $body !== '') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        return [
            'url'         => $url,
            'http_code'   => $httpCode,
            'response_ms' => $responseMs,
            'error'       => $curlErrno === 28 ? 'timeout' : ($curlErrno !== 0 ? 'curl_' . $curlErrno : null),
            'body'        => $decoded,
        ];
    }

    /**
     * Ruft die oeffentliche Website auf.
     *
     * @return array{checked:bool,url:string,http_code:int,response_ms:int,error:?string}
     */
    public static function probeFrontend(string $frontendUrl): array
    {
        $url = self::normalizeBaseUrl($frontendUrl);
        if ($url === '') {
            return ['checked' => false, 'url' => '', 'http_code' => 0, 'response_ms' => 0, 'error' => null];
        }

        $start = microtime(true);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Eine Weiterleitung auf www oder https ist beim Frontend normal.
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_NOBODY         => true,
        ]);

        curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        return [
            'checked'     => true,
            'url'         => $url,
            'http_code'   => $httpCode,
            'response_ms' => (int)((microtime(true) - $start) * 1000),
            'error'       => $curlErrno === 28 ? 'timeout' : ($curlErrno !== 0 ? 'curl_' . $curlErrno : null),
        ];
    }

    // ---------------------------------------------------------------
    // Bewerten
    // ---------------------------------------------------------------

    /**
     * Aus zwei Messungen wird ein Urteil. Die einzige Stelle, an der das
     * passiert - der Cron misst selbst, die Pipeline schickt ihre Messung
     * hierher, bewertet wird identisch.
     *
     * @param array<string,mixed> $cms
     * @param array<string,mixed> $frontend
     * @return array{status:string,response_ms:int,cms_version:?string,frontend_version:?string,php_version:?string,raw_response:array<string,mixed>}
     */
    public function evaluate(array $cms, array $frontend, string $source): array
    {
        $source = in_array($source, self::SOURCES, true) ? $source : 'manual';

        $httpCode   = (int)($cms['http_code'] ?? 0);
        $responseMs = (int)($cms['response_ms'] ?? 0);
        $error      = $cms['error'] ?? null;
        $body       = is_array($cms['body'] ?? null) ? $cms['body'] : null;

        $frontendChecked = (bool)($frontend['checked'] ?? false);
        $frontendCode    = (int)($frontend['http_code'] ?? 0);
        $frontendOk      = $frontendChecked
            && ($frontend['error'] ?? null) === null
            && $frontendCode >= 200 && $frontendCode < 400;

        $frontendHealth = [
            'checked'     => $frontendChecked,
            'ok'          => $frontendChecked ? $frontendOk : true,
            'status'      => $frontendChecked ? ($frontendOk ? 'healthy' : 'down') : 'skipped',
            'http_code'   => $frontendCode,
            'response_ms' => (int)($frontend['response_ms'] ?? 0),
            'url'         => (string)($frontend['url'] ?? ''),
            'error'       => $frontend['error'] ?? null,
        ];

        $raw = [
            'source'          => $source,
            'url'             => (string)($cms['url'] ?? ''),
            'http_code'       => $httpCode,
            'frontend_health' => $frontendHealth,
        ];

        if ($error === 'timeout') {
            return $this->result('timeout', $responseMs, null, $raw + ['error' => 'timeout'], $frontendHealth);
        }

        if ($httpCode !== 200 || $body === null) {
            $raw['error'] = $body === null && $httpCode === 200 ? 'invalid_json' : 'http_error';
            if ($httpCode === 403) {
                // Haeufigste Ursache: HEALTH_TOKEN fehlt in der .env der Instanz
                // oder weicht vom hinterlegten ab. Das CMS behandelt eine leere
                // Erwartung ebenfalls als "verboten".
                $raw['hint'] = 'health_token_mismatch_or_missing';
            }
            if ($httpCode === 404) {
                $raw['hint'] = 'endpoint_not_found_check_api_php_path';
            }
            if ($error !== null) {
                $raw['error'] = $error;
            }

            return $this->result('down', $responseMs, $body, $raw, $frontendHealth);
        }

        $status = (string)($body['status'] ?? '');
        if (!in_array($status, ['healthy', 'degraded'], true)) {
            $raw['error'] = 'unknown_status';

            return $this->result('down', $responseMs, $body, $raw, $frontendHealth);
        }

        // Die Website ist Teil des Versprechens: Ein gesundes CMS hinter einer
        // toten Startseite ist fuer den Kunden kein gesunder Zustand.
        if ($status === 'healthy' && $frontendChecked && !$frontendOk) {
            $status = 'degraded';
        }

        return $this->result($status, $responseMs, $body, $raw, $frontendHealth);
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $frontendHealth
     * @return array{status:string,response_ms:int,cms_version:?string,frontend_version:?string,php_version:?string,raw_response:array<string,mixed>}
     */
    private function result(string $status, int $responseMs, ?array $body, array $raw, array $frontendHealth): array
    {
        if (is_array($body)) {
            $raw = array_merge($body, $raw);
        }

        return [
            'status'           => $status,
            'response_ms'      => max($responseMs, (int)($frontendHealth['response_ms'] ?? 0)),
            'cms_version'      => isset($body['cms_version']) ? (string)$body['cms_version'] : null,
            'frontend_version' => isset($body['frontend_version']) && $body['frontend_version'] !== null
                ? (string)$body['frontend_version']
                : null,
            'php_version'      => isset($body['php_version']) ? (string)$body['php_version'] : null,
            'raw_response'     => $raw,
        ];
    }

    // ---------------------------------------------------------------
    // Speichern und alarmieren
    // ---------------------------------------------------------------

    /**
     * Ergebnis festhalten, Dashboard-Status spiegeln, bei Statuswechsel melden.
     *
     * @param array<string,mixed> $result Rueckgabe von evaluate()
     */
    public function record(int $customerId, string $customerName, array $result, string $source): void
    {
        $status = (string)($result['status'] ?? 'down');

        // Vor dem Schreiben lesen: der Wechsel entscheidet ueber die Meldung.
        $lastStatus = $this->lastStatus($customerId);

        $stmt = $this->pdo->prepare("
            INSERT INTO health_checks (customer_id, checked_at, status, cms_version, frontend_version, php_version, response_ms, raw_response)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $customerId,
            $status,
            $result['cms_version'] ?? null,
            $result['frontend_version'] ?? null,
            $result['php_version'] ?? null,
            (int)($result['response_ms'] ?? 0),
            json_encode($result['raw_response'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        $mapped = match ($status) {
            'healthy'  => 'online',
            'degraded' => 'degraded',
            default    => 'offline',
        };

        $sync = $this->pdo->prepare("
            INSERT INTO customer_health (customer_id, status, last_check_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = VALUES(status), last_check_at = VALUES(last_check_at)
        ");
        $sync->execute([$customerId, $mapped]);

        $this->alert($customerId, $customerName, $status, $lastStatus, $result, $source);
    }

    private function lastStatus(int $customerId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT status FROM health_checks
            WHERE customer_id = ?
            ORDER BY checked_at DESC
            LIMIT 1
        ");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (string)$row['status'] : null;
    }

    /** @param array<string,mixed> $result */
    private function alert(int $customerId, string $customerName, string $status, ?string $lastStatus, array $result, string $source): void
    {
        $problem  = $status !== 'healthy' && ($lastStatus === null || $lastStatus === 'healthy');
        $recovery = $status === 'healthy' && $lastStatus !== null && $lastStatus !== 'healthy';

        if (!$problem && !$recovery) {
            return;
        }

        $raw = is_array($result['raw_response'] ?? null) ? $result['raw_response'] : [];
        $url = (string)($raw['url'] ?? '');

        if ($problem) {
            $subject = "DIGIWTAL Health Alert: {$customerName} ist {$status}";
            $body  = "Kunde: {$customerName}\n";
            $body .= "Geprueft: " . $url . "\n";
            $body .= "Quelle: {$source}\n";
            $body .= "Status: {$status}\n";
            $body .= "Zeitpunkt: " . date('Y-m-d H:i:s') . "\n";
            $body .= "Antwortzeit: " . (int)($result['response_ms'] ?? 0) . "ms\n";
            $body .= "CMS: " . (string)($result['cms_version'] ?? '-') . "\n";
            $body .= "PHP: " . (string)($result['php_version'] ?? '-') . "\n";
            $body .= "\nDetails:\n" . json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

            $this->sendMail($subject, $body);
            $this->sendPush(
                "⚠️ {$customerName} ist {$status}",
                $url !== '' ? $url : 'Instanz nicht erreichbar',
                'health-alert-' . $customerId
            );

            return;
        }

        $duration = $this->outageDuration($customerId);
        $subject  = "DIGIWTAL Recovery: {$customerName} wieder healthy - Ausfall {$duration}";
        $body  = "Kunde: {$customerName}\n";
        $body .= "Geprueft: " . $url . "\n";
        $body .= "Quelle: {$source}\n";
        $body .= "Wiederhergestellt: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Ausfallzeit: {$duration}\n";
        $body .= "CMS: " . (string)($result['cms_version'] ?? '-') . "\n";

        $this->sendMail($subject, $body);
        $this->sendPush("✅ {$customerName} wieder online", "Ausfall: {$duration}", 'health-recovery-' . $customerId);
    }

    private function outageDuration(int $customerId): string
    {
        $stmt = $this->pdo->prepare("
            SELECT MIN(checked_at) AS outage_start
            FROM health_checks
            WHERE customer_id = ?
              AND status != 'healthy'
              AND checked_at > COALESCE(
                  (SELECT MAX(checked_at) FROM health_checks WHERE customer_id = ? AND status = 'healthy'),
                  '1970-01-01'
              )
        ");
        $stmt->execute([$customerId, $customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $start = ($row && $row['outage_start']) ? (string)$row['outage_start'] : '';
        if ($start === '') {
            return 'unbekannt';
        }

        try {
            $diff = (new DateTime($start))->diff(new DateTime());
        } catch (Throwable) {
            return 'unbekannt';
        }

        // Frueher stand hier $diff->h * 66 - eine Stunde Ausfall wurde als
        // 66 Minuten gemeldet.
        $minutes = ($diff->days * 1440) + ($diff->h * 60) + $diff->i;

        return $minutes . ' Minuten';
    }

    private function sendMail(string $subject, string $body): void
    {
        if ($this->alertEmail === '') {
            return;
        }
        $headers = "From: noreply@digiwtal.de\r\nContent-Type: text/plain; charset=utf-8\r\n";
        @mail($this->alertEmail, $subject, $body, $headers);
    }

    private function sendPush(string $title, string $body, string $tag): void
    {
        if ($this->push === null || $this->pushRepo === null || !$this->push->isConfigured()) {
            return;
        }
        $this->push->sendToAll($this->pushRepo->listAll(), $title, $body, '/admin/dashboard', $tag);
    }

    // ---------------------------------------------------------------
    // Der Waechter wird bewacht
    // ---------------------------------------------------------------

    public function noteRun(string $source, int $customersChecked, string $note = ''): void
    {
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'manual';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO monitor_runs (source, last_run_at, customers_checked, note)
            VALUES (?, NOW(), ?, ?)
            ON DUPLICATE KEY UPDATE
                last_run_at = VALUES(last_run_at),
                customers_checked = VALUES(customers_checked),
                note = VALUES(note)
        ");
        $stmt->execute([$source, $customersChecked, mb_substr($note, 0, 255)]);
    }

    /**
     * Zustand der Pruflaeufe selbst.
     *
     * @return array{runs:array<int,array<string,mixed>>, last_run_at:?string, age_seconds:?int, stale:bool}
     */
    public function monitorState(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT source, last_run_at, customers_checked, note FROM monitor_runs ORDER BY last_run_at DESC");
            $runs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            // Migration 026 noch nicht eingespielt - dann ist der Zustand
            // schlicht unbekannt, kein Grund die Seite zu stoppen.
            return ['runs' => [], 'last_run_at' => null, 'age_seconds' => null, 'stale' => true];
        }

        $runs = is_array($runs) ? $runs : [];
        $latest = $runs[0]['last_run_at'] ?? null;
        $age = null;
        if (is_string($latest) && $latest !== '') {
            $ts = strtotime($latest);
            $age = $ts === false ? null : max(0, time() - $ts);
        }

        return [
            'runs'        => $runs,
            'last_run_at' => is_string($latest) ? $latest : null,
            'age_seconds' => $age,
            'stale'       => $age === null || $age > self::RUN_STALE_AFTER,
        ];
    }
}
