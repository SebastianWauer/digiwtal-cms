<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Env;
use App\Services\AuthService;

final class PasswordResetController
{
    private function extractTokenFromPath(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (!is_string($path)) return '';
        if (preg_match('#^/password-reset/([A-Fa-f0-9]{64})$#', rtrim($path, '/'), $m)) {
            return (string)$m[1];
        }
        return '';
    }

    public function showRequest(): void
    {
        $auth = new AuthService();
        $auth->redirectIfAlreadyLoggedIn();

        $flash = null;
        require __DIR__ . '/../Views/password_reset_request.php';
    }

    public function submitRequest(): void
    {
        $auth = new AuthService();
        $auth->redirectIfAlreadyLoggedIn();
        \admin_verify_csrf();

        $email = trim((string)($_POST['email'] ?? ''));
        $flash = ['type' => 'ok', 'msg' => 'Wenn ein Konto mit dieser E-Mail existiert, wurde ein Reset-Link versendet.'];

        if ($email !== '') {
            try {
                $pdo = \admin_pdo();
                $stmt = $pdo->prepare("
                    SELECT id, email
                    FROM users
                    WHERE email = :email
                      AND enabled = 1
                      AND is_deleted = 0
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (is_array($user) && (int)($user['id'] ?? 0) > 0) {
                    $tokenPlain = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $tokenPlain);

                    $ins = $pdo->prepare("
                        INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                        VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
                    ");
                    $ins->execute([
                        ':uid' => (int)$user['id'],
                        ':hash' => $tokenHash,
                    ]);

                    $this->sendResetMail((string)($user['email'] ?? ''), $tokenPlain);
                }
            } catch (\Throwable) {
                // nicht leaken, gleiche Antwort wie bei Erfolg
            }
        }

        require __DIR__ . '/../Views/password_reset_request.php';
    }

    public function showResetForm(): void
    {
        $auth = new AuthService();
        $auth->redirectIfAlreadyLoggedIn();

        $token = $this->extractTokenFromPath();
        if ($token === '') {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $flash = null;
        require __DIR__ . '/../Views/password_reset_form.php';
    }

    public function submitResetForm(): void
    {
        $auth = new AuthService();
        $auth->redirectIfAlreadyLoggedIn();
        \admin_verify_csrf();

        $token = $this->extractTokenFromPath();
        if ($token === '') {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password_confirm'] ?? '');
        $flash = null;

        if ($password === '' || strlen($password) < 8) {
            $flash = ['type' => 'error', 'msg' => 'Passwort muss mindestens 8 Zeichen lang sein.'];
            require __DIR__ . '/../Views/password_reset_form.php';
            return;
        }
        if (!hash_equals($password, $password2)) {
            $flash = ['type' => 'error', 'msg' => 'Passwort-Bestätigung stimmt nicht überein.'];
            require __DIR__ . '/../Views/password_reset_form.php';
            return;
        }

        try {
            $pdo = \admin_pdo();
            $tokenHash = hash('sha256', $token);

            $sel = $pdo->prepare("
                SELECT id, user_id
                FROM password_resets
                WHERE token_hash = :hash
                  AND used_at IS NULL
                  AND expires_at >= NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $sel->execute([':hash' => $tokenHash]);
            $row = $sel->fetch();
            if (!is_array($row) || (int)($row['user_id'] ?? 0) <= 0) {
                $flash = ['type' => 'error', 'msg' => 'Token ungültig oder abgelaufen.'];
                require __DIR__ . '/../Views/password_reset_form.php';
                return;
            }

            $pdo->beginTransaction();
            $updUser = $pdo->prepare("
                UPDATE users
                SET password_hash = :hash, updated_at = NOW()
                WHERE id = :uid
                LIMIT 1
            ");
            $updUser->execute([
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
                ':uid' => (int)$row['user_id'],
            ]);

            $updReset = $pdo->prepare("
                UPDATE password_resets
                SET used_at = NOW()
                WHERE id = :id
                LIMIT 1
            ");
            $updReset->execute([':id' => (int)$row['id']]);
            $pdo->commit();

            $flash = ['type' => 'ok', 'msg' => 'Passwort wurde aktualisiert. Du kannst dich jetzt einloggen.'];
            require __DIR__ . '/../Views/password_reset_request.php';
            return;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $flash = ['type' => 'error', 'msg' => 'Reset fehlgeschlagen. Bitte erneut versuchen.'];
            require __DIR__ . '/../Views/password_reset_form.php';
            return;
        }
    }

    private function sendResetMail(string $to, string $token): void
    {
        $to = trim($to);
        if ($to === '') return;

        $base = $this->detectRequestBaseUrl();
        if ($base === '') {
            $base = rtrim(trim((string)Env::get('APP_URL', '')), '/');
        }
        if ($base === '') {
            $base = 'http://localhost';
        }

        $link = $base . '/password-reset/' . strtolower($token);
        $subject = 'Passwort zurücksetzen';
        $body = "Hallo,\n\nfür dein CMS-Konto wurde ein Passwort-Reset angefordert.\n\n";
        $body .= "Link (gültig 1 Stunde):\n" . $link . "\n\n";
        $body .= "Wenn du das nicht warst, kannst du diese E-Mail ignorieren.\n";

        $from = trim((string)Env::get('MAIL_FROM', 'noreply@localhost'));
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from,
        ];

        @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function detectRequestBaseUrl(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }

        $https = (string)($_SERVER['HTTPS'] ?? '');
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $scheme = ($forwardedProto === 'https' || ($forwardedProto === '' && $https !== '' && $https !== 'off')) ? 'https' : 'http';

        $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = is_string($uriPath) ? $uriPath : '/';
        $path = '/' . ltrim($path, '/');

        $prefix = '';
        $markerPos = strpos($path, '/password-reset');
        if ($markerPos !== false) {
            $prefix = rtrim(substr($path, 0, $markerPos), '/');
        }

        return $scheme . '://' . $host . $prefix;
    }
}
