<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Redirect;
use App\Services\UserPrefsService;

final class ThemeController
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

        $svc  = new UserPrefsService();
        $data = $svc->handleThemePost();

        \admin_set_pref((int)$user['id'], 'theme', (string)($data['theme'] ?? 'dark'));

        $next = (string)($data['next'] ?? '/');
        Redirect::to($next, 302);
    }
}
