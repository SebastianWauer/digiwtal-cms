<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Env;
use App\Repositories\SiteSettingsRepositoryDb;
use App\Services\InstagramClient;
use App\Services\InstagramTokenCrypto;
use RuntimeException;

/**
 * OAuth-Anbindung an die Instagram API (native Meta Graph API, kein
 * Drittanbieter-Tool - siehe README "Grundsatz: keine Drittanbieter-Embed-Dienste").
 */
final class InstagramController
{
    private function client(): ?InstagramClient
    {
        $appId = trim((string)Env::get('INSTAGRAM_APP_ID', ''));
        $appSecret = trim((string)Env::get('INSTAGRAM_APP_SECRET', ''));
        if ($appId === '' || $appSecret === '') {
            return null;
        }
        return new InstagramClient($appId, $appSecret);
    }

    private function redirectUri(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $base = function_exists('cms_base_path') ? cms_base_path() : '';
        return ($https ? 'https' : 'http') . '://' . $host . $base . '/settings/instagram/callback';
    }

    private function flashAndRedirect(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
        header('Location: ' . (function_exists('cms_base_path') ? cms_base_path() : '') . '/settings');
        exit;
    }

    public function connect(): void
    {
        \admin_require_perm('settings.view');

        $client = $this->client();
        if ($client === null) {
            $this->flashAndRedirect('error', 'Instagram ist nicht konfiguriert (INSTAGRAM_APP_ID / INSTAGRAM_APP_SECRET fehlen in der .env).');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['instagram_oauth_state'] = $state;

        header('Location: ' . $client->authorizeUrl($this->redirectUri(), $state));
        exit;
    }

    public function callback(): void
    {
        \admin_require_perm('settings.view');

        $client = $this->client();
        if ($client === null) {
            $this->flashAndRedirect('error', 'Instagram ist nicht konfiguriert.');
        }

        $error = trim((string)($_GET['error_description'] ?? $_GET['error'] ?? ''));
        if ($error !== '') {
            $this->flashAndRedirect('error', 'Instagram-Verbindung abgebrochen: ' . $error);
        }

        $state = trim((string)($_GET['state'] ?? ''));
        $expectedState = (string)($_SESSION['instagram_oauth_state'] ?? '');
        unset($_SESSION['instagram_oauth_state']);
        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            $this->flashAndRedirect('error', 'Instagram-Verbindung fehlgeschlagen (ungültiger State).');
        }

        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '') {
            $this->flashAndRedirect('error', 'Instagram-Verbindung fehlgeschlagen (kein Code erhalten).');
        }

        try {
            $short = $client->exchangeCodeForToken($code, $this->redirectUri());
            $long = $client->exchangeForLongLivedToken($short['access_token']);
            $profile = $client->fetchProfile($long['access_token']);

            $repo = new SiteSettingsRepositoryDb(\db());
            $repo->set('instagram_access_token_enc', InstagramTokenCrypto::encrypt($long['access_token']));
            $repo->set('instagram_token_expires_at', (string)(time() + $long['expires_in']));
            $repo->set('instagram_user_id', $profile['id'] !== '' ? $profile['id'] : $short['user_id']);
            $repo->set('instagram_username', $profile['username']);
            $repo->set('instagram_connected_at', (string)time());
            $repo->set('instagram_media_cache_json', null);
            $repo->set('instagram_media_cache_at', null);
        } catch (RuntimeException $e) {
            \FileLogger::channel('cms')->error('[INSTAGRAM] OAuth callback failed: ' . $e->getMessage());
            $this->flashAndRedirect('error', 'Instagram-Verbindung fehlgeschlagen. Bitte erneut versuchen.');
        }

        $this->flashAndRedirect('success', 'Instagram erfolgreich verbunden als @' . $profile['username'] . '.');
    }

    public function disconnect(): void
    {
        \admin_require_perm('settings.view');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $repo = new SiteSettingsRepositoryDb(\db());
        foreach (
            [
                'instagram_access_token_enc',
                'instagram_token_expires_at',
                'instagram_user_id',
                'instagram_username',
                'instagram_connected_at',
                'instagram_media_cache_json',
                'instagram_media_cache_at',
            ] as $key
        ) {
            $repo->set($key, null);
        }

        $this->flashAndRedirect('success', 'Instagram-Verbindung getrennt.');
    }
}
