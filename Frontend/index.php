<?php
declare(strict_types=1);

// -------------------------------------------------------
// Minimal .env loader (no Composer required)
// -------------------------------------------------------
(static function (): void {
    $file = __DIR__ . '/.env';
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));
        // Strip surrounding quotes + Maskierungen aufloesen (die Verwaltung
        // schreibt diese Datei mit escapten " und \ - ohne diesen Schritt kaeme
        // z.B. ein API-Token mit Sonderzeichen verfaelscht an).
        if (strlen($v) >= 2 && $v[0] === $v[-1] && ($v[0] === '"' || $v[0] === "'")) {
            $v = substr($v, 1, -1);
            $v = preg_replace('/\\\\(["\\\\])/', '$1', $v) ?? $v;
        }
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
})();

require_once __DIR__ . '/app/CmsApiClient.php';
require_once __DIR__ . '/app/view.php';
$sharedLogger = dirname(__DIR__) . '/shared/FileLogger.php';
$localLogger  = __DIR__ . '/app/FileLogger.php';
if (is_file($sharedLogger)) {
    require_once $sharedLogger;
} elseif (is_file($localLogger)) {
    require_once $localLogger;
} else {
    // Minimal no-op fallback so the site doesn't crash without a logger
    if (!class_exists('FileLogger')) {
        class FileLogger {
            public static function channel(string $n): static { return new static(); }
            public function error(string $m, array $c = []): void {}
        }
    }
}

// -------------------------------------------------------
// Config from .env
// -------------------------------------------------------
$baseUrl  = (string)(getenv('CMS_API_URL')   ?: '');
$token    = (string)(getenv('CMS_API_TOKEN') ?: '');
$timeout  = (int)(getenv('CMS_TIMEOUT')      ?: 5);
$cacheTtl = 0; // Live-Frontend: Seiteninhalte immer direkt aus dem CMS holen.
$frontendBaseUrl = (string)(getenv('FRONTEND_BASE_URL') ?: '');
$cmsSitemapUrl   = (string)(getenv('CMS_SITEMAP_URL') ?: '');

if ($baseUrl === '') {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "ERROR: CMS_API_URL not set in .env\n";
    exit(1);
}

$client = new CmsApiClient(
    baseUrl:  $baseUrl,
    token:    $token !== '' ? $token : null,
    timeout:  $timeout,
    cacheTtl: $cacheTtl,
    cacheDir: __DIR__ . '/storage/cache'
);

// -------------------------------------------------------
// Helper functions
// -------------------------------------------------------
function frontendLogPath(): string
{
    return __DIR__ . '/storage/frontend_error.log';
}

function frontendDebugLog(string $message): void
{
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = '[' . gmdate('c') . '] ' . $message . "\n";
    @file_put_contents(frontendLogPath(), $line, FILE_APPEND);
    FileLogger::channel('frontend')->error($message);
}

function renderErrorPage(int $statusCode, string $siteName, string $title, string $message, ?string $hint = null): never
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $pageTitle = $title;
    $fullTitle = $title . ' – ' . $siteName;
    $slug = 'error';
    $navItems = [];
    $blocks = [
        [
            'type' => 'hero',
            'headline' => $title,
            'subtitle' => $message,
        ],
    ];
    if ($hint !== null && trim($hint) !== '') {
        $blocks[] = [
            'type' => 'text',
            'text' => $hint,
        ];
    }

    render('templates/layout.php', [
        'siteName' => $siteName,
        'title' => $fullTitle,
        'pageTitle' => $pageTitle,
        'blocks' => $blocks,
        'navItems' => $navItems,
        'slug' => $slug,
    ]);
    exit;
}

function render404(string $siteName = 'Website'): never {
    renderErrorPage(
        404,
        $siteName,
        '404 – Seite nicht gefunden',
        'Die angeforderte Seite existiert nicht oder wurde verschoben.'
    );
}

function render500(string $siteName = 'Website'): never {
    renderErrorPage(
        500,
        $siteName,
        '500 – Interner Serverfehler',
        'Der Server ist momentan nicht erreichbar. Bitte versuche es später erneut.'
    );
}

function resolveHomeSlug(CmsApiClient $client, string $fallback = 'home'): string
{
    try {
        $pages = $client->getPages(1);
    } catch (CmsApiException) {
        return $fallback;
    }

    $items = [];
    if (is_array($pages)) {
        if (array_is_list($pages)) {
            $items = $pages;
        } elseif (is_array($pages['items'] ?? null)) {
            $items = $pages['items'];
        }
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $isHomeByUrl = (string)($item['url'] ?? '') === '/';
        $isHomeFlag  = !empty($item['is_home']);
        if (!$isHomeByUrl && !$isHomeFlag) {
            continue;
        }

        $candidate = strtolower(trim((string)($item['slug'] ?? '')));
        if ($candidate !== '' && preg_match('/^[a-z0-9\/-]+$/', $candidate)) {
            return trim($candidate, '/');
        }
    }

    return $fallback;
}

