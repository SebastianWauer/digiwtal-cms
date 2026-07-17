<?php
declare(strict_types=1);

namespace App\Controller;

use App\Setup\MigrationsRunner;

final class MigrateController
{
    private function resolveMigrationsDir(): string
    {
        $candidates = [
            realpath(__DIR__ . '/../migrations'),     // app/migrations
            realpath(__DIR__ . '/../../migrations'),  // /migrations (Projektroot)
        ];

        foreach ($candidates as $dir) {
            if (is_string($dir) && $dir !== '' && is_dir($dir)) {
                return $dir;
            }
        }

        throw new \RuntimeException('Migrations directory not found.');
    }

    private function render(array $user, ?array $result = null): void
    {
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));

        // View erwartet $flash und $result
        $flash = null;

        \admin_layout_begin([
            'title'    => 'Migrationen',
            'theme'    => $theme,
            'active'   => 'migrate',
            'user'     => $user,
            'next'     => '/migrate',
            'headline' => 'Migrationen',
            'subtitle' => 'Nur SystemUser.',
        ]);

        require __DIR__ . '/../Views/migrate.php';

        \admin_layout_end();
    }

    public function show(): void
    {
        // Migrationen strikt admin-only (nicht delegierbar)
        $user = \admin_require_system_user();
        $this->render($user, null);
    }

    public function run(): void
    {
        $user = \admin_require_system_user();

        // POST erzwingen
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }

        // CSRF prüfen
        \admin_verify_csrf();

        $pdo = \admin_pdo();
        $dir = $this->resolveMigrationsDir();

        $result = MigrationsRunner::run($pdo, $dir);

        // KEIN Redirect -> sonst verliert man $result
        $this->render($user, is_array($result) ? $result : null);
    }

    public function baseline(): void
    {
        $user = \admin_require_system_user();

        // POST erzwingen
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }

        // CSRF prüfen
        \admin_verify_csrf();

        $pdo = \admin_pdo();
        $dir = $this->resolveMigrationsDir();

        $result = MigrationsRunner::baseline($pdo, $dir);

        // KEIN Redirect -> sonst verliert man $result
        $this->render($user, is_array($result) ? $result : null);
    }
}
