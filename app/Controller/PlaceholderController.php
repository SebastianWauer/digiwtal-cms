<?php
declare(strict_types=1);

namespace App\Controller;

final class PlaceholderController
{
    private function render(string $active, string $title): void
    {
        $user  = \admin_require_login();
        $theme = \admin_theme_for_user((int)$user['id']);

        \admin_layout_begin([
            'title' => $title,
            'theme' => $theme,
            'active' => $active,
            'user' => $user,
            'next' => '/',
            'headline' => $title,
            'subtitle' => 'Dieses Modul ist noch nicht implementiert.',
        ]);

        echo '<div class="card"><p>Platzhalter.</p></div>';

        \admin_layout_end();
    }

    public function pages(): void { $this->render('pages', 'Seiten'); }
    public function media(): void { $this->render('media', 'Medien'); }
    public function users(): void { $this->render('users', 'Benutzer'); }
    public function settings(): void { $this->render('settings', 'Einstellungen'); }
}
