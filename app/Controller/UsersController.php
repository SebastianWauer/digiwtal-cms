<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\UserRepositoryDb;
use App\Repositories\RoleRepositoryDb;
use App\Repositories\UserRoleRepositoryDb;

final class UsersController
{
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)$user['id']);
        $pdo   = \admin_pdo();

        $users = new UserRepositoryDb($pdo);
        $roles = new RoleRepositoryDb($pdo);
        $userRoles = new UserRoleRepositoryDb($pdo);

        return [$user, $theme, $pdo, $users, $roles, $userRoles];
    }

    public function index(): void
    {
        $user = \admin_require_perm('users.view');
        [$user, $theme, $_pdo, $users, $_roles, $userRoles] = $this->deps($user);

        $rows = $users->listActive();
        $deletedCount = $users->countDeleted(); // ✅ View erwartet das
        $flash = null;

        $currentUserId = (int)($user['id'] ?? 0);

        $ids = [];
        foreach ($rows as $r) $ids[] = (int)($r['id'] ?? 0);

        $roleMap = $userRoles->roleNamesForUsers($ids);
        foreach ($rows as $i => $r) {
            $uid = (int)($r['id'] ?? 0);
            $rows[$i]['roles'] = $roleMap[$uid] ?? [];
        }

        // UI-Delete-Guard: nicht selbst / nicht letzter Admin
        $adminCount = $users->countActiveAdmins();
        $adminIdsInList = $userRoles->adminUserIdsForUsers($ids);
        $adminIdsSet = array_fill_keys($adminIdsInList, true);
        $isLastAdmin = ($adminCount <= 1);

        foreach ($rows as $i => $r) {
            $uid = (int)($r['id'] ?? 0);
            $canDelete = (function_exists('admin_can') && admin_can('users.delete'));

            if ($uid === $currentUserId) $canDelete = false;
            if ($canDelete && $isLastAdmin && isset($adminIdsSet[$uid])) $canDelete = false;

            $rows[$i]['can_delete'] = $canDelete ? 1 : 0;
        }

        \admin_layout_begin([
            'title'    => 'Benutzer',
            'theme'    => $theme,
            'active'   => 'users',
            'user'     => $user,
            'next'     => '/users',
            'pageCss'  => 'pages-list',
            'headline' => 'Benutzer',
            'subtitle' => 'Benutzer anlegen, bearbeiten oder löschen (Soft-Delete).',
        ]);

        require __DIR__ . '/../Views/users_list.php';
        \admin_layout_end();
    }

    public function deleted(): void
    {
        $user = \admin_require_perm('users.view');
        [$user, $theme, $_pdo, $users] = $this->deps($user);

        $rows = $users->listDeleted();
        $deletedCount = $users->countDeleted();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'Gelöschte Benutzer',
            'theme'    => $theme,
            'active'   => 'users',
            'user'     => $user,
            'next'     => '/users',
            'pageCss'  => 'pages-list',
            'headline' => 'Benutzer',
            'subtitle' => 'Gelöschte Benutzer (Restore).',
        ]);

        require __DIR__ . '/../Views/users_deleted.php';
        \admin_layout_end();
    }

    public function edit(): void
    {
        $user = \admin_require_perm('users.view');
        [$user, $theme, $_pdo, $users, $roles, $userRoles] = $this->deps($user);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id > 0) {
            \admin_require_perm('users.edit');

            // System-User (Admin) ist vollständig isoliert
            if ($users->isSystemUserAnyState($id)) {
                http_response_code(404);
                echo 'Not Found';
                return;
            }
        } else {
            \admin_require_perm('users.create');
        }

        $row = $id > 0 ? $users->findById($id) : null;

        if (!is_array($row)) {
            $row = [
                'id' => 0, 'username' => '', 'name' => '', 'email' => null,
                'enabled' => 1, 'is_deleted' => 0
            ];
        }

        if (!empty($row['is_deleted'])) {
            header('Location: /users/deleted');
            exit;
        }

        $allRoles = $roles->listActive();
        $selectedRoleIds = $id > 0 ? $userRoles->roleIdsForUser($id) : [];

        $currentUserId = (int)($user['id'] ?? 0);
        $isSelf = ($id > 0 && $id === $currentUserId);

        // UI-Guards (Backend bleibt autoritativ: /users/delete und /users/save prüfen zusätzlich)
        $canDelete = (function_exists('admin_can') && admin_can('users.delete'));
        if ($id <= 0) $canDelete = false;
        if ($isSelf) $canDelete = false;

        // Letzten Admin nicht löschbar machen
        if ($canDelete && $id > 0 && $users->isAdminUser($id) && $users->countActiveAdmins() <= 1) {
            $canDelete = false;
        }

        // Passwort anderer User nur, wenn Berechtigung gesetzt ist
        $canResetOtherPw = (function_exists('admin_can') && admin_can('users.password.reset'));
        if ($id <= 0) $canResetOtherPw = true; // beim Anlegen darf Passwort gesetzt werden
        if ($isSelf) $canResetOtherPw = true;  // eigenes Passwort darf (sofern Feld angezeigt wird)

        $canEditRoles = (function_exists('admin_can') && admin_can('users.roles.edit.other'));
        if ($isSelf) $canEditRoles = false;

        \admin_layout_begin([
            'title'    => 'Benutzer bearbeiten',
            'theme'    => $theme,
            'active'   => 'users',
            'user'     => $user,
            'next'     => '/users',
            'pageCss'  => 'pages-edit',
            'headline' => 'Benutzer',
            'subtitle' => 'Passwort wird nur gesetzt, wenn du eins eingibst.',
        ]);

        require __DIR__ . '/../Views/users_edit.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('users.view');
        [$user, $theme, $_pdo, $users, $roles, $userRoles] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ CSRF prüfen (zentral)
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            \admin_require_perm('users.edit');

            if ($users->isSystemUserAnyState($id)) {
                http_response_code(404);
                echo 'Not Found';
                return;
            }
        } else {
            \admin_require_perm('users.create');
        }

        $currentUserId = (int)($user['id'] ?? 0);
        $isSelf = ($id > 0 && $id === $currentUserId);

        // View sendet new_password; alte Feldnamen (password) weiterhin akzeptieren
        $pw = trim((string)($_POST['new_password'] ?? ($_POST['password'] ?? '')));
        $hasPw = ($pw !== '');

        if ($hasPw && $id > 0 && $id !== $currentUserId) {
            \admin_require_perm('users.password.reset');
        }

        $username = (string)($_POST['username'] ?? '');
        $name     = (string)($_POST['name'] ?? '');
        $emailRaw = (string)($_POST['email'] ?? '');
        $email    = trim($emailRaw) !== '' ? trim($emailRaw) : null;

        $enabled  = !empty($_POST['enabled']);
        $newPassword = $hasPw ? $pw : null;

        $res = $users->save($id > 0 ? $id : null, $username, $name, $email, $enabled, $newPassword);
        if (empty($res['ok'])) {
            $flash = $res['flash'] ?? ['type' => 'error', 'msg' => 'Fehler'];

            $id2 = $id;
            $row = $id2 > 0 ? $users->findById($id2) : null;
            if (!is_array($row)) {
                $row = [
                    'id' => 0,
                    'username' => $username,
                    'name' => $name,
                    'email' => $email,
                    'enabled' => $enabled ? 1 : 0,
                    'is_deleted' => 0
                ];
            }

            $allRoles = $roles->listActive();
            $selectedRoleIds = $id2 > 0 ? $userRoles->roleIdsForUser($id2) : [];

            // Flags für View (auch im Fehlerfall konsistent)
            $canDelete = (function_exists('admin_can') && admin_can('users.delete'));
            if ($id2 <= 0) $canDelete = false;
            if ($isSelf) $canDelete = false;
            if ($canDelete && $id2 > 0 && $users->isAdminUser($id2) && $users->countActiveAdmins() <= 1) {
                $canDelete = false;
            }

            $canResetOtherPw = (function_exists('admin_can') && admin_can('users.password.reset'));
            if ($id2 <= 0) $canResetOtherPw = true;
            if ($isSelf) $canResetOtherPw = true;

            $canEditRoles = (function_exists('admin_can') && admin_can('users.roles.edit.other'));
            if ($isSelf) $canEditRoles = false;

            \admin_layout_begin([
                'title'    => 'Benutzer bearbeiten',
                'theme'    => $theme,
                'active'   => 'users',
                'user'     => $user,
                'next'     => '/users',
                'pageCss'  => 'pages-edit',
                'headline' => 'Benutzer',
                'subtitle' => 'Passwort wird nur gesetzt, wenn du eins eingibst.',
            ]);

            require __DIR__ . '/../Views/users_edit.php';
            \admin_layout_end();
            return;
        }

        $id2 = (int)($res['id'] ?? $id);

        $canEditRoles = (function_exists('admin_can') && admin_can('users.roles.edit.other'));
        if ($isSelf) $canEditRoles = false;

        if ($id2 > 0 && $canEditRoles) {
            $roleIds = $_POST['roles'] ?? [];
            if (!is_array($roleIds)) $roleIds = [];
            $roleIds = array_map('intval', $roleIds);

            $existing = $roles->listActive();
            $allowed = [];
            foreach ($existing as $r) $allowed[] = (int)($r['id'] ?? 0);

            $roleIds = array_values(array_filter($roleIds, fn($x) => $x > 0 && in_array($x, $allowed, true)));
            $userRoles->setRoles($id2, $roleIds);
        }

        $row = $id2 > 0 ? $users->findById($id2) : null;
        if (!is_array($row)) {
            $row = [
                'id' => 0,
                'username' => $username,
                'name' => $name,
                'email' => $email,
                'enabled' => $enabled ? 1 : 0,
                'is_deleted' => 0
            ];
        }

        $flash = $res['flash'] ?? ['type' => 'ok', 'msg' => 'Gespeichert.'];
        $allRoles = $roles->listActive();
        $selectedRoleIds = $id2 > 0 ? $userRoles->roleIdsForUser($id2) : [];

        // Flags für View (nach erfolgreichem Speichern)
        $isSelf = ($id2 > 0 && $id2 === $currentUserId);

        $canDelete = (function_exists('admin_can') && admin_can('users.delete'));
        if ($id2 <= 0) $canDelete = false;
        if ($isSelf) $canDelete = false;
        if ($canDelete && $id2 > 0 && $users->isAdminUser($id2) && $users->countActiveAdmins() <= 1) {
            $canDelete = false;
        }

        $canResetOtherPw = (function_exists('admin_can') && admin_can('users.password.reset'));
        if ($id2 <= 0) $canResetOtherPw = true;
        if ($isSelf) $canResetOtherPw = true;

        $canEditRoles = (function_exists('admin_can') && admin_can('users.roles.edit.other'));
        if ($isSelf) $canEditRoles = false;

        \admin_layout_begin([
            'title'    => 'Benutzer bearbeiten',
            'theme'    => $theme,
            'active'   => 'users',
            'user'     => $user,
            'next'     => '/users',
            'pageCss'  => 'pages-edit',
            'headline' => 'Benutzer',
            'subtitle' => 'Passwort wird nur gesetzt, wenn du eins eingibst.',
        ]);

        require __DIR__ . '/../Views/users_edit.php';
        \admin_layout_end();
    }

    public function delete(): void
    {
        $user = \admin_require_perm('users.delete');
        [$user, $_theme, $_pdo, $users] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ CSRF prüfen (zentral)
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /users');
            exit;
        }

        if ($users->isSystemUserAnyState($id)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $currentUserId = (int)($user['id'] ?? 0);
        if ($id === $currentUserId) {
            http_response_code(400);
            echo 'Du kannst dich nicht selbst löschen.';
            return;
        }

        $targetIsAdmin = $users->isAdminUser($id);
        if ($targetIsAdmin && $users->countActiveAdmins() <= 1) {
            http_response_code(400);
            echo 'Der letzte Administrator kann nicht gelöscht werden.';
            return;
        }

        $users->softDelete($id);

        header('Location: /users');
        exit;
    }

    public function restore(): void
    {
        $user = \admin_require_perm('users.delete');
        [$user, $_theme, $_pdo, $users] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        // ✅ CSRF prüfen (zentral)
        \admin_verify_csrf();
        
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0 && $users->isSystemUserAnyState($id)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        if ($id > 0) $users->restore($id);

        header('Location: /users');
        exit;
    }
    public function purge(): void
    {
        $user = \admin_require_perm('users.delete');
        [$user, $_theme, $_pdo, $users] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $n = $users->purgeDeleted();
        $_SESSION['flash'] = ($n > 0)
            ? ['type' => 'success', 'msg' => 'Papierkorb geleert (' . $n . ').']
            : ['type' => 'success', 'msg' => 'Papierkorb ist bereits leer.'];

        header('Location: /users/deleted');
        exit;
    }
}
