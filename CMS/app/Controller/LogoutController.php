<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\AuthService;

final class LogoutController
{
    public function handle(): void
    {
        // Logout ist POST -> CSRF Pflicht
        \admin_verify_csrf();

        (new AuthService())->logoutAndRedirect();
    }
}