function extractMediaIdFromUrl(string $url): ?int
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $path = (string)($parts['path'] ?? '');
    if (!str_ends_with($path, '/media/file') && !str_ends_with($path, '/media/thumb')) {
        return null;
    }

    $query = (string)($parts['query'] ?? '');
    if ($query === '') {
        return null;
    }

    parse_str($query, $q);
    $id = (int)($q['id'] ?? 0);
    return $id > 0 ? $id : null;
}

function deriveCmsBaseUrlFromApiBase(string $apiBaseUrl): string
{
    $base = rtrim(trim($apiBaseUrl), '/');
    if ($base === '') {
        return '';
    }

    $patterns = [
        '#/api\.php/api/v1$#i',
        '#/api/v1$#i',
        '#/api\.php$#i',
        '#/api$#i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $base) === 1) {
            $base = preg_replace($p, '', $base) ?? $base;
            break;
        }
    }

    return rtrim($base, '/');
}

function absolutizeCmsMediaUrl(string $url, string $cmsBaseUrl): string
{
    $url = trim($url);
    if ($url === '' || $cmsBaseUrl === '') {
        return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    if (!empty($parts['scheme']) && !empty($parts['host'])) {
        return $url; // bereits absolut
    }

    $path = (string)($parts['path'] ?? '');
    if (!str_ends_with($path, '/media/file') && !str_ends_with($path, '/media/thumb')) {
        return $url;
    }

    $query = (string)($parts['query'] ?? '');
    $mediaPos = strrpos($path, '/media/');
    $mediaPath = $mediaPos === false ? $path : substr($path, $mediaPos);
    return rtrim($cmsBaseUrl, '/') . $mediaPath . ($query !== '' ? ('?' . $query) : '');
}

function frontendFormSecret(): string
{
    $secret = (string)(getenv('FRONTEND_FORM_SECRET') ?: '');
    if ($secret !== '') {
        return $secret;
    }
    $fallback = (string)(getenv('CMS_API_TOKEN') ?: '');
    if ($fallback !== '') {
        return $fallback;
    }
    return hash('sha256', __FILE__ . '|' . PHP_VERSION);
}

function contactFormCreateSig(string $slug, string $formId, int $ts): string
{
    $payload = trim($slug, '/') . '|' . trim($formId) . '|' . (string)$ts;
    return hash_hmac('sha256', $payload, frontendFormSecret());
}

function contactFormCreateRobotSig(string $slug, string $formId, int $ts): string
{
    $payload = trim($slug, '/') . '|' . trim($formId) . '|' . (string)$ts . '|robot';
    return hash_hmac('sha256', $payload, frontendFormSecret());
}

function contactFormCreateCaptchaSig(string $slug, string $formId, int $ts, int $a, int $b): string
{
    $payload = trim($slug, '/') . '|' . trim($formId) . '|' . (string)$ts . '|captcha|' . $a . '|' . $b;
    return hash_hmac('sha256', $payload, frontendFormSecret());
}

function isHttpsRequest(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto === 'https') {
        return true;
    }
    $forwardedSsl = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    return $forwardedSsl === 'on';
}

function contactTurnstileSiteKey(): string
{
    return trim((string)(getenv('TURNSTILE_SITE_KEY') ?: ''));
}

function contactTurnstileSecretKey(): string
{
    return trim((string)(getenv('TURNSTILE_SECRET_KEY') ?: ''));
}

function contactTurnstileEnabled(): bool
{
    return contactTurnstileSiteKey() !== '' && contactTurnstileSecretKey() !== '';
}

function verifyTurnstileToken(string $token, string $remoteIp = ''): bool
{
    $secret = contactTurnstileSecretKey();
    if ($secret === '') {
        return false;
    }
    $post = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => trim($remoteIp),
    ]);

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $body = curl_exec($ch);
        $ok = $body !== false && curl_errno($ch) === 0;
        curl_close($ch);
        if (!$ok || !is_string($body) || $body === '') {
            return false;
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $post,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || !is_string($raw) || $raw === '') {
            return false;
        }
        $body = $raw;
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) && !empty($decoded['success']);
}

