<?php
declare(strict_types=1);

namespace App\Controller;

final class RolesController
{
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)$user['id']);
        $pdo   = \admin_pdo();
        return [$user, $theme, $pdo];
    }

    private static function permissionImplications(): array
    {
        // Nur bestehende Keys aus migrations/012_permissions.sql
        return [
            'dashboard.view' => [],

            'pages.view'    => [],
            'pages.create'  => ['pages.view'],
            'pages.edit'    => ['pages.view'],
            'pages.delete'  => ['pages.view'],
            'pages.restore' => ['pages.view'],
            'pages.publish' => ['pages.view'],

            'users.view'           => [],
            'users.create'         => ['users.view'],
            'users.edit'           => ['users.view'],
            'users.delete'         => ['users.view'],
            'users.restore'        => ['users.view'],
            'users.password.reset' => ['users.edit', 'users.view'],

            'roles.view'             => [],
            'roles.create'           => ['roles.view'],
            'roles.edit'             => ['roles.view'],
            'roles.delete'           => ['roles.view'],
            'roles.permissions.edit' => ['roles.edit', 'roles.view'],

            // System (nicht über Rollen-UI vergebbar)
            'system.health.view' => [],
            'system.migrate.run' => [],

            'settings.view' => ['media.view'],
            'forms.view' => [],
            'news.view' => [],
            'news.create' => ['news.view'],
            'news.edit' => ['news.view'],
            'news.delete' => ['news.view'],
        ];
    }

    /** Keys die NIE per Rollen-UI vergeben werden dürfen */
    private static function forbiddenPermissionKeys(): array
    {
        return [
            'system.migrate.run', // Migrationen bleiben strikt Admin-only / nicht delegierbar
        ];
    }

    private function currentUserRoleKeys(int $userId, \PDO $pdo): array
    {
        $st = $pdo->prepare("
            SELECT r.`key`
            FROM roles r
            JOIN user_roles ur ON ur.role_id = r.id
            WHERE ur.user_id = :uid AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
        ");
        $st->execute([':uid' => $userId]);
        $rows = $st->fetchAll();
        $keys = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (is_array($r) && isset($r['key'])) {
                    $k = (string)$r['key'];
                    if ($k !== '') $keys[] = $k;
                }
            }
        }
        return array_values(array_unique($keys));
    }

    public function index(): void
    {
        $user = \admin_require_perm('roles.view');
        [$user, $theme, $pdo] = $this->deps($user);

        // Admin-Rolle nicht anzeigen (Systemrolle)
        $rows = $pdo->query("
            SELECT id, `key`, `name`
            FROM roles
            WHERE (is_deleted = 0 OR is_deleted IS NULL)
              AND `key` <> 'admin'
            ORDER BY `key` ASC
        ")->fetchAll();
        if (!is_array($rows)) $rows = [];

        $st = $pdo->query("SELECT COUNT(*) AS c FROM roles WHERE is_deleted = 1 AND `key` <> 'admin'");
        $deletedCount = 0;
        $r = $st ? $st->fetch() : null;
        if (is_array($r)) $deletedCount = (int)($r['c'] ?? 0);

        $flash = null;

        \admin_layout_begin([
            'title'    => 'Rollen',
            'theme'    => $theme,
            'active'   => 'roles',
            'user'     => $user,
            'next'     => '/roles',
            'pageCss'  => 'pages-list',
            'headline' => 'Rollen',
            'subtitle' => 'Rollen anlegen, bearbeiten oder löschen (Soft-Delete).',
        ]);

        require __DIR__ . '/../Views/roles_list.php';
        \admin_layout_end();
    }

    public function deleted(): void
    {
        $user = \admin_require_perm('roles.view');
        [$user, $theme, $pdo] = $this->deps($user);

        $rows = $pdo->query("
            SELECT id, `key`, `name`, deleted_at
            FROM roles
            WHERE is_deleted = 1 AND `key` <> 'admin'
            ORDER BY deleted_at DESC
        ")->fetchAll();
        if (!is_array($rows)) $rows = [];

        $st = $pdo->query("SELECT COUNT(*) AS c FROM roles WHERE is_deleted = 1 AND `key` <> 'admin'");
        $deletedCount = 0;
        $r = $st ? $st->fetch() : null;
        if (is_array($r)) $deletedCount = (int)($r['c'] ?? 0);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'Gelöschte Rollen',
            'theme'    => $theme,
            'active'   => 'roles',
            'user'     => $user,
            'next'     => '/roles',
            'pageCss'  => 'pages-list',
            'headline' => 'Gelöschte Rollen',
            'subtitle' => 'Hier kannst du gelöschte Rollen wiederherstellen.',
        ]);

        require __DIR__ . '/../Views/roles_deleted.php';
        \admin_layout_end();
    }

    public function edit(): void
    {
        $user = \admin_require_perm('roles.view');
        [$user, $theme, $pdo] = $this->deps($user);

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id > 0) {
            \admin_require_perm('roles.edit');
        } else {
            \admin_require_perm('roles.create');
        }

        $row = null;
        if ($id > 0) {
            $st = $pdo->prepare("SELECT * FROM roles WHERE id = :id LIMIT 1");
            $st->execute([':id' => $id]);
            $row = $st->fetch();
        }

        if (!is_array($row)) {
            $row = ['id' => 0, 'key' => '', 'name' => '', 'is_deleted' => 0];
        }

        if (!empty($row['is_deleted'])) {
            header('Location: /roles/deleted');
            exit;
        }

        $roleKey = (string)($row['key'] ?? '');
        $isAdminRole = ($roleKey === 'admin');
        if ($isAdminRole) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $currentRoleKeys = $this->currentUserRoleKeys((int)($user['id'] ?? 0), $pdo);
        $isOwnRole = ($roleKey !== '' && in_array($roleKey, $currentRoleKeys, true));

        $permRows = $pdo->query("SELECT id, `key`, `label`, `group_key` FROM permissions ORDER BY group_key, `key`")->fetchAll();
        if (!is_array($permRows)) $permRows = [];

        $forbidden = array_fill_keys(self::forbiddenPermissionKeys(), true);
        $permRows = array_values(array_filter($permRows, function($p) use ($forbidden) {
            if (!is_array($p)) return false;
            $k = (string)($p['key'] ?? '');
            if ($k === '') return false;
            if (isset($forbidden[$k])) return false;
            return true;
        }));

        $groups = [];
        foreach ($permRows as $p) {
            $gk = (string)($p['group_key'] ?? 'general');
            if ($gk === '') $gk = 'general';
            $groups[$gk] ??= [];
            $groups[$gk][] = $p;
        }

        $selectedSet = [];
        if ($id > 0) {
            $st = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :rid");
            $st->execute([':rid' => $id]);
            $rows = $st->fetchAll();
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (is_array($r) && isset($r['permission_id'])) {
                        $selectedSet[(int)$r['permission_id']] = true;
                    }
                }
            }
        }

        // ✅ Minimalinvasiv: Wer roles.edit hat, darf Rechte zuweisen (eigene Rolle bleibt gesperrt)
        $canEditPerms = (function_exists('admin_can') && admin_can('roles.edit'));
        if ($isOwnRole) $canEditPerms = false;

        $implications = self::permissionImplications();
        $flash = null;

        \admin_layout_begin([
            'title'    => 'Rolle bearbeiten',
            'theme'    => $theme,
            'active'   => 'roles',
            'user'     => $user,
            'next'     => '/roles',
            'pageCss'  => 'roles-edit',
            'headline' => 'Rolle',
            'subtitle' => 'Key + Name + Rechte.',
        ]);

        require __DIR__ . '/../Views/roles_edit.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('roles.view');
        [$user, $_theme, $pdo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $id   = (int)($_POST['id'] ?? 0);
        $key  = trim((string)($_POST['key'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));

        if ($id > 0) {
            \admin_require_perm('roles.edit');
        } else {
            \admin_require_perm('roles.create');
        }

        $existing = null;
        if ($id > 0) {
            $st = $pdo->prepare("SELECT * FROM roles WHERE id = :id LIMIT 1");
            $st->execute([':id' => $id]);
            $existing = $st->fetch();
        }
        $existingKey = is_array($existing) ? (string)($existing['key'] ?? '') : '';
        if ($existingKey === 'admin' || $key === 'admin') {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $currentRoleKeys = $this->currentUserRoleKeys((int)($user['id'] ?? 0), $pdo);
        $targetKeyForOwnCheck = ($existingKey !== '' ? $existingKey : $key);
        $isOwnRole = ($targetKeyForOwnCheck !== '' && in_array($targetKeyForOwnCheck, $currentRoleKeys, true));

        if ($key === '' || $name === '') {
            http_response_code(400);
            echo 'Key/Name fehlt.';
            return;
        }

        // Key/Name speichern
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE roles SET `key`=:k, `name`=:n WHERE id=:id");
            $st->execute([':k'=>$key, ':n'=>$name, ':id'=>$id]);
            $id2 = $id;
        } else {
            $st = $pdo->prepare("INSERT INTO roles (`key`,`name`) VALUES (:k,:n)");
            $st->execute([':k'=>$key, ':n'=>$name]);
            $id2 = (int)$pdo->lastInsertId();
        }

        // ✅ Minimalinvasiv: Rechte zuweisen basiert auf roles.edit (eigene Rolle bleibt gesperrt)
        $canEditPerms = (function_exists('admin_can') && admin_can('roles.edit'));
        if ($isOwnRole) $canEditPerms = false;

        $hasPermPost = array_key_exists('perm', $_POST);
        if ($id2 > 0 && $canEditPerms && $hasPermPost) {
            $permIds = $_POST['perm'] ?? [];
            if (!is_array($permIds)) $permIds = [];
            $permIds = array_values(array_unique(array_map('intval', $permIds)));
            $permIds = array_values(array_filter($permIds, fn($x) => $x > 0));

            // IDs -> Keys
            $keys = [];
            if ($permIds) {
                $in = implode(',', array_fill(0, count($permIds), '?'));
                $st = $pdo->prepare("SELECT `key` FROM permissions WHERE id IN ($in)");
                $st->execute($permIds);
                $rows = $st->fetchAll();
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        if (is_array($r) && isset($r['key'])) {
                            $k = (string)$r['key'];
                            if ($k !== '') $keys[] = $k;
                        }
                    }
                }
            }

            // Verbotene Keys serverseitig killen
            $forbidden = array_fill_keys(self::forbiddenPermissionKeys(), true);
            $keys = array_values(array_filter($keys, fn($k) => $k !== '' && !isset($forbidden[$k])));

            // Implications erweitern
            $implies = self::permissionImplications();
            $set = [];
            $q = [];
            foreach ($keys as $k) { $set[$k] = true; $q[] = $k; }

            while ($q) {
                $cur = array_pop($q);
                foreach (($implies[$cur] ?? []) as $need) {
                    $need = (string)$need;
                    if ($need === '' || isset($forbidden[$need])) continue;
                    if (!isset($set[$need])) { $set[$need] = true; $q[] = $need; }
                }
            }

            $expandedKeys = array_keys($set);

            // Keys -> IDs
            $expandedIds = [];
            if ($expandedKeys) {
                $in = implode(',', array_fill(0, count($expandedKeys), '?'));
                $st = $pdo->prepare("SELECT id, `key` FROM permissions WHERE `key` IN ($in)");
                $st->execute($expandedKeys);
                $rows = $st->fetchAll();
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        if (!is_array($r)) continue;
                        $k = (string)($r['key'] ?? '');
                        if ($k === '' || isset($forbidden[$k])) continue;
                        $expandedIds[] = (int)($r['id'] ?? 0);
                    }
                }
            }
            $expandedIds = array_values(array_unique(array_filter($expandedIds, fn($x) => $x > 0)));

            // Replace role_permissions atomar
            $pdo->beginTransaction();
            try {
                $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = :rid");
                $del->execute([':rid' => $id2]);

                if ($expandedIds) {
                    $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)");
                    foreach ($expandedIds as $pid) {
                        $ins->execute([':rid' => $id2, ':pid' => (int)$pid]);
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        header('Location: /roles/edit?id=' . (int)$id2);
        exit;
    }

    public function delete(): void
    {
        $user = \admin_require_perm('roles.delete');
        [$user, $_theme, $pdo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /roles');
            exit;
        }

        $st = $pdo->prepare("SELECT `key` FROM roles WHERE id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        $key = is_array($r) ? (string)($r['key'] ?? '') : '';
        if ($key === 'admin') {
            http_response_code(400);
            echo 'Admin-Rolle kann nicht gelöscht werden.';
            return;
        }

        $st = $pdo->prepare("SELECT COUNT(*) AS c FROM user_roles WHERE role_id = :rid");
        $st->execute([':rid' => $id]);
        $cnt = (int)(($st->fetch()['c'] ?? 0));
        if ($cnt > 0) {
            http_response_code(400);
            echo 'Rolle kann nicht gelöscht werden: Es sind Benutzer zugeordnet.';
            return;
        }

        $st = $pdo->prepare("UPDATE roles SET is_deleted=1, deleted_at=NOW() WHERE id=:id");
        $st->execute([':id' => $id]);

        header('Location: /roles');
        exit;
    }

    public function restore(): void
    {
        $user = \admin_require_perm('roles.delete');
        [$user, $_theme, $pdo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            header('Location: /roles');
            exit;
        }

        $st = $pdo->prepare("SELECT `key` FROM roles WHERE id=:id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        $key = is_array($r) ? (string)($r['key'] ?? '') : '';
        if ($key === 'admin') {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $st = $pdo->prepare("UPDATE roles SET is_deleted=0, deleted_at=NULL WHERE id=:id");
        $st->execute([':id' => $id]);

        header('Location: /roles');
        exit;
    }
    public function purge(): void
    {
        $user = \admin_require_perm('roles.delete');
        [$user, $_theme, $pdo] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $repo = new \App\Repositories\RoleRepositoryDb($pdo);

        $n = $repo->purgeDeleted();
        $_SESSION['flash'] = ($n > 0)
            ? ['type' => 'success', 'msg' => 'Papierkorb geleert (' . $n . ').']
            : ['type' => 'success', 'msg' => 'Papierkorb ist bereits leer.'];

        header('Location: /roles/deleted');
        exit;
    }
}

