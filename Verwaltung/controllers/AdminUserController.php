<?php
declare(strict_types=1);

class AdminUserController
{
    private const TOTP_SETUP_KEY = 'admin_user_totp_setup';

    public function __construct(
        private AdminUserRepository $userRepo,
        private AuditLogger $audit
    ) {}

    public function index(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $users = $this->userRepo->listAll();
        require __DIR__ . '/../views/admin_users/index.php';
    }

    public function show(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $pendingSecret = $this->getPendingSecret($userId);
        $setupSecret = $pendingSecret ?: (string)($user['totp_secret'] ?? '');
        $totpIssuer = 'DigiWTAL Verwaltung';
        $otpauthUri = $setupSecret !== ''
            ? Totp::buildOtpAuthUri($totpIssuer, (string)($user['email'] ?? ''), $setupSecret)
            : '';

        $success = $_SESSION['flash_success'] ?? null;
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        unset($_SESSION['flash_success'], $_SESSION['flash_errors']);

        $errors = is_array($flashErrors) ? $flashErrors : ($flashErrors !== null ? [(string)$flashErrors] : []);

        require __DIR__ . '/../views/admin_users/show.php';
    }

    public function create(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $errors = $_SESSION['flash_errors'] ?? null;
        unset($_SESSION['flash_errors']);
        require __DIR__ . '/../views/admin_users/create.php';
    }

