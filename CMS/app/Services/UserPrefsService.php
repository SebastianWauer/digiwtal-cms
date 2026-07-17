<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Paths;
use App\Repositories\UserPrefRepositoryDb;

final class UserPrefsService
{
    /**
     * @return array{theme:string,next:string}
     */
    public function handleThemePost(): array
    {
        $theme = (string)($_POST['theme'] ?? '');
        $theme = ($theme === 'dark') ? 'dark' : 'light';

        $next = (string)($_POST['next'] ?? Paths::DASHBOARD);
        $next = Paths::safeInternal($next, Paths::DASHBOARD);

        return ['theme' => $theme, 'next' => $next];
    }

    /**
     * Erwartete POST-Felder:
     * - pref_key / key (oder legacy: theme)
     * - pref_value / value (oder legacy: theme)
     * - next (optional)
     *
     * @return array{key:string,value:string,next:string}
     */
    public function handlePrefsPost(): array
    {
        // Legacy-Bridge: Theme-Form sendet "theme"
        if (array_key_exists('theme', $_POST)) {
            $theme = (string)($_POST['theme'] ?? '');
            $theme = ($theme === 'dark') ? 'dark' : 'light';

            $next = (string)($_POST['next'] ?? Paths::DASHBOARD);
            $next = Paths::safeInternal($next, Paths::DASHBOARD);

            return ['key' => 'theme', 'value' => $theme, 'next' => $next];
        }

        $key = (string)($_POST['pref_key'] ?? ($_POST['key'] ?? ''));
        $key = trim($key);

        $raw = (string)($_POST['pref_value'] ?? ($_POST['value'] ?? ''));

        $value = '';

        if ($key === 'ui.sidebar_collapsed') {
            $value = ($raw === '1') ? '1' : '0';
        } elseif ($key === 'media.view') {
            $raw = strtolower(trim($raw));
            $value = ($raw === 'list') ? 'list' : 'grid';
        } elseif ($key === 'theme') {
            $raw = strtolower(trim($raw));
            $value = ($raw === 'dark') ? 'dark' : 'light';
        } else {
            $value = '';
        }

        $next = (string)($_POST['next'] ?? Paths::DASHBOARD);
        $next = Paths::safeInternal($next, Paths::DASHBOARD);

        return ['key' => $key, 'value' => $value, 'next' => $next];
    }

    /**
     * Orchestriert Pref-Speichern zentral.
     *
     * @return array{ok:bool,key?:string,value?:string,next?:string}
     */
    public function saveFromPost(int $userId): array
    {
        $data = $this->handlePrefsPost();

        $key   = (string)($data['key'] ?? '');
        $value = (string)($data['value'] ?? '');

        if ($key === '' || $value === '') {
            return ['ok' => false];
        }

        $repo = new UserPrefRepositoryDb();
        $repo->set($userId, $key, $value);

        return [
            'ok'    => true,
            'key'   => $key,
            'value' => $value,
            'next'  => (string)($data['next'] ?? Paths::DASHBOARD),
        ];
    }
}