function contactEncryptionPublicKeyRaw(): string
{
    $tenantKey = trim((string)(getenv('CONTACT_FORM_ENCRYPTION_PUBLIC_KEY') ?: ''));
    if ($tenantKey !== '') {
        return $tenantKey;
    }
    return trim((string)(getenv('GLOBAL_CONTACT_ENCRYPTION_PUBLIC_KEY') ?: ''));
}

function loadContactEncryptionPublicKeyPem(): string
{
    $raw = contactEncryptionPublicKeyRaw();
    if ($raw === '') {
        return '';
    }
    if (is_file($raw)) {
        $pem = (string)@file_get_contents($raw);
        return trim($pem);
    }
    // Allow escaped newlines in .env values
    $raw = str_replace('\n', "\n", $raw);
    return trim($raw);
}

function encryptContactPayload(string $plainText): ?array
{
    if (!function_exists('openssl_seal')) {
        return null;
    }
    $pem = loadContactEncryptionPublicKeyPem();
    if ($pem === '') {
        return null;
    }
    $pubKey = @openssl_pkey_get_public($pem);
    if ($pubKey === false) {
        return null;
    }
    $sealed = '';
    $envKeys = [];
    $iv = '';
    $ok = @openssl_seal($plainText, $sealed, $envKeys, [$pubKey], 'AES-256-CBC', $iv);
    @openssl_free_key($pubKey);
    if ($ok === false || $ok <= 0 || $sealed === '' || empty($envKeys[0])) {
        return null;
    }
    return [
        'cipher' => 'AES-256-CBC',
        'sealed_b64' => base64_encode($sealed),
        'env_key_b64' => base64_encode((string)$envKeys[0]),
        'iv_b64' => base64_encode($iv),
    ];
}

function contactEncryptionRequired(): bool
{
    $raw = strtolower(trim((string)(getenv('CONTACT_FORM_ENCRYPTION_REQUIRED') ?: '1')));
    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

function contactRateLimitAllow(string $ip, int $windowSeconds = 600, int $maxWindow = 6, int $maxDay = 40): bool
{
    $ip = trim($ip);
    if ($ip === '') {
        $ip = '0.0.0.0';
    }

    $file = __DIR__ . '/storage/contact_rate_limit.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $now = time();
    $all = [];
    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $all = $decoded;
        }
    }

    $records = is_array($all[$ip] ?? null) ? $all[$ip] : [];
    $records = array_values(array_filter($records, static function ($t) use ($now): bool {
        return is_int($t) && $t > ($now - 86400);
    }));

    $recent = 0;
    foreach ($records as $t) {
        if ($t > ($now - $windowSeconds)) {
            $recent++;
        }
    }
    if ($recent >= $maxWindow || count($records) >= $maxDay) {
        return false;
    }

    $records[] = $now;
    $all[$ip] = $records;
    @file_put_contents($file, json_encode($all, JSON_UNESCAPED_SLASHES), LOCK_EX);
    return true;
}

