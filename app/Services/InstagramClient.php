<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Schlanker Client fuer die "Instagram API with Instagram Login" (Business Login).
 * Kein Composer/SDK - reines cURL, analog zu Frontend/app/CmsApiClient.php.
 *
 * Ablauf:
 *   1. authorizeUrl()            -> Redirect zu Instagram (Nutzer bestaetigt)
 *   2. exchangeCodeForToken()    -> code -> kurzlebiger Token (1h)
 *   3. exchangeForLongLivedToken() -> kurzlebiger -> langlebiger Token (60 Tage)
 *   4. refreshLongLivedToken()   -> verlaengert einen langlebigen Token um weitere 60 Tage
 *   5. fetchProfile()/fetchMedia() -> Graph-API-Aufrufe mit dem langlebigen Token
 */
final class InstagramClient
{
    private const AUTHORIZE_URL = 'https://www.instagram.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const GRAPH_BASE = 'https://graph.instagram.com';
    private const SCOPE = 'instagram_business_basic';

    public function __construct(
        private readonly string $appId,
        private readonly string $appSecret,
    ) {
    }

    public function authorizeUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
        ]);
        return self::AUTHORIZE_URL . '?' . $query;
    }

    /** @return array{access_token:string,user_id:string} */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $result = $this->post(self::TOKEN_URL, [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!isset($result['access_token'])) {
            throw new RuntimeException('instagram_code_exchange_failed');
        }

        return [
            'access_token' => (string)$result['access_token'],
            'user_id' => (string)($result['user_id'] ?? ''),
        ];
    }

    /** @return array{access_token:string,expires_in:int} */
    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $url = self::GRAPH_BASE . '/access_token?' . http_build_query([
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->appSecret,
            'access_token' => $shortLivedToken,
        ]);

        $result = $this->get($url);
        if (!isset($result['access_token'])) {
            throw new RuntimeException('instagram_long_lived_exchange_failed');
        }

        return [
            'access_token' => (string)$result['access_token'],
            'expires_in' => (int)($result['expires_in'] ?? 5184000),
        ];
    }

    /** @return array{access_token:string,expires_in:int} */
    public function refreshLongLivedToken(string $longLivedToken): array
    {
        $url = self::GRAPH_BASE . '/refresh_access_token?' . http_build_query([
            'grant_type' => 'ig_refresh_token',
            'access_token' => $longLivedToken,
        ]);

        $result = $this->get($url);
        if (!isset($result['access_token'])) {
            throw new RuntimeException('instagram_token_refresh_failed');
        }

        return [
            'access_token' => (string)$result['access_token'],
            'expires_in' => (int)($result['expires_in'] ?? 5184000),
        ];
    }

    /** @return array{id:string,username:string} */
    public function fetchProfile(string $accessToken): array
    {
        $url = self::GRAPH_BASE . '/me?' . http_build_query([
            'fields' => 'id,username',
            'access_token' => $accessToken,
        ]);
        $result = $this->get($url);

        return [
            'id' => (string)($result['id'] ?? ''),
            'username' => (string)($result['username'] ?? ''),
        ];
    }

    /** @return list<array{id:string,caption:string,media_type:string,media_url:string,thumbnail_url:string,permalink:string,timestamp:string}> */
    public function fetchMedia(string $accessToken, int $limit = 12): array
    {
        $url = self::GRAPH_BASE . '/me/media?' . http_build_query([
            'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
            'limit' => max(1, min(50, $limit)),
            'access_token' => $accessToken,
        ]);
        $result = $this->get($url);

        $items = is_array($result['data'] ?? null) ? $result['data'] : [];
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = [
                'id' => (string)($item['id'] ?? ''),
                'caption' => (string)($item['caption'] ?? ''),
                'media_type' => (string)($item['media_type'] ?? ''),
                'media_url' => (string)($item['media_url'] ?? ''),
                'thumbnail_url' => trim((string)($item['thumbnail_url'] ?? '')) !== ''
                    ? (string)$item['thumbnail_url']
                    : (string)($item['media_url'] ?? ''),
                'permalink' => (string)($item['permalink'] ?? ''),
                'timestamp' => (string)($item['timestamp'] ?? ''),
            ];
        }

        return $out;
    }

    // -------------------------------------------------------
    // Internes: minimaler cURL-HTTP-Client
    // -------------------------------------------------------

    private function get(string $url): array
    {
        return $this->request($url, 'GET');
    }

    private function post(string $url, array $payload): array
    {
        return $this->request($url, 'POST', $payload);
    }

    private function request(string $url, string $method, array $payload = []): array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($payload);
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            throw new RuntimeException('instagram_network_error: ' . $err);
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('instagram_invalid_response');
        }

        if ($status >= 400) {
            $msg = is_array($decoded['error'] ?? null)
                ? (string)($decoded['error']['message'] ?? 'instagram_api_error')
                : (string)($decoded['error_message'] ?? 'instagram_api_error');
            throw new RuntimeException('instagram_api_error: ' . $msg);
        }

        return $decoded;
    }
}
