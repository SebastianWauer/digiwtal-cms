<?php
declare(strict_types=1);

/**
 * CmsApiException – thrown on HTTP ≥ 400, JSON parse errors, and network errors.
 */
class CmsApiException extends RuntimeException
{
    public function __construct(
        public readonly int    $statusCode,
        public readonly string $apiError,
        public readonly string $rawBody,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : $apiError, $statusCode, $previous);
    }
}

/**
 * CmsApiClient – lean HTTP client for DigiWTAL CMS REST API.
 *
 * Requirements: PHP 8.1+, curl extension OR allow_url_fopen=On
 * No Composer, no dependencies.
 */
class CmsApiClient
{
    private string  $baseUrl;
    private ?string $token;
    private int     $timeout;
    private int     $cacheTtl;
    private string  $cacheDir;

    public function __construct(
        string  $baseUrl,
        ?string $token    = null,
        int     $timeout  = 5,
        int     $cacheTtl = 0,
        string  $cacheDir = ''
    ) {
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->token    = ($token !== null && $token !== '') ? $token : null;
        $this->timeout  = max(1, $timeout);
        $this->cacheTtl = max(0, $cacheTtl);
        $this->cacheDir = $cacheDir !== '' ? rtrim($cacheDir, '/') : '';
    }

    // -------------------------------------------------------
    // Public API methods
    // -------------------------------------------------------

    /**
     * GET /pages?page=N
     * Returns full API response including items[] and pagination{}.
     */
    public function getPages(int $page = 1): array
    {
        return $this->get('/pages', ['page' => max(1, $page)]);
    }

    /**
     * GET /pages/{slug}
     * Returns page JSON with blocks[] and seo{}.
     */
    public function getPage(string $slug): array
    {
        $slug = ltrim($slug, '/');
        if ($slug === '') {
            throw new \InvalidArgumentException('slug must not be empty');
        }
        return $this->get('/pages/' . rawurlencode($slug));
    }

    /**
     * GET /settings/public
     * Returns site_name, brand_color_*, logo_url.
     */
    public function getPublicSettings(): array
    {
        return $this->get('/settings/public');
    }

    /**
     * GET /instagram/media
     * Returns connected, username, items[] (bereits vom CMS geladene Instagram-Posts).
     */
    public function getInstagramMedia(): array
    {
        return $this->get('/instagram/media');
    }