function processContactFormSubmission(array $post, string $slug, string $siteName, string $recipientEmail, ?CmsApiClient $client = null): array
{
    if (($post['_cf'] ?? '') !== '1') {
        return ['handled' => false];
    }
    if (!isHttpsRequest()) {
        return [
            'handled' => true,
            'form_id' => '__global',
            'status' => 'error',
            'message' => 'Aus Sicherheitsgruenden ist das Kontaktformular nur ueber HTTPS verfuegbar.',
            'values' => [],
        ];
    }

    $formId = strtolower(trim((string)($post['_cf_form_id'] ?? '')));
    if ($formId === '' || preg_match('/^[a-z0-9_-]{1,64}$/', $formId) !== 1) {
        $formId = '__global';
    }

    $values = [
        'name' => trim((string)($post['name'] ?? '')),
        'email' => trim((string)($post['email'] ?? '')),
        'phone' => trim((string)($post['phone'] ?? '')),
        'message' => trim((string)($post['message'] ?? '')),
        'captcha_answer' => trim((string)($post['captcha_answer'] ?? '')),
        'privacy_consent' => (string)($post['privacy_consent'] ?? ''),
    ];

    $rawFields = trim((string)($post['_cf_fields'] ?? ''));
    $enabledMap = ['name' => false, 'email' => false, 'phone' => false, 'message' => false];
    if ($rawFields !== '') {
        foreach (explode(',', $rawFields) as $f) {
            $k = strtolower(trim($f));
            if (isset($enabledMap[$k])) {
                $enabledMap[$k] = true;
            }
        }
    } else {
        $enabledMap = ['name' => true, 'email' => true, 'phone' => true, 'message' => true];
    }
    if (!$enabledMap['name'] && !$enabledMap['email'] && !$enabledMap['phone'] && !$enabledMap['message']) {
        $enabledMap['message'] = true;
    }

    $fail = static function (string $msg) use ($formId, $values): array {
        return [
            'handled' => true,
            'form_id' => $formId,
            'status' => 'error',
            'message' => $msg,
            'values' => $values,
        ];
    };

    $honey = trim((string)($post['website'] ?? ''));
    if ($honey !== '') {
        return $fail('Nachricht konnte nicht gesendet werden. Bitte spaeter erneut versuchen.');
    }

    $ts = (int)($post['_cf_ts'] ?? 0);
    $sig = trim((string)($post['_cf_sig'] ?? ''));
    if ($ts <= 0 || $sig === '' || !hash_equals(contactFormCreateSig($slug, $formId, $ts), $sig)) {
        return $fail('Nachricht konnte nicht verifiziert werden.');
    }
    $robotSig = trim((string)($post['_cf_robot_sig'] ?? ''));
    if ($robotSig === '' || !hash_equals(contactFormCreateRobotSig($slug, $formId, $ts), $robotSig)) {
        return $fail('Sicherheitspruefung fehlgeschlagen. Bitte Formular neu laden.');
    }

    $capA = (int)($post['_cf_cap_a'] ?? 0);
    $capB = (int)($post['_cf_cap_b'] ?? 0);
    $capSig = trim((string)($post['_cf_cap_sig'] ?? ''));
    if ($capA < 1 || $capA > 50 || $capB < 1 || $capB > 50 || $capSig === '') {
        return $fail('Captcha fehlt. Bitte Formular neu laden.');
    }
    if (!hash_equals(contactFormCreateCaptchaSig($slug, $formId, $ts, $capA, $capB), $capSig)) {
        return $fail('Captcha konnte nicht verifiziert werden.');
    }
    $capAnswerRaw = trim((string)($post['captcha_answer'] ?? ''));
    if ($capAnswerRaw === '' || preg_match('/^-?\d+$/', $capAnswerRaw) !== 1) {
        return $fail('Bitte die Sicherheitsfrage loesen.');
    }
    if ((int)$capAnswerRaw !== ($capA + $capB)) {
        return $fail('Die Antwort auf die Sicherheitsfrage ist nicht korrekt.');
    }
    if ((string)($post['privacy_consent'] ?? '') !== '1') {
        return $fail('Bitte stimmen Sie dem Datenschutzhinweis zu.');
    }

    if (contactTurnstileEnabled()) {
        $turnstileToken = trim((string)($post['cf-turnstile-response'] ?? ''));
        if ($turnstileToken === '') {
            return $fail('Bitte bestaetigen Sie den Bot-Schutz.');
        }
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!verifyTurnstileToken($turnstileToken, $ip)) {
            return $fail('Bot-Schutz konnte nicht verifiziert werden. Bitte erneut versuchen.');
        }
    }

    $age = time() - $ts;
    if ($age < 3 || $age > 7200) {
        return $fail('Bitte Formular erneut laden und absenden.');
    }

    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!contactRateLimitAllow($ip)) {
        return $fail('Zu viele Anfragen. Bitte spaeter erneut versuchen.');
    }

    if ($enabledMap['name'] && (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 120)) {
        return $fail('Bitte einen gueltigen Namen eingeben.');
    }
    if ($enabledMap['email'] && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        return $fail('Bitte eine gueltige E-Mail-Adresse eingeben.');
    }
    if ($enabledMap['message'] && (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 4000)) {
        return $fail('Bitte Nachricht mit mindestens 10 Zeichen eingeben.');
    }
    if ($enabledMap['phone'] && $values['phone'] !== '' && mb_strlen($values['phone']) > 80) {
        return $fail('Telefonnummer ist zu lang.');
    }

    $linkCount = preg_match_all('~(https?://|www\.)~i', $values['message']);
    if (is_int($linkCount) && $linkCount > 5) {
        return $fail('Zu viele Links in der Nachricht.');
    }

    $to = trim($recipientEmail);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = trim((string)(getenv('CONTACT_FORM_TO') ?: ''));
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        frontendDebugLog('[FRONTEND] contact form recipient missing');
        return $fail('Kontaktformular ist aktuell nicht verfuegbar.');
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $from = trim((string)(getenv('CONTACT_FORM_FROM') ?: ('noreply@' . $host)));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'noreply@localhost';
    }

    $mailLines = [];
    $mailLines[] = 'Neue Kontaktanfrage';
    $mailLines[] = '';
    $mailLines[] = 'Name: ' . ($values['name'] !== '' ? $values['name'] : '-');
    $mailLines[] = 'E-Mail: ' . ($values['email'] !== '' ? $values['email'] : '-');
    if ($enabledMap['phone'] && $values['phone'] !== '') {
        $mailLines[] = 'Telefon: ' . $values['phone'];
    }
    $mailLines[] = 'Seite: /' . trim($slug, '/');
    $mailLines[] = 'Gesendet am: ' . date('d.m.Y H:i:s');
    $mailLines[] = 'IP: ' . ($ip !== '' ? $ip : '-');
    $mailLines[] = '';
    $mailLines[] = 'Nachricht:';
    $mailLines[] = $values['message'] !== '' ? $values['message'] : '-';
    $plain = implode("\n", $mailLines) . "\n";
    $encrypted = encryptContactPayload($plain);
    if (!is_array($encrypted)) {
        frontendDebugLog('[FRONTEND] contact payload encryption unavailable');
        if (contactEncryptionRequired()) {
            return $fail('Kontaktformular ist aktuell nicht verfuegbar (Verschluesselung fehlt).');
        }
    }

    if (is_array($encrypted)) {
        $subject = '[Kontakt] ' . $siteName . ' - neue Anfrage';
        $body = $plain;
    } else {
        $subject = '[Kontakt] ' . $siteName . ' - unverschluesselte Anfrage';
        $body = $plain;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $from,
        'X-Mailer: DigiWTAL Frontend',
    ];
    if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $values['email'];
    }

    $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
    if (!$sent) {
        frontendDebugLog('[FRONTEND] contact mail send failed');
        return $fail('Nachricht konnte nicht gesendet werden. Bitte spaeter erneut versuchen.');
    }

    if ($client instanceof CmsApiClient) {
        try {
            $client->submitFormSubmission([
                'form_id' => $formId,
                'slug' => trim($slug, '/'),
                'name' => $values['name'],
                'email' => $values['email'],
                'phone' => $values['phone'],
                'message' => $values['message'],
                'ip' => $ip,
                'user_agent' => trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'fields' => array_keys(array_filter($enabledMap, static fn(bool $v): bool => $v)),
            ]);
        } catch (Throwable $e) {
            frontendDebugLog('[FRONTEND] form submission persist failed: ' . $e->getMessage());
        }
    }

    return [
        'handled' => true,
        'form_id' => $formId,
        'status' => 'ok',
        'message' => 'Vielen Dank. Die Nachricht wurde erfolgreich gesendet.',
        'values' => [],
    ];
}
function enrichBlockFocusWithMedia(array $blocks, CmsApiClient $client, string $cmsBaseUrl = ''): array
{
    $cache = [];

    $enrich = function ($value) use (&$enrich, &$cache, $client, $cmsBaseUrl) {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        if ($isList) {
            foreach ($value as $i => $item) {
                $value[$i] = $enrich($item);
            }
            return $value;
        }

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $enrich($v);
                continue;
            }

            if (!is_string($v)) {
                continue;
            }

            $key = (string)$k;

            $isImageUrlLike = in_array($key, ['url', 'image_url', 'poster_url'], true)
                || (bool)preg_match('/_image_url$/', $key);
            $isMediaIdLike = in_array($key, ['media_id', 'image_media_id', 'poster_media_id'], true)
                || (bool)preg_match('/_media_id$/', $key);

            // 1) URL-basierte Felder: URL ggf. absolutisieren + Fokus anreichern
            if ($isImageUrlLike) {
                $value[$key] = absolutizeCmsMediaUrl($v, $cmsBaseUrl);
                $mediaId = extractMediaIdFromUrl($v);
                if ($mediaId === null) {
                    continue;
                }
                if (!array_key_exists($mediaId, $cache)) {
                    try {
                        $m = $client->getMedia($mediaId);
                    } catch (CmsApiException) {
                        $m = [];
                    }
                    $cache[$mediaId] = [
                        'url' => isset($m['url']) ? (string)$m['url'] : '',
                        'x' => isset($m['focus_x']) && $m['focus_x'] !== '' ? (float)$m['focus_x'] : null,
                        'y' => isset($m['focus_y']) && $m['focus_y'] !== '' ? (float)$m['focus_y'] : null,
                    ];
                }
                $fx = $cache[$mediaId]['x'];
                $fy = $cache[$mediaId]['y'];
                if ($fx !== null) {
                    $value[$key . '_focus_x'] = $fx;
                }
                if ($fy !== null) {
                    $value[$key . '_focus_y'] = $fy;
                }
                continue;
            }

            // 2) media_id-basierte Felder: URL-Fallback erzeugen + Fokus anreichern
            if ($isMediaIdLike) {
                $mid = (int)$v;
                if ($mid <= 0) {
                    continue;
                }
                if (!array_key_exists($mid, $cache)) {
                    try {
                        $m = $client->getMedia($mid);
                    } catch (CmsApiException) {
                        $m = [];
                    }
                    $cache[$mid] = [
                        'url' => isset($m['url']) ? (string)$m['url'] : '',
                        'x' => isset($m['focus_x']) && $m['focus_x'] !== '' ? (float)$m['focus_x'] : null,
                        'y' => isset($m['focus_y']) && $m['focus_y'] !== '' ? (float)$m['focus_y'] : null,
                    ];
                }
                $mediaUrl = absolutizeCmsMediaUrl((string)($cache[$mid]['url'] ?? ''), $cmsBaseUrl);
                if ($mediaUrl === '') {
                    continue;
                }

                if ($key === 'poster_media_id') {
                    $targetField = 'poster_url';
                } elseif ($key === 'media_id' || $key === 'image_media_id') {
                    $targetField = 'image_url';
                } elseif (preg_match('/_media_id$/', $key) === 1) {
                    $targetField = (string)preg_replace('/_media_id$/', '_image_url', $key);
                } else {
                    $targetField = 'image_url';
                }
                if (!isset($value[$targetField]) || (string)$value[$targetField] === '') {
                    $value[$targetField] = $mediaUrl;
                }
                // Bei Image-Blocks mit media_id zusätzlich "url" setzen.
                if ($key === 'media_id' && (!isset($value['url']) || (string)$value['url'] === '')) {
                    $value['url'] = $mediaUrl;
                }

                $fx = $cache[$mid]['x'];
                $fy = $cache[$mid]['y'];
                if ($fx !== null) {
                    $value[$targetField . '_focus_x'] = $fx;
                    if ($key === 'media_id') {
                        $value['url_focus_x'] = $fx;
                    }
                }
                if ($fy !== null) {
                    $value[$targetField . '_focus_y'] = $fy;
                    if ($key === 'media_id') {
                        $value['url_focus_y'] = $fy;
                    }
                }
            }
        }

        return $value;
    };

    return $enrich($blocks);
}

