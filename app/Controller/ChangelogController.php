<?php
declare(strict_types=1);

namespace App\Controller;

final class ChangelogController
{
    private const PER_PAGE = 50;

    public function show(): void
    {
        $user  = \admin_require_login();
        $theme = function_exists('admin_theme_for_user')
            ? \admin_theme_for_user((int)($user['id'] ?? 0))
            : 'dark';

        $pdo    = \admin_pdo();
        $page   = max(1, (int)($_GET['page'] ?? 1));

        try {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM changelogs")->fetchColumn();
        } catch (\Throwable) {
            $total = 0;
        }

        $pages  = $total > 0 ? (int)ceil($total / self::PER_PAGE) : 1;
        $page   = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $entries = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT id, version, type, module_key, content_md, released_at
                 FROM changelogs
                 ORDER BY released_at DESC, id DESC
                 LIMIT " . self::PER_PAGE . " OFFSET " . $offset
            );
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $entries = is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            $entries = [];
        }

        \admin_layout_begin([
            'title'    => 'Changelog',
            'theme'    => $theme,
            'active'   => 'changelog',
            'user'     => $user,
            'next'     => '/changelog',
            'headline' => 'Changelog',
            'subtitle' => 'Versions- und Modulupdates.',
        ]);

        require __DIR__ . '/../Views/changelog.php';

        \admin_layout_end();
    }
}
