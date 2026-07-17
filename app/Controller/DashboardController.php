<?php
declare(strict_types=1);

namespace App\Controller;

final class DashboardController
{
    public function handle(): void
    {
        // Debug-Ausgabe nur in DEV (sonst Admin-Leaks vermeiden)
        $env = (string)\admin_env('APP_ENV', 'production');
        if (in_array(strtolower($env), ['dev', 'local', 'development'], true)) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        // Autoritativer Einstieg: liefert den eingeloggten User
        $user  = \admin_require_login();
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));

        $pdo = \db();

        // --- Pages Stats (DB-only, defensiv) ---
        $pageCount = 0;
        $publishedCount = 0;
        $lastDate = '—';

        $columnExists = static function (\PDO $pdo, string $table, string $column): bool {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = :t
                  AND column_name = :c
                LIMIT 1
            ");
            $stmt->execute([':t' => $table, ':c' => $column]);
            return (bool)$stmt->fetchColumn();
        };

        if (\db_table_exists('pages')) {
            $pageCount = (int)$pdo->query("SELECT COUNT(*) FROM pages WHERE is_deleted = 0")->fetchColumn();

            // "Veröffentlicht" = alles, was nicht gelöscht ist UND nicht draft (Entwurf)
            if ($columnExists($pdo, 'pages', 'status')) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM pages
                    WHERE is_deleted = 0
                      AND (status IS NULL OR status <> :draft)
                ");
                $stmt->execute([':draft' => 'draft']);
                $publishedCount = (int)$stmt->fetchColumn();
            } else {
                $publishedCount = $pageCount;
            }

            // Letzte Änderung (nicht gelöscht)
            if ($columnExists($pdo, 'pages', 'updated_at')) {
                $lastUpdated = $pdo->query("SELECT MAX(updated_at) FROM pages WHERE is_deleted = 0")->fetchColumn();
                if (is_string($lastUpdated) && $lastUpdated !== '') {
                    $ts = strtotime($lastUpdated);
                    if ($ts !== false && $ts > 0) {
                        $lastDate = date('d.m.Y H:i', $ts);
                    }
                }
            }
        }

        // --- Users Stats (DB-only, defensiv) ---
        $userCount = 0;
        if (\db_table_exists('users')) {
            // Einheitlich: nur verwaltbare, aktive CMS-User (nicht gelöscht, nicht gesperrt, kein System-User)
            $repo = new \App\Repositories\UserRepositoryDb($pdo);
            $userCount = $repo->countActiveEnabledNonSystem();
        }

        // View erwartet: $stats und $lastDate (separat)
        $stats = [
            'pageCount'      => $pageCount,
            'publishedCount' => $publishedCount,
            'userCount'      => $userCount,
        ];

        \admin_layout_begin([
            'title'    => 'Dashboard',
            'theme'    => $theme,
            'active'   => 'dashboard',
            'user'     => $user,
            'next'     => '/',
            'pageCss'  => 'dashboard',
            'headline' => 'Dashboard',
            'subtitle' => 'Willkommen, ' . (string)($user['username'] ?? ''),
        ]);

        require __DIR__ . '/../Views/dashboard.php';

        \admin_layout_end();
    }
}