function enrichEventBlocksWithItems(array $blocks, CmsApiClient $client, string $cmsBaseUrl = ''): array
{
    $cache = [];

    $walk = function ($value) use (&$walk, &$cache, $client, $cmsBaseUrl) {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            foreach ($value as $i => $item) {
                $value[$i] = $walk($item);
            }
            return $value;
        }

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $walk($v);
            }
        }

        if ((string)($value['type'] ?? '') !== 'events') {
            return $value;
        }

        $rawCategories = trim((string)($value['category_slugs'] ?? ($value['category_slug'] ?? '')));
        $categoryList = $rawCategories !== ''
            ? array_values(array_filter(array_map(static fn(string $v): string => trim($v), explode(',', $rawCategories)), static fn(string $v): bool => $v !== ''))
            : [];
        $rawLimit = strtolower(trim((string)($value['limit'] ?? 'all')));
        if ($rawLimit === 'all') {
            $limit = 500;
        } else {
            $limit = (int)$rawLimit;
            if ($limit <= 0) $limit = 50;
            if ($limit > 500) $limit = 500;
        }
        $includePast = true;
        $cacheKey = strtolower(implode(',', $categoryList)) . '|' . $limit . '|' . ($includePast ? '1' : '0');

        if (!array_key_exists($cacheKey, $cache)) {
            try {
                $resp = $client->getEvents($categoryList, $limit, $includePast);
                $items = is_array($resp['items'] ?? null) ? $resp['items'] : [];
            } catch (CmsApiException) {
                $items = [];
            }
            if ($cmsBaseUrl !== '') {
                foreach ($items as $i => $item) {
                    if (!is_array($item)) continue;
                    $img = (string)($item['image_url'] ?? '');
                    $items[$i]['image_url'] = absolutizeCmsMediaUrl($img, $cmsBaseUrl);
                    $variants = is_array($item['image_variants'] ?? null) ? $item['image_variants'] : [];
                    foreach ($variants as $vi => $variant) {
                        if (!is_array($variant)) continue;
                        $vimg = (string)($variant['image_url'] ?? '');
                        $variants[$vi]['image_url'] = absolutizeCmsMediaUrl($vimg, $cmsBaseUrl);
                    }
                    $items[$i]['image_variants'] = $variants;
                    $links = is_array($item['category_links'] ?? null) ? $item['category_links'] : [];
                    foreach ($links as $li => $link) {
                        if (!is_array($link)) continue;
                        $lurl = (string)($link['url'] ?? '');
                        $links[$li]['url'] = absolutizeCmsMediaUrl($lurl, $cmsBaseUrl);
                    }
                    $items[$i]['category_links'] = $links;
                    $logoMap = is_array($item['category_logo_map'] ?? null) ? $item['category_logo_map'] : [];
                    foreach ($logoMap as $slug => $logoUrl) {
                        $logoMap[$slug] = absolutizeCmsMediaUrl((string)$logoUrl, $cmsBaseUrl);
                    }
                    $items[$i]['category_logo_map'] = $logoMap;
                }
            }
            $cache[$cacheKey] = $items;
        }

        $value['items'] = $cache[$cacheKey];
        return $value;
    };

    return $walk($blocks);
}