    /**
     * GET /media/{id}
     * Returns media metadata (url, mime, dimensions, alt).
     */
    public function getMedia(int $id): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('id must be a positive integer');
        }
        return $this->get('/media/' . $id);
    }

    /**
     * GET /navigation
     * Returns navigation items with slug, title, url, nav_order, etc.
     */
    public function getNavigation(): array
    {
        return $this->get('/navigation');
    }

    /**
     * GET /events
     */
    public function getEvents(array $categorySlugs = [], int $limit = 6, bool $includePast = false): array
    {
        $query = [
            'limit' => max(1, min(500, $limit)),
            'include_past' => $includePast ? 1 : 0,
        ];
        $normalized = array_values(array_unique(array_filter(array_map(static function (string $v): string {
            $v = trim($v);
            $v = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($v)) ?? '';
            return trim($v, '-');
        }, $categorySlugs), static fn(string $v): bool => $v !== '')));
        if ($normalized !== []) {
            $query['categories'] = implode(',', $normalized);
        }
        return $this->get('/events', $query);
    }

    /**
     * GET /news
     */
    public function getNews(array $categorySlugs = [], int|string $limit = 6): array
    {
        $query = [];
        if ($limit === 'all') {
            $query['limit'] = 'all';
        } else {
            $query['limit'] = max(1, min(200, (int)$limit));
        }
        $normalized = array_values(array_unique(array_filter(array_map(static function (string $v): string {
            $v = trim($v);
            $v = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($v)) ?? '';
            return trim($v, '-');
        }, $categorySlugs), static fn(string $v): bool => $v !== '')));
        if ($normalized !== []) {
            $query['categories'] = implode(',', $normalized);
        }
        return $this->get('/news', $query);
    }

    /**
     * POST /form/submit
     */
    public function submitFormSubmission(array $payload): array
    {
        return $this->post('/form/submit', $payload);
    }

    /**
     * GET /sitemap.xml on CMS frontend host.
     */
    public function getSitemapXml(?string $sitemapUrl = null): string
    {
        $url = $sitemapUrl !== null && trim($sitemapUrl) !== ''
            ? trim($sitemapUrl)
            : $this->deriveSitemapUrl();

        [$status, $body] = $this->request($url, [
            'Accept: application/xml, text/xml;q=0.9, */*;q=0.1',
            'User-Agent: DigiWTAL-Frontend/1.0',
        ]);

        if ($status >= 400) {
            throw new CmsApiException($status, 'http_error', $body, 'Failed to fetch sitemap');
        }

        return (string)$body;
    }

    // -------------------------------------------------------
    // Internal: GET with optional file cache
    // -------------------------------------------------------

    private function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = $this->buildHeaders();

        // --- Read from cache ---
        $cachePath = $this->cachePath($url, $headers);
        if ($cachePath !== null && is_file($cachePath)) {
            $age = time() - (int)filemtime($cachePath);
            if ($age < $this->cacheTtl) {
                $raw = file_get_contents($cachePath);
                if ($raw !== false) {
                    $data = json_decode($raw, true);
                    if (is_array($data)) {
                        return $data;
                    }
                }
            }
        }

        [$status, $body] = $this->request($url, $headers);

        if ($status >= 400) {
            $decoded = json_decode($body, true);
            $apiErr  = is_array($decoded) ? (string)($decoded['error'] ?? 'http_error') : 'http_error';
            throw new CmsApiException($status, $apiErr, $body);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new CmsApiException(0, 'invalid_json', $body);
        }

        // --- Write to cache ---
        if ($cachePath !== null && $this->cacheTtl > 0) {
            $this->writeCache($cachePath, $body);
        }

        return $data;
    }

    private function post(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;
        $headers = $this->buildHeaders();
        $headers[] = 'Content-Type: application/json';
        $bodyRaw = (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$status, $body] = $this->request($url, $headers, 'POST', $bodyRaw);
        if ($status >= 400) {
            $decoded = json_decode($body, true);
            $apiErr = is_array($decoded) ? (string)($decoded['error'] ?? 'http_error') : 'http_error';
            throw new CmsApiException($status, $apiErr, $body);
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new CmsApiException(0, 'invalid_json', $body);
        }
        return $data;
    }

    // -------------------------------------------------------
    // Internal: HTTP transport (cURL → stream fallback)
    // -------------------------------------------------------

    /** @return array{0:int,1:string} */
    private function request(string $url, array $headers, string $method = 'GET', ?string $body = null): array
    {
        if (function_exists('curl_init')) {
            return $this->curlRequest($url, $headers, $method, $body);
        }
        return $this->streamRequest($url, $headers, $method, $body);
    }

    /** @return array{0:int,1:string} */
    private function curlRequest(string $url, array $headers, string $method = 'GET', ?string $body = null): array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if (strtoupper($method) !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            throw new CmsApiException(0, 'network_error', $err);
        }

        return [$code, (string)$body];
    }

    /** @return array{0:int,1:string} */
    private function streamRequest(string $url, array $headers, string $method = 'GET', ?string $body = null): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => strtoupper($method),
                'header'        => implode("\r\n", $headers),
                'content'       => $body ?? '',
                'timeout'       => (float)$this->timeout,
                'ignore_errors' => true,   // receive body even on 4xx/5xx
            ],
            'ssl'  => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        // @ suppresses connection warning; false signals failure
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new CmsApiException(0, 'network_error', '');
        }

        $code = 200;
        if (!empty($http_response_header[0])
            && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }

        return [$code, $body];
    }

    // -------------------------------------------------------
    // Internal: helpers
    // -------------------------------------------------------

    /** @return string[] */
    private function buildHeaders(): array
    {
        $h = [
            'Accept: application/json',
            'User-Agent: DigiWTAL-Frontend/1.0',
        ];
        if ($this->token !== null) {
            $h[] = 'Authorization: Bearer ' . $this->token;
        }
        return $h;
    }

    private function cachePath(string $url, array $headers): ?string
    {
        if ($this->cacheDir === '' || $this->cacheTtl <= 0) {
            return null;
        }
        $key = sha1($url . "\n" . implode("\n", $headers));
        return $this->cacheDir . '/' . $key . '.json';
    }

    private function writeCache(string $path, string $body): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, $body, LOCK_EX);
    }

    private function deriveSitemapUrl(): string
    {
        $base = $this->baseUrl;
        $patterns = [
            '#/api\.php/api/v1/?$#i',
            '#/api/v1/?$#i',
            '#/api\.php/?$#i',
            '#/api/?$#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $base) === 1) {
                $base = preg_replace($pattern, '', $base) ?? $base;
                break;
            }
        }

        return rtrim($base, '/') . '/sitemap.xml';
    }
}
