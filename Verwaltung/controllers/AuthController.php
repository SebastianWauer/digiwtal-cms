<?php
declare(strict_types=1);

class AuthController
{
    private AdminUserRepository $userRepo;
    private PDO $pdo;
    private AuditLogger $audit;

    public function __construct(AdminUserRepository $userRepo, PDO $pdo, AuditLogger $audit)
    {
        $this->userRepo = $userRepo;
        $this->pdo = $pdo;
        $this->audit = $audit;
    }

    private function getIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function countAttempts(string $type): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM admin_login_attempts
             WHERE ip_address = ? AND attempt_type = ? AND attempted_at > (NOW() - INTERVAL 10 MINUTE)"
        );
        $stmt->execute([$this->getIp(), $type]);
        return (int)$stmt->fetchColumn();
    }

    private function recordAttempt(string $type): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO admin_login_attempts (ip_address, attempt_type) VALUES (?, ?)"
        );
        $stmt->execute([$this->getIp(), $type]);

        // optional cleanup (alte Einträge)
        $this->pdo->exec("DELETE FROM admin_login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    }

    public function showLogin(): void
    {
        if (AdminAuth::isLoggedIn()) {
            header('Location: /admin/dashboard');
            exit;
        }
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        require __DIR__ . '/../views/auth/login.php';
    }

    public function handleLogin(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['login_error'] = 'Invalid request';
            header('Location: /admin/login');
            exit;
        }

        if ($this->countAttempts('login') >= 5) {
            $_SESSION['login_error'] = 'Too many attempts. Try again in 10 minutes.';
            header('Location: /admin/login');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->recordAttempt('login');
            sleep(1);
            $_SESSION['login_error'] = 'Email and password required';
            header('Location: /admin/login');
            exit;
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $this->recordAttempt('login');
            sleep(1);
            $_SESSION['login_error'] = 'Invalid credentials';
            $this->audit->log('login.fail', 'auth', null, "email: {$email}");
            header('Location: /admin/login');
            exit;
        }

        if ($user['totp_secret'] !== null && $user['totp_secret'] !== '') {
            AdminAuth::setPending2FA((int)$user['id']);
            header('Location: /admin/verify-2fa');
            exit;
        }

        AdminAuth::login((int)$user['id']);
        $_SESSION['admin_email'] = (string)$email;
        $_SESSION['admin_role'] = (string)($user['role'] ?? 'operator');
        $this->pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
            ->execute([(int)$user['id']]);
        $this->audit->log('login.success', 'auth', null, "email: {$email}");
        header('Location: /admin/dashboard');
        exit;
    }

    public function showVerify2FA(): void
    {
        $userId = AdminAuth::getPending2FA();
        if ($userId === null) {
            header('Location: /admin/login');
            exit;
        }
        $error = $_SESSION['2fa_error'] ?? null;
        unset($_SESSION['2fa_error']);
        require __DIR__ . '/../views/auth/verify_2fa.php';
    }

    public function handleVerify2FA(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['2fa_error'] = 'Invalid request';
            header('Location: /admin/verify-2fa');
            exit;
        }

        if ($this->countAttempts('2fa') >= 5) {
            $_SESSION['2fa_error'] = 'Too many attempts. Try again in 10 minutes.';
            header('Location: /admin/verify-2fa');
            exit;
        }

        $userId = AdminAuth::getPending2FA();
        if ($userId === null) {
            header('Location: /admin/login');
            exit;
        }

        $user = $this->userRepo->findById($userId);
        if ($user === null || $user['totp_secret'] === null) {
            header('Location: /admin/login');
            exit;
        }

        $code = trim($_POST['code'] ?? '');
        if (!Totp::verify($user['totp_secret'], $code)) {
            $this->recordAttempt('2fa');
            sleep(1);
            $_SESSION['2fa_error'] = 'Invalid code';
            header('Location: /admin/verify-2fa');
            exit;
        }

        AdminAuth::login($userId);
        $_SESSION['admin_email'] = (string)($user['email'] ?? '');
        $_SESSION['admin_role'] = (string)($user['role'] ?? 'operator');
        $this->pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
            ->execute([$userId]);
        $this->audit->log('login.success', 'auth', $userId, 'email: ' . ($user['email'] ?? '') . ' (2FA)');
        header('Location: /admin/dashboard');
        exit;
    }

    public function logout(): void
    {
        $this->audit->log('logout', 'auth');
        AdminAuth::logout();
        header('Location: /admin/login');
        exit;
    }
}