// -------------------------------------------------------
function enrichNewsBlocksWithItems(array $blocks, CmsApiClient $client, string $cmsBaseUrl = ''): array
{
    $cache = [];

    $walk = function ($value) use (&$walk, &$cache, $client, $cmsBaseUrl) {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            foreach ($value as $i => $item) {
                $value[$i] = $walk($item);
            }
            return $value;
        }

        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $walk($v);
            }
        }

        if ((string)($value['type'] ?? '') !== 'news') {
            return $value;
        }

        $rawCategories = trim((string)($value['category_slugs'] ?? ''));
        $categoryList = $rawCategories !== ''
            ? array_values(array_filter(array_map(static fn(string $v): string => trim($v), explode(',', $rawCategories)), static fn(string $v): bool => $v !== ''))
            : [];
        $rawLimit = strtolower(trim((string)($value['limit'] ?? '6')));
        $limit = $rawLimit === 'all' ? 'all' : max(1, min(200, (int)$rawLimit));
        $cacheKey = strtolower(implode(',', $categoryList)) . '|' . (string)$limit;

        if (!array_key_exists($cacheKey, $cache)) {
            try {
                $resp = $client->getNews($categoryList, $limit);
                $items = is_array($resp['items'] ?? null) ? $resp['items'] : [];
            } catch (CmsApiException) {
                $items = [];
            }
            if ($cmsBaseUrl !== '') {
                foreach ($items as $i => $item) {
                    if (!is_array($item)) continue;
                    $img = (string)($item['image_url'] ?? '');
                    $items[$i]['image_url'] = absolutizeCmsMediaUrl($img, $cmsBaseUrl);
                }
            }
            $cache[$cacheKey] = $items;
        }

        $value['items'] = $cache[$cacheKey];
        return $value;
    };

    return $walk($blocks);
}
// Routing & slug normalization
// -------------------------------------------------------
$homeSlug = 'home';

