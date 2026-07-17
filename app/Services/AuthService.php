<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Paths;
use App\Http\Redirect;

/**
 * Produktreifer Auth-Usecase für Login/Logout.
 * HTTP bleibt im Controller minimal, Redirects laufen über Redirect.
 */
final class AuthService
{
    /**
     * @return array{ok:bool, message:?string}
     */
    public function handleLoginPost(): array
    {
        $username = (string)($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            return ['ok' => false, 'message' => 'Bitte Benutzername und Passwort eingeben.'];
        }

        // admin_login() kommt aus app/admin_auth.php
        if (\admin_login($username, $password)) {
            return ['ok' => true, 'message' => null];
        }

        return ['ok' => false, 'message' => 'Login fehlgeschlagen.'];
    }

    public function redirectIfAlreadyLoggedIn(): void
    {
        if (\admin_current_user() !== null) {
            Redirect::to(Paths::DASHBOARD, 302);
        }
    }

    public function logoutAndRedirect(): void
    {
        \admin_logout();
        Redirect::to(Paths::LOGIN, 302);
    }
}
