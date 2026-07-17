<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Paths;
use App\Http\Redirect;
use App\Services\AuthService;

final class LoginController
{
    public function show(): void
    {
        $auth = new AuthService();
        $auth->redirectIfAlreadyLoggedIn();

        $flash = null;
        require __DIR__ . '/../Views/login.php';
    }

    public function submit(): void
    {
        $auth = new AuthService();
        $auth->redirectIfAlreadyLoggedIn();

        // CSRF Pflicht bei POST
        \admin_verify_csrf();

        $flash = null;

        $res = $auth->handleLoginPost();
        if (!empty($res['ok'])) {
            Redirect::to(Paths::DASHBOARD, 302);
        }

        $flash = ['type' => 'error', 'msg' => (string)($res['message'] ?? 'Login fehlgeschlagen.')];
        require __DIR__ . '/../Views/login.php';
    }
}