if (!isHttpsRequest()) {
    $target = trim((string)(getenv('FRONTEND_BASE_URL') ?: ''));
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($target !== '' && preg_match('#^https://#i', $target) === 1) {
        $dest = rtrim($target, '/') . '/' . ltrim($requestUri, '/');
    } else {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        $dest = $host !== '' ? ('https://' . $host . $requestUri) : ('https://' . ltrim($requestUri, '/'));
    }
    header('Location: ' . $dest, true, 301);
    exit;
}
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri  = is_string($path) ? $path : '/';

if ($uri === '/sitemap.xml') {
    try {
        $xml = $client->getSitemapXml($cmsSitemapUrl !== '' ? $cmsSitemapUrl : null);
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $xml;
        exit;
    } catch (CmsApiException $e) {
        frontendDebugLog('[FRONTEND] sitemap fetch failed'
            . ' status=' . $e->statusCode
            . ' api_error=' . $e->apiError
            . ' body=' . substr($e->rawBody, 0, 500));
        http_response_code(502);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Sitemap currently unavailable\n";
        exit;
    }
}

if ($uri === '/robots.txt') {
    $base = trim($frontendBaseUrl);
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? '');
        $base   = $host !== '' ? ($scheme . '://' . $host) : '';
    }
    $base = rtrim($base, '/');
    $sitemapLine = $base !== '' ? ($base . '/sitemap.xml') : '/sitemap.xml';

    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo 'Sitemap: ' . $sitemapLine . "\n";
    exit;
}

$slug = trim($uri, '/');

if ($slug === '') {
    $slug = resolveHomeSlug($client, $homeSlug);
} else {
    $slug = strtolower($slug);
    $slug = preg_replace('#/+#', '/', $slug);
    $slug = trim((string)$slug, '/');
}

// Validate slug format
if (!preg_match('/^[a-z0-9\/-]+$/', $slug)) {
    $siteName = 'Website';
    try {
        $settings = $client->getPublicSettings();
        $siteName = (string)($settings['site_name'] ?? 'Website');
    } catch (CmsApiException) {
        // Use default
    }
    render404($siteName);
}

