<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Setup;
use App\Repositories\SiteSettingsRepositoryDb;
use App\Repositories\UserRepositoryDb;
use App\Repositories\RoleRepositoryDb;
use App\Setup\MigrationsRunner;

final class SetupController
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function pdo(): \PDO
    {
        return db();
    }

    /**
     * Gibt 404 zurück wenn CMS bereits installiert ist.
     * DB-Fehler → Setup erlaubt (DB könnte frisch sein).
     */
    private function guardNotInstalled(): void
    {
        try {
            if (Setup::isInstalled($this->pdo())) {
                http_response_code(404);
                echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>404</title></head>'
                   . '<body><h1>404 – Nicht gefunden</h1></body></html>';
                exit;
            }
        } catch (\Throwable) {
            // DB nicht erreichbar → Setup darf laufen
        }
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        $s = $_SESSION['setup_state'] ?? null;
        return is_array($s) ? $s : [];
    }

    /** @param array<string,mixed> $data */
    private function setState(array $data): void
    {
        $_SESSION['setup_state'] = array_merge($this->state(), $data);
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    // ── Step 1: DB-Check ─────────────────────────────────────────────────────

    public function step1(): void
    {
        $this->guardNotInstalled();

        $cfg   = db_config();
        $error = (string)($this->state()['db_error'] ?? '');

        // Session-Error nach dem Lesen löschen, damit Reload ihn nicht nochmal zeigt
        if ($error !== '') {
            $state = $this->state();
            unset($state['db_error']);
            $_SESSION['setup_state'] = $state;
        }

        require __DIR__ . '/../Views/setup_step1.php';
    }

    public function step1Post(): void
    {
        $this->guardNotInstalled();
        admin_verify_csrf();

        try {
            $pdo = $this->pdo();
            $pdo->query('SELECT 1'); // Verbindungstest

            // Prüfen ob schema_migrations bereits existiert (Teilinstallation)
            $r       = $pdo->query("SHOW TABLES LIKE 'schema_migrations'");
            $partial = $r && $r->rowCount() > 0;

            $this->setState(['db_ok' => true, 'db_partial' => $partial]);
            $this->redirect('/setup/step2');

        } catch (\Throwable $e) {
            $this->setState(['db_ok' => false, 'db_error' => $e->getMessage()]);
            $this->redirect('/setup');
        }
    }

    // ── Step 2: Migrationen ───────────────────────────────────────────────────

    public function step2(): void
    {
        $this->guardNotInstalled();

        if (!($this->state()['db_ok'] ?? false)) {
            $this->redirect('/setup');
        }

        $migrationsDir = realpath(__DIR__ . '/../../migrations') ?: '';
        $files         = $migrationsDir !== '' ? (glob($migrationsDir . '/*.sql') ?: []) : [];
        $migrationCount = count($files);

        $errors  = (array)($this->state()['migration_errors'] ?? []);
        $applied = (int)($this->state()['migrations_applied'] ?? 0);

        require __DIR__ . '/../Views/setup_step2.php';
    }

    public function step2Post(): void
    {
        $this->guardNotInstalled();
        admin_verify_csrf();

        if (!($this->state()['db_ok'] ?? false)) {
            $this->redirect('/setup');
        }

        try {
            $pdo  = $this->pdo();
            $dir  = realpath(__DIR__ . '/../../migrations') ?: '';

            $result = MigrationsRunner::run($pdo, $dir);

            $ok      = (bool)($result['ok'] ?? false);
            $applied = (int)($result['ran'] ?? 0);
            $log     = (array)($result['log'] ?? []);

            // Fehler aus Log filtern (Zeilen die mit 'ERROR' oder 'Error' beginnen)
            $errors = array_values(array_filter($log, fn(string $l): bool =>
                stripos($l, 'error') !== false || stripos($l, 'fehler') !== false
            ));

            $this->setState([
                'migrations_ok'      => $ok,
                'migrations_applied' => $applied,
                'migration_errors'   => $errors,
            ]);

            if ($ok) {
                $this->redirect('/setup/step3');
            } else {
                $this->redirect('/setup/step2');
            }

        } catch (\Throwable $e) {
            $this->setState([
                'migrations_ok'    => false,
                'migration_errors' => [$e->getMessage()],
            ]);
            $this->redirect('/setup/step2');
        }
    }

    // ── Step 3: Admin-Account + Site-Settings ────────────────────────────────

    public function step3(): void
    {
        $this->guardNotInstalled();

        if (!($this->state()['migrations_ok'] ?? false)) {
            $this->redirect('/setup/step2');
        }

        $errors = (array)($this->state()['finish_errors'] ?? []);
        $old    = (array)($this->state()['finish_old']    ?? []);

        // Nach Anzeige löschen
        if ($errors !== []) {
            $s = $this->state();
            unset($s['finish_errors'], $s['finish_old']);
            $_SESSION['setup_state'] = $s;
        }

        require __DIR__ . '/../Views/setup_step3.php';
    }

    // ── Finish ────────────────────────────────────────────────────────────────

    public function finish(): void
    {
        $this->guardNotInstalled();
        admin_verify_csrf();

        if (!($this->state()['migrations_ok'] ?? false)) {
            $this->redirect('/setup/step2');
        }

        // ── Eingaben ──────────────────────────────────────────────────────────
        $email     = trim((string)($_POST['admin_email']            ?? ''));
        $password  = (string)($_POST['admin_password']              ?? '');
        $confirm   = (string)($_POST['admin_password_confirm']      ?? '');
        $siteName  = trim((string)($_POST['site_name']              ?? ''));
        $canonical = rtrim(trim((string)($_POST['canonical_base']   ?? '')), '/');

        // ── Validierung ───────────────────────────────────────────────────────
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        }
        if (mb_strlen($password) < 10) {
            $errors[] = 'Passwort muss mindestens 10 Zeichen lang sein.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwörter stimmen nicht überein.';
        }
        if ($canonical !== '' && !preg_match('#^https?://#', $canonical)) {
            $errors[] = 'Canonical-Base-URL muss mit https:// oder http:// beginnen.';
        }

        if ($errors !== []) {
            $this->setState([
                'finish_errors' => $errors,
                'finish_old'    => [
                    'admin_email'    => $email,
                    'site_name'      => $siteName,
                    'canonical_base' => $canonical,
                ],
            ]);
            $this->redirect('/setup/step3');
        }

        // ── Admin-User anlegen ────────────────────────────────────────────────
        try {
            $pdo      = $this->pdo();
            $userRepo = new UserRepositoryDb($pdo);
            $roleRepo = new RoleRepositoryDb($pdo);

            // Username aus E-Mail generieren (Teil vor @, bereinigt)
            $username = preg_replace('/[^a-z0-9_]/i', '', explode('@', $email)[0]) ?: 'admin';
            if (mb_strtolower($username) === 'admin') {
                $username = 'cms_admin';
            }

            // Existiert bereits ein User mit dieser E-Mail?
            $existingUser = $userRepo->findByEmail($email);
            $userId       = null;

            if ($existingUser === null) {
                $result = $userRepo->save(null, $username, $username, $email, true, $password);
                if (!($result['ok'] ?? false)) {
                    throw new \RuntimeException(
                        (string)($result['flash']['msg'] ?? 'User konnte nicht angelegt werden.')
                    );
                }
                $userId = (int)($result['id'] ?? 0);
            } else {
                $userId = (int)($existingUser['id'] ?? 0);
            }

            if ($userId <= 0) {
                throw new \RuntimeException('User-ID ungültig nach Insert.');
            }

            // ── Admin-Rolle sicherstellen und zuweisen ────────────────────────
            // Rolle 'admin' anlegen falls nicht vorhanden
            $adminRole = $roleRepo->findByKey('admin');
            if ($adminRole === null) {
                $roleRepo->save(null, 'admin', 'Administrator');
                $adminRole = $roleRepo->findByKey('admin');
            }
            $roleId = (int)($adminRole['id'] ?? 0);

            if ($roleId <= 0) {
                throw new \RuntimeException('Admin-Rolle konnte nicht gefunden/erstellt werden.');
            }

            // user_roles direkt (UserRoleRepositoryDb filtert System-Rollen heraus)
            $st = $pdo->prepare(
                "INSERT INTO user_roles (user_id, role_id)
                 VALUES (:uid, :rid)
                 ON DUPLICATE KEY UPDATE user_id = user_id"
            );
            $st->execute([':uid' => $userId, ':rid' => $roleId]);

            // ── Site-Settings ──────────────────────────────────────────────────
            $settingsRepo = new SiteSettingsRepositoryDb($pdo);

            if ($siteName !== '') {
                $settingsRepo->set('site_name', $siteName);
            }
            if ($canonical !== '') {
                $settingsRepo->set('seo_canonical_base', $canonical);
            }
            $settingsRepo->set('seo_robots_default', 'index,follow');

            // ── Abschluss ──────────────────────────────────────────────────────
            Setup::markInstalled($pdo);

            unset($_SESSION['setup_state']);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Setup abgeschlossen. Willkommen!'];
            $this->redirect('/login');

        } catch (\Throwable $e) {
            $this->setState([
                'finish_errors' => ['Fehler beim Abschluss: ' . $e->getMessage()],
                'finish_old'    => [
                    'admin_email'    => $email,
                    'site_name'      => $siteName,
                    'canonical_base' => $canonical,
                ],
            ]);
            $this->redirect('/setup/step3');
        }
    }

    // ── Debug: Status ─────────────────────────────────────────────────────────

    public function status(): void
    {
        $this->guardNotInstalled();

        $cfg  = db_config();
        $dir  = realpath(__DIR__ . '/../../migrations') ?: '';
        $files = $dir !== '' ? (glob($dir . '/*.sql') ?: []) : [];

        $payload = [
            'setup_allowed' => true,
            'db_host'       => (string)($cfg['host'] ?? ''),
            'db_name'       => (string)($cfg['name'] ?? $cfg['database'] ?? ''),
            'migrations_dir'=> $dir,
            'migration_files' => count($files),
            'session_state' => $this->state(),
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
