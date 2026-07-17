<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Redirect;
use App\Services\UserPrefsService;

final class UserPrefsController
{
    public function handle(): void
    {
        $user = \admin_require_login();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ Zentrales CSRF für Admin-POSTs
        \admin_verify_csrf();

        $svc    = new UserPrefsService();
        $result = $svc->saveFromPost((int)$user['id']);

        if (!(bool)($result['ok'] ?? false)) {
            http_response_code(400);

            $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
            if (str_contains($accept, 'application/json')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid preference'], JSON_UNESCAPED_SLASHES);
                return;
            }

            echo 'Bad Request';
            return;
        }

        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson =
            str_contains($accept, 'application/json')
            || (string)($_POST['format'] ?? '') === 'json'
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] !== '');

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => true,
                'key'   => (string)($result['key'] ?? ''),
                'value' => (string)($result['value'] ?? ''),
            ], JSON_UNESCAPED_SLASHES);
            return;
        }

        $next = (string)($result['next'] ?? '/');
        Redirect::to($next, 302);
    }
}