// -------------------------------------------------------
// Load settings
// -------------------------------------------------------
try {
    $settings = $client->getPublicSettings();
    $siteName = (string)($settings['site_name'] ?? 'Website');
    $faviconUrl = null;
    $headerLogoUrl = null;
    $contactEmail = trim((string)($settings['contact_email'] ?? ''));
    if (isset($settings['favicon_url']) && is_string($settings['favicon_url']) && $settings['favicon_url'] !== '') {
        $faviconUrl = absolutizeCmsMediaUrl($settings['favicon_url'], deriveCmsBaseUrlFromApiBase($baseUrl));
    }
    $logoCandidate = '';
    if (isset($settings['cms_logo_light_url']) && is_string($settings['cms_logo_light_url'])) {
        $logoCandidate = trim($settings['cms_logo_light_url']);
    }
    if ($logoCandidate === '' && isset($settings['logo_url']) && is_string($settings['logo_url'])) {
        $logoCandidate = trim($settings['logo_url']);
    }
    if ($logoCandidate !== '') {
        $headerLogoUrl = absolutizeCmsMediaUrl($logoCandidate, deriveCmsBaseUrlFromApiBase($baseUrl));
    }
} catch (CmsApiException $e) {
    frontendDebugLog('[FRONTEND] settings/public failed'
        . ' base_url=' . $baseUrl
        . ' status=' . $e->statusCode
        . ' api_error=' . $e->apiError
        . ' body=' . substr($e->rawBody, 0, 500));
    render500('Website');
}

// -------------------------------------------------------
// Load navigation items
// -------------------------------------------------------
$navItems = [];
try {
    $navResult = $client->getNavigation();
    $navItems = $navResult['items'] ?? [];
} catch (CmsApiException) {
    // Navigation failure should not break the page
    $navItems = [];
}

// -------------------------------------------------------
// Load page
// -------------------------------------------------------
try {
    $page = $client->getPage($slug);
} catch (CmsApiException $e) {
    frontendDebugLog('[FRONTEND] page fetch failed'
        . ' base_url=' . $baseUrl
        . ' slug=' . $slug
        . ' status=' . $e->statusCode
        . ' api_error=' . $e->apiError
        . ' body=' . substr($e->rawBody, 0, 500));
    if ($e->statusCode === 404) {
        render404($siteName);
    }
    if (in_array($e->apiError, ['network_error', 'invalid_json'], true) 
        || $e->statusCode >= 500 
        || $e->statusCode === 0) {
        render500($siteName);
    }
    render500($siteName);
}

// -------------------------------------------------------
// Render HTML
// -------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$pageTitle = trim((string)($page['frontend_title'] ?? ''));
if ($pageTitle === '') {
    $pageTitle = (string)($page['title'] ?? 'Seite');
}
$pageSubtitle = trim((string)($page['subtitle'] ?? ''));
$internalTitle = trim((string)($page['title'] ?? ''));
if ($internalTitle === '') {
    $internalTitle = $pageTitle;
}
$title = $internalTitle . ' - ' . $siteName;
$blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
$cmsBaseUrl = deriveCmsBaseUrlFromApiBase($baseUrl);
$blocks = enrichBlockFocusWithMedia($blocks, $client, $cmsBaseUrl);
$blocks = enrichEventBlocksWithItems($blocks, $client, $cmsBaseUrl);
$blocks = enrichNewsBlocksWithItems($blocks, $client, $cmsBaseUrl);
$seo = is_array($page['seo'] ?? null) ? $page['seo'] : [];
$contactFormStates = [];
$contactTurnstileSiteKey = contactTurnstileSiteKey();
$publicSettings = is_array($settings ?? null) ? $settings : [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submission = processContactFormSubmission($_POST, $slug, $siteName, (string)($contactEmail ?? ''), $client);
    if (!empty($submission['handled'])) {
        $key = (string)($submission['form_id'] ?? '__global');
        $contactFormStates[$key] = [
            'status' => (string)($submission['status'] ?? 'error'),
            'message' => (string)($submission['message'] ?? ''),
            'values' => is_array($submission['values'] ?? null) ? $submission['values'] : [],
        ];
    }
}

try {
    render('templates/layout.php', compact('siteName', 'title', 'pageTitle', 'pageSubtitle', 'blocks', 'navItems', 'slug', 'seo', 'faviconUrl', 'headerLogoUrl', 'contactFormStates', 'contactTurnstileSiteKey', 'publicSettings', 'client'));
} catch (Throwable) {
    render500($siteName);
}



