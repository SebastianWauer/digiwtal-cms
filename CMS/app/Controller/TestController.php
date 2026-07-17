<?php
declare(strict_types=1);

namespace App\Controller;

final class TestController
{
    public function handle(): void
    {
        $user  = \admin_require_login();
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));

        $checks = [
            'PHP-Version'   => PHP_VERSION,
            'Datum/Uhrzeit' => date('d.m.Y H:i:s'),
            'Datenbank'     => \db_table_exists('users') ? 'Verbindung OK' : 'Keine Verbindung',
            'Session'       => session_status() === PHP_SESSION_ACTIVE ? 'Aktiv' : 'Inaktiv',
            'Eingeloggter User' => (string)($user['username'] ?? '—'),
        ];

        \admin_layout_begin([
            'title'    => 'Test',
            'theme'    => $theme,
            'active'   => '',
            'user'     => $user,
            'headline' => 'System-Test',
            'subtitle' => 'Alles in Ordnung?',
        ]);

        require __DIR__ . '/../Views/test.php';

        \admin_layout_end();
    }
}