    public function store(): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ungültige Anfrage.'];
            header('Location: /admin/admin-users/create');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'operator';
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige E-Mail-Adresse.';
        }
        if (strlen($password) < 12) {
            $errors[] = 'Passwort muss mindestens 12 Zeichen haben.';
        }
        if (!in_array($role, ['superadmin', 'operator'], true)) {
            $role = 'operator';
        }
        if ($this->userRepo->findByEmail($email) !== null) {
            $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            header('Location: /admin/admin-users/create');
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $newId = $this->userRepo->create($email, $hash, $role);

        $this->audit->log('admin_user.create', 'admin_user', $newId, "email: {$email}, role: {$role}");
        $_SESSION['flash_success'] = "Admin-Benutzer {$email} angelegt.";
        header('Location: /admin/admin-users');
        exit;
    }

    public function startTotpSetup(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ungültige Anfrage.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $_SESSION[self::TOTP_SETUP_KEY][$userId] = Totp::generateSecret();
        $this->audit->log('admin_user.totp.setup_start', 'admin_user', $userId, 'email: ' . (string)($user['email'] ?? ''));
        $_SESSION['flash_success'] = 'TOTP-Setup vorbereitet. Secret im Authenticator eintragen und danach mit dem 6-stelligen Code bestätigen.';
        header('Location: /admin/admin-users/' . $userId);
        exit;
    }

    public function verifyTotpSetup(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ungültige Anfrage.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $secret = $this->getPendingSecret($userId);
        if ($secret === null) {
            $_SESSION['flash_errors'] = ['Kein ausstehendes TOTP-Setup gefunden.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            $_SESSION['flash_errors'] = ['Bitte einen gültigen 6-stelligen TOTP-Code eingeben.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        if (!Totp::verify($secret, $code)) {
            $_SESSION['flash_errors'] = ['Der TOTP-Code ist ungültig.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $this->userRepo->updateTotpSecret($userId, $secret);
        unset($_SESSION[self::TOTP_SETUP_KEY][$userId]);

        $this->audit->log('admin_user.totp.enabled', 'admin_user', $userId, 'email: ' . (string)($user['email'] ?? ''));
        $_SESSION['flash_success'] = 'TOTP erfolgreich aktiviert.';
        header('Location: /admin/admin-users/' . $userId);
        exit;
    }

    public function resetPassword(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_errors'] = ['Ungültige Anfrage.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
            exit;
        }

        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        $errors = [];

        if (strlen($password) < 12) {
            $errors[] = 'Passwort muss mindestens 12 Zeichen haben.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwörter stimmen nicht überein.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if (!is_string($hash) || $hash === '') {
            $_SESSION['flash_errors'] = ['Passwort konnte nicht sicher gespeichert werden.'];
            header('Location: /admin/admin-users/' . $userId);
            exit;
        }

        $this->userRepo->updatePasswordHash($userId, $hash);
        $this->audit->log('admin_user.password_reset', 'admin_user', $userId, 'email: ' . (string)($user['email'] ?? ''));

        $_SESSION['flash_success'] = 'Passwort wurde erfolgreich zurückgesetzt.';
        header('Location: /admin/admin-users/' . $userId);
        exit;
    }

    public function qr(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            http_response_code(404);
            exit;
        }

        $secret = $this->getPendingSecret($userId) ?: (string)($user['totp_secret'] ?? '');
        if ($secret === '' || !function_exists('imagecreatetruecolor')) {
            http_response_code(404);
            exit;
        }

        $otpauthUri = Totp::buildOtpAuthUri('DigiWTAL Verwaltung', (string)($user['email'] ?? ''), $secret);
        $this->renderTotpCard($user, $secret, $otpauthUri);
    }

    public function delete(int $userId): void
    {
        AdminAuth::requireAuth();
        $this->requireSuperadmin();

        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            header('Location: /admin/admin-users');
            exit;
        }

        $currentId = (int)($_SESSION['admin_id'] ?? 0);
        if ($userId === $currentId) {
            $_SESSION['flash_errors'] = ['Du kannst dein eigenes Konto nicht löschen.'];
            header('Location: /admin/admin-users');
            exit;
        }

        $this->userRepo->delete($userId);
        $this->audit->log('admin_user.delete', 'admin_user', $userId);
        $_SESSION['flash_success'] = 'Admin-Benutzer gelöscht.';
        header('Location: /admin/admin-users');
        exit;
    }

    private function requireSuperadmin(): void
    {
        if (($_SESSION['admin_role'] ?? 'operator') !== 'superadmin') {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>'
                . '<body><main><h1>403 - Kein Zugriff</h1>'
                . '<p>Nur Superadmins haben Zugriff auf diesen Bereich.</p>'
                . '<p><a href="/admin/dashboard">Zurück zum Dashboard</a></p></main></body></html>';
            exit;
        }
    }

    private function getPendingSecret(int $userId): ?string
    {
        $secret = $_SESSION[self::TOTP_SETUP_KEY][$userId] ?? null;
        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function renderTotpCard(array $user, string $secret, string $otpauthUri): void
    {
        $width = 840;
        $height = 440;
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            http_response_code(500);
            exit;
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 24, 39);
        $blue = imagecolorallocate($image, 37, 99, 235);
        $gray = imagecolorallocate($image, 100, 116, 139);
        $pale = imagecolorallocate($image, 241, 245, 249);

        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        imagefilledrectangle($image, 24, 24, $width - 24, $height - 24, $pale);
        imagestring($image, 5, 44, 46, 'DigiWTAL Verwaltung - TOTP Setup', $black);
        imagestring($image, 4, 44, 88, 'Admin: ' . (string)($user['email'] ?? ''), $blue);
        imagestring($image, 3, 44, 128, 'Secret im Authenticator eintragen und mit dem Code bestaetigen.', $gray);
        imagestring($image, 5, 44, 176, 'Secret', $black);
        imagestring($image, 5, 44, 206, $secret, $black);
        imagestring($image, 5, 44, 254, 'otpauth://', $black);
        $uriLines = str_split($otpauthUri, 64);
        $lineY = 286;
        foreach ($uriLines as $line) {
            imagestring($image, 2, 44, $lineY, $line, $black);
            $lineY += 18;
        }
        imagestring($image, 3, 44, $height - 54, 'Grafik mit Secret und otpauth-Link fuer die manuelle TOTP-Einrichtung.', $gray);

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }
}
