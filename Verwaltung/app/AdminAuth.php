<?php
declare(strict_types=1);

class AdminAuth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 10;
    private const SESSION_TIMEOUT = 14400; // 4 hours in seconds

    private static function currentIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function isLoggedIn(): bool
    {
        if (!isset($_SESSION['admin_id']) || !is_int($_SESSION['admin_id'])) {
            return false;
        }
        if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== self::currentIp()) {
            return false;
        }

        // CHECK SESSION TIMEOUT
        $lastActive = $_SESSION['admin_last_active'] ?? 0;
        if (time() - (int)$lastActive > self::SESSION_TIMEOUT) {
            self::logout();
            return false;
        }

        // UPDATE ACTIVITY
        $_SESSION['admin_last_active'] = time();

        return true;
    }

    public static function getUserId(): ?int
    {
        return self::isLoggedIn() ? $_SESSION['admin_id'] : null;
    }

    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login');
            exit;
        }
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $userId;
        $_SESSION['admin_ip'] = self::currentIp();
        $_SESSION['admin_last_active'] = time(); // SET INITIAL ACTIVITY
        unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_ip']);
        self::clearAttempts();
    }

    public static function setPending2FA(int $userId): void
    {
        $_SESSION['pending_2fa'] = $userId;
        $_SESSION['pending_2fa_ip'] = self::currentIp();
    }

    public static function getPending2FA(): ?int
    {
        if (!isset($_SESSION['pending_2fa']) || !is_int($_SESSION['pending_2fa'])) {
            return null;
        }
        if (isset($_SESSION['pending_2fa_ip']) && $_SESSION['pending_2fa_ip'] !== self::currentIp()) {
            unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_ip']);
            return null;
        }
        return $_SESSION['pending_2fa'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function clearAttempts(): void
    {
        unset($_SESSION['login_attempts']);
    }
}
