<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\MediaRepositoryDb;
use App\Repositories\MediaFolderRepositoryDb;
use App\Repositories\MediaUsageRepositoryDb;
use App\Services\MediaService;

final class MediaController
{
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));
        $pdo   = \admin_pdo();

        $media   = new MediaRepositoryDb($pdo);
        $folders = new MediaFolderRepositoryDb($pdo);
        $usages  = new MediaUsageRepositoryDb($pdo);

        $service = new MediaService($pdo, $media, $folders);

        return [$user, $theme, $pdo, $media, $folders, $usages, $service];
    }

    private function json(int $code, array $payload): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function index(): void
    {
        $user = \admin_require_perm('media.view');
        [$user, $theme, $_pdo, $mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        $folderId   = isset($_GET['folder']) ? (int)$_GET['folder'] : 1;
        $q          = trim((string)($_GET['q'] ?? ''));
        $ext        = trim((string)($_GET['ext'] ?? ''));
        $onlyUnused = (string)($_GET['unused'] ?? '') === '1';
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = (int)($_GET['per_page'] ?? 40);
        $perPage    = max(1, min(200, $perPage));

        // ✅ GET > Pref > Default
        $view = (string)($_GET['view'] ?? '');
        if ($view === '') {
            $view = \admin_get_pref((int)($user['id'] ?? 0), 'media.view', 'grid');
        }
        $view = ($view === 'list') ? 'list' : 'grid'; // grid|list

        // ✅ Persistiere explizite Auswahl aus GET als User-Pref
        if ((string)($_GET['view'] ?? '') !== '') {
            \admin_set_pref((int)($user['id'] ?? 0), 'media.view', $view);
        }

        if ($folderId < 0) $folderId = 1;
        if ($folderId > 0 && !$folderRepo->findById($folderId)) $folderId = 1;

        $total = $mediaRepo->countActive($folderId, $q, $ext, $onlyUnused);
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = $mediaRepo->listActive($folderId, $q, $ext, $onlyUnused, $perPage, $offset);

        $folders = $folderRepo->listAll();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        
        if (!empty($_GET['picker'])) {
            $title = 'Medien';
            $pageCss = 'media-list';

            \define('ADMIN_HIDE_SIDEBAR', true);

            \admin_layout_begin([
                'title'    => 'Medien',
                'theme'    => $theme,
                'active'   => null,
                'user'     => $user,
                'pageCss'  => 'media-list',
                'headline' => 'Medien',
                'subtitle' => null,
            ]);

            require __DIR__ . '/../Views/media_list.php';

            \admin_layout_end();
            return;
        }

        \admin_layout_begin([
            'title'    => 'Medien',
            'theme'    => $theme,
            'active'   => 'media',
            'user'     => $user,
            'next'     => '/',
            'pageCss'  => 'media-list',
            'headline' => 'Medien',
            'subtitle' => 'Dateien hochladen, organisieren und verwenden.',
        ]);

        require __DIR__ . '/../Views/media_list.php';

        \admin_layout_end();

    }

    public function deleted(): void
    {
        $user = \admin_require_perm('media.delete');
        [$user, $theme, $_pdo, $mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        $q    = trim((string)($_GET['q'] ?? ''));
        $ext  = trim((string)($_GET['ext'] ?? ''));

        // ✅ GET > Pref > Default
        $view = (string)($_GET['view'] ?? '');
        if ($view === '') {
            $view = \admin_get_pref((int)($user['id'] ?? 0), 'media.view', 'list');
        }
        $view = ($view === 'grid') ? 'grid' : 'list';

        // ✅ Persistiere explizite Auswahl aus GET als User-Pref
        if ((string)($_GET['view'] ?? '') !== '') {
            \admin_set_pref((int)($user['id'] ?? 0), 'media.view', $view);
        }

        $rows = $mediaRepo->listDeleted($q, $ext, 300, 0);

        $folders = $folderRepo->listAll();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'Papierkorb – Medien',
            'theme'    => $theme,
            'active'   => 'media',
            'user'     => $user,
            'next'     => '/media',
            'pageCss'  => 'media-list',
            'headline' => 'Gelöschte Medien',
            'subtitle' => 'Wiederherstellen oder endgültig löschen.',
        ]);

        require __DIR__ . '/../Views/media_deleted.php';

        \admin_layout_end();
    }

    public function show(): void
    {
        $user = \admin_require_perm('media.view');
        [$user, $theme, $_pdo, $mediaRepo, $folderRepo, $usageRepo, $_service] = $this->deps($user);

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'Bad Request';
            return;
        }

        $row = $mediaRepo->findById($id);
        if (!$row) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $folders = $folderRepo->listAll();
        $usages  = $usageRepo->listForMedia($id);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'Medium',
            'theme'    => $theme,
            'active'   => 'media',
            'user'     => $user,
            'next'     => '/media',
            'pageCss'  => 'media-edit',
            'headline' => 'Medium',
            'subtitle' => 'Metadaten & Verwendungen.',
        ]);

        $canEdit = (function_exists('admin_can') && admin_can('media.edit'));
        $canDelete = (function_exists('admin_can') && admin_can('media.delete'));

        require __DIR__ . '/../Views/media_show.php';

        \admin_layout_end();
    }

    public function edit(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $theme, $_pdo, $mediaRepo, $folderRepo, $usageRepo, $_service] = $this->deps($user);

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo 'Bad Request';
            return;
        }

        $row = $mediaRepo->findById($id);
        if (!$row) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $folders = $folderRepo->listAll();
        $usages  = $usageRepo->listForMedia($id);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        \admin_layout_begin([
            'title'    => 'Medium bearbeiten',
            'theme'    => $theme,
            'active'   => 'media',
            'user'     => $user,
            'next'     => '/media',
            'pageCss'  => 'media-edit',
            'headline' => 'Medium bearbeiten',
            'subtitle' => 'Metadaten pflegen & Verwendungen prüfen.',
        ]);

        $canEdit = (function_exists('admin_can') && admin_can('media.edit'));
        require __DIR__ . '/../Views/media_edit.php';

        \admin_layout_end();
    }

    public function upload(): void
    {
        $user = \admin_require_perm('media.upload');
        [$user, $_theme, $_pdo, $_mediaRepo, $folderRepo, $_usageRepo, $service] = $this->deps($user);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $folderId = (int)($_POST['folder_id'] ?? 0);
        if ($folderId <= 0) $folderId = 1;

        if (!$folderRepo->findById($folderId)) {
            $folderId = 1;
        }

        if (!$folderRepo->findById($folderId)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid folder'];
            header('Location: /media');
            exit;
        }

        if (!isset($_FILES['files'])) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'No files uploaded'];
            header('Location: /media');
            exit;
        }

        try {
            $ids = $service->uploadFromFilesGlobal($_FILES['files'], $folderId);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Upload erfolgreich (' . count($ids) . ' Datei(en)).'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        }

        header('Location: /media?folder=' . $folderId);
        exit;
    }

    public function folderCreate(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $theme, $_pdo, $_mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $parentId = (int)($_POST['parent_id'] ?? 1);
        if ($parentId <= 0) $parentId = 1;

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordnername fehlt.'];
            header('Location: /media?folder=' . $parentId);
            exit;
        }

        $existingFolder = $folderRepo->findByParentAndName($parentId, $name);
        if ($existingFolder) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordnername bereits vergeben.'];
            header('Location: /media?folder=' . $parentId);
            exit;
        }

        $folderId = $folderRepo->createFolder($parentId, $name);
        if ($folderId > 0) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Ordner wurde erfolgreich erstellt.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Fehler beim Erstellen des Ordners.'];
        }

        header('Location: /media?folder=' . $parentId);
        exit;
    }

    public function folderMove(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $_theme, $_pdo, $_mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $folderId = (int)($_POST['folder_id'] ?? 0);
        $targetParentId = (int)($_POST['target_parent_id'] ?? 0);

        if ($folderId <= 1) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Dieser Ordner kann nicht verschoben werden.'];
            header('Location: /media');
            exit;
        }

        $folder = $folderRepo->findById($folderId);
        if (!is_array($folder)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordner nicht gefunden.'];
            header('Location: /media');
            exit;
        }

        if ($targetParentId <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Zielordner ist ungültig.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        $targetParent = $folderRepo->findById($targetParentId);
        if (!is_array($targetParent)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Zielordner wurde nicht gefunden.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        if ($targetParentId === $folderId || $this->folderIsDescendantOf($targetParentId, $folderId, $folderRepo)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ein Ordner kann nicht in sich selbst oder einen Unterordner verschoben werden.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        $existing = $folderRepo->findByParentAndName($targetParentId, (string)($folder['name'] ?? ''));
        if (is_array($existing) && (int)($existing['id'] ?? 0) !== $folderId) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Im Zielordner gibt es bereits einen Ordner mit diesem Namen.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        if ($folderRepo->moveFolder($folderId, $targetParentId)) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Ordner wurde verschoben.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordner konnte nicht verschoben werden.'];
        header('Location: /media?folder=' . $folderId);
        exit;
    }

    public function folderDelete(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $_theme, $_pdo, $_mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $folderId = (int)($_POST['folder_id'] ?? 0);
        if ($folderId <= 1) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Dieser Ordner kann nicht gelöscht werden.'];
            header('Location: /media');
            exit;
        }

        $folder = $folderRepo->findById($folderId);
        if (!is_array($folder)) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordner nicht gefunden.'];
            header('Location: /media');
            exit;
        }

        if ($folderRepo->countChildren($folderId) > 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordner kann nur gelöscht werden, wenn er keine Unterordner enthält.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        if ($folderRepo->countMediaItems($folderId) > 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordner kann nur gelöscht werden, wenn er keine Medien enthält.'];
            header('Location: /media?folder=' . $folderId);
            exit;
        }

        $parentId = (int)($folder['parent_id'] ?? 1);
        if ($parentId <= 0) {
            $parentId = 1;
        }

        if ($folderRepo->deleteFolder($folderId)) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Ordner wurde gelöscht.'];
            header('Location: /media?folder=' . $parentId);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ordner konnte nicht gelöscht werden.'];
        header('Location: /media?folder=' . $folderId);
        exit;
    }

    public function save(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $_theme, $_pdo, $mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid ID'];
            header('Location: /media');
            exit;
        }

        $row = $mediaRepo->findById($id);
        if (!$row) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Not found'];
            header('Location: /media');
            exit;
        }

        $folderId = (int)($_POST['folder_id'] ?? 0);
        if ($folderId <= 0 || !$folderRepo->findById($folderId)) {
            $folderId = (int)($row['folder_id'] ?? 1);
        }

        $display = $this->sanitizeDisplay((string)($_POST['display_filename'] ?? (string)($row['display_filename'] ?? '')));
        if ($display === '') $display = (string)($row['display_filename'] ?? '');

        $title = trim((string)($_POST['title'] ?? ''));
        $alt = trim((string)($_POST['alt_text'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));

        $focusX = $_POST['focus_x'] ?? null;
        $focusY = $_POST['focus_y'] ?? null;

        $fx = null;
        if ($focusX !== null && $focusX !== '') {
            $t = (float)$focusX;
            if ($t < -1.0) $t = -1.0;
            if ($t > 1.0) $t = 1.0;
            $fx = $t;
        }

        $fy = null;
        if ($focusY !== null && $focusY !== '') {
            $t = (float)$focusY;
            if ($t < -1.0) $t = -1.0;
            if ($t > 1.0) $t = 1.0;
            $fy = $t;
        }

        try {
            $mediaRepo->updateMeta($id, [
                'folder_id'        => $folderId,
                'display_filename' => $display,
                'title'            => ($title === '' ? null : $title),
                'alt_text'         => ($alt === '' ? null : $alt),
                'description'      => ($desc === '' ? null : $desc),
                'focus_x'          => $fx,
                'focus_y'          => $fy,
            ]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Gespeichert.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        }

        header('Location: /media/edit?id=' . $id);
        exit;
    }

    public function delete(): void
    {
        $user = \admin_require_perm('media.delete');
        [$user, $_theme, $_pdo, $mediaRepo, $_folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $idsRaw = $_POST['id'] ?? [];
        if (!is_array($idsRaw)) {
            $idsRaw = [$idsRaw];
        }

        $ids = array_values(array_filter(array_map('intval', $idsRaw), static fn($v) => $v > 0));
        if (!$ids) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Keine Medien ausgewählt.'];
            header('Location: /media');
            exit;
        }

        $deleted = $mediaRepo->softDeleteUnusedBulk($ids);

        $_SESSION['flash'] = ($deleted > 0)
            ? ['type' => 'success', 'msg' => $deleted . ' Medium/Medien in Papierkorb verschoben.']
            : ['type' => 'error', 'msg' => 'Konnte nicht gelöscht werden'];

        header('Location: /media');
        exit;
    }

    public function restore(): void
    {
        $user = \admin_require_perm('media.delete');
        [$user, $_theme, $_pdo, $mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $ids = $_POST['id'] ?? [];
        if (!is_array($ids)) $ids = [$ids];

        $ids = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));
        if (!$ids) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Keine Medien ausgewählt.'];
            header('Location: /media/deleted');
            exit;
        }

        $ok = 0;

        foreach ($ids as $id) {
            $row = $mediaRepo->findById((int)$id);
            if (!$row) {
                continue;
            }

            $fid = (int)($row['folder_id'] ?? 1);
            if ($fid <= 0 || !$folderRepo->findById($fid)) {
                $mediaRepo->moveToFolder((int)$id, 1);
            }

            if ($mediaRepo->restore((int)$id)) {
                $ok++;
            }
        }

        $_SESSION['flash'] = ($ok > 0)
            ? ['type'=>'success', 'msg'=> $ok . ' Medium/Medien wiederhergestellt.']
            : ['type'=>'error', 'msg'=> 'Wiederherstellen nicht möglich.'];

        $view = $_GET['view'] ?? 'grid';
        $folderId = $_GET['folder'] ?? 0;

        header('Location: /media?view=' . urlencode((string)$view) . '&folder=' . urlencode((string)$folderId));
        exit;
    }

    public function move(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $_theme, $_pdo, $mediaRepo, $folderRepo, $_usageRepo, $_service] = $this->deps($user);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405, ['ok' => false, 'error' => 'Method Not Allowed']);
            return;
        }

        \admin_verify_csrf();

        $mediaId  = (int)($_POST['media_id'] ?? 0);
        $folderId = (int)($_POST['folder_id'] ?? 0);

        if ($mediaId <= 0 || $folderId <= 0) {
            $this->json(400, ['ok' => false, 'error' => 'Invalid parameters']);
            return;
        }

        if (!$folderRepo->findById($folderId)) {
            $this->json(404, ['ok' => false, 'error' => 'Folder not found']);
            return;
        }

        $row = $mediaRepo->findById($mediaId);
        if (!$row) {
            $this->json(404, ['ok' => false, 'error' => 'Media not found']);
            return;
        }

        if ((int)($row['is_deleted'] ?? 0) === 1) {
            $this->json(409, ['ok' => false, 'error' => 'Media is deleted']);
            return;
        }

        $ok = $mediaRepo->moveToFolder($mediaId, $folderId);
        if (!$ok) {
            $this->json(500, ['ok' => false, 'error' => 'Move failed']);
            return;
        }

        $this->json(200, ['ok' => true]);
    }

    public function rotate(): void
    {
        $user = \admin_require_perm('media.edit');
        [$user, $_theme, $_pdo, $mediaRepo, $_folderRepo, $_usageRepo, $service] = $this->deps($user);

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Method Not Allowed'];
            header('Location: /media');
            exit;
        }

        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ungültige Medien-ID.'];
            header('Location: /media');
            exit;
        }

        $direction = strtolower(trim((string)($_POST['direction'] ?? 'cw')));
        $degrees = $direction === 'ccw' ? 270 : 90;

        $row = $mediaRepo->findById($id);
        if (!$row || (int)($row['is_deleted'] ?? 0) === 1) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Medium nicht gefunden.'];
            header('Location: /media');
            exit;
        }

        try {
            $service->rotateStoredMedia($row, $degrees);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Bild erfolgreich gedreht.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
        }

        header('Location: /media/edit?id=' . $id);
        exit;
    }

    public function file(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $pdo = \admin_pdo();
        $mediaRepo = new MediaRepositoryDb($pdo);
        $folderRepo = new MediaFolderRepositoryDb($pdo);
        $service = new MediaService($pdo, $mediaRepo, $folderRepo);

        $row = $mediaRepo->findById($id);
        if (!$row) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $isDeleted = ((int)($row['is_deleted'] ?? 0) === 1);
        if ($isDeleted && !\admin_can('media.delete')) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $path = $service->getStoragePathForMedia($row);
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $mime = (string)($row['mime'] ?? 'application/octet-stream');
        $ext  = (string)($row['ext'] ?? '');

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');

        if ($ext === 'svg') {
            header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'none'; sandbox");
            header('Content-Type: image/svg+xml; charset=UTF-8');
        } else {
            header('Content-Type: ' . $mime);
        }

        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: inline; filename="' . $this->safeHeaderFilename((string)($row['original_filename'] ?? 'file')) . '"');

        readfile($path);
        exit;
    }

    public function thumb(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $pdo = \admin_pdo();
        $mediaRepo = new MediaRepositoryDb($pdo);
        $folderRepo = new MediaFolderRepositoryDb($pdo);
        $service = new MediaService($pdo, $mediaRepo, $folderRepo);

        $row = $mediaRepo->findById($id);
        if (!$row) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $isDeleted = ((int)($row['is_deleted'] ?? 0) === 1);

        if ($isDeleted) {
            if (!\admin_can('media.delete')) {
                http_response_code(404);
                echo 'Not Found';
                return;
            }
        }

        $path = $service->getStoragePathForMedia($row);
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Not Found';
            return;
        }

        $mime = (string)($row['mime'] ?? 'application/octet-stream');
        $ext  = (string)($row['ext'] ?? '');

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');

        if ($ext === 'pdf') {
            $svg = $this->buildPdfThumbSvg($row);
            header('Content-Type: image/svg+xml; charset=UTF-8');
            header('Content-Length: ' . (string)strlen($svg));
            header('Content-Disposition: inline; filename="pdf-thumb-' . $id . '.svg"');
            echo $svg;
            exit;
        }

        if ($ext === 'svg') {
            header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'none'; sandbox");
            header('Content-Type: image/svg+xml; charset=UTF-8');
        } else {
            header('Content-Type: ' . $mime);
        }

        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: inline; filename="' . $this->safeHeaderFilename((string)($row['original_filename'] ?? 'file')) . '"');

        readfile($path);
        exit;
    }
    
    public function purge(): void
    {
        $user = \admin_require_perm('media.delete');
        [$user, $_theme, $_pdo, $mediaRepo, $_folderRepo, $_usageRepo, $service] = $this->deps($user);

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        \admin_verify_csrf();

        $rows = $mediaRepo->listDeletedForPurge();
        if (!$rows) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Papierkorb ist bereits leer.'];
            header('Location: /media/deleted');
            exit;
        }

        $ids = [];
        $paths = [];

        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;

            $sf = (string)($r['storage_filename'] ?? '');
            if ($sf !== '') {
                $paths[] = rtrim($service->mediaStorageDir(), '/') . '/' . ltrim($sf, '/');
            }

            $ids[] = $id;
        }

        // 1) DB löschen (CASCADE löscht media_usages automatisch)
        $n = $mediaRepo->purgeDeletedByIds($ids);

        // 2) Dateien löschen (best effort; fehlende Dateien sind ok)
        foreach ($paths as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }

        $_SESSION['flash'] = ($n > 0)
            ? ['type' => 'success', 'msg' => 'Papierkorb geleert (' . $n . ').']
            : ['type' => 'success', 'msg' => 'Papierkorb ist bereits leer.'];

        header('Location: /media/deleted');
        exit;
    }

    private function safeHeaderFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') $name = 'file';
        $name = str_replace(["\r", "\n", '"'], ['', '', ''], $name);
        return $name;
    }

    private function folderIsDescendantOf(int $folderId, int $ancestorId, MediaFolderRepositoryDb $folderRepo): bool
    {
        $currentId = $folderId;
        $guard = 0;
        while ($currentId > 0 && $guard < 100) {
            if ($currentId === $ancestorId) {
                return true;
            }
            $row = $folderRepo->findById($currentId);
            if (!is_array($row)) {
                return false;
            }
            $parentId = (int)($row['parent_id'] ?? 0);
            if ($parentId <= 0) {
                return false;
            }
            $currentId = $parentId;
            $guard++;
        }
        return false;
    }

    private function buildPdfThumbSvg(array $row): string
    {
        $label = trim((string)($row['display_filename'] ?? $row['original_filename'] ?? 'PDF'));
        if ($label === '') {
            $label = 'PDF';
        }

        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        if (mb_strlen($label) > 28) {
            $label = mb_substr($label, 0, 25) . '...';
        }

        $sizeBytes = (int)($row['size_bytes'] ?? 0);
        $meta = $sizeBytes > 0 ? $this->humanFilesize($sizeBytes) : 'PDF-Datei';

        $labelEsc = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $metaEsc = htmlspecialchars($meta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480" viewBox="0 0 640 480" role="img" aria-label="PDF Vorschau">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#fff7ed"/>
      <stop offset="100%" stop-color="#ffe4e6"/>
    </linearGradient>
  </defs>
  <rect width="640" height="480" rx="32" fill="url(#bg)"/>
  <rect x="120" y="56" width="400" height="368" rx="28" fill="#ffffff" stroke="#e5e7eb" stroke-width="10"/>
  <path d="M440 56v92c0 15.464 12.536 28 28 28h52" fill="#fee2e2"/>
  <path d="M440 56l80 120h-52c-15.464 0-28-12.536-28-28V56z" fill="#fecaca"/>
  <rect x="168" y="140" width="128" height="48" rx="16" fill="#dc2626"/>
  <text x="232" y="172" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="700" fill="#ffffff">PDF</text>
  <rect x="168" y="220" width="304" height="18" rx="9" fill="#e5e7eb"/>
  <rect x="168" y="256" width="248" height="18" rx="9" fill="#e5e7eb"/>
  <rect x="168" y="292" width="276" height="18" rx="9" fill="#e5e7eb"/>
  <text x="320" y="360" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="700" fill="#111827">{$labelEsc}</text>
  <text x="320" y="394" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="20" fill="#6b7280">{$metaEsc}</text>
</svg>
SVG;
    }

    private function humanFilesize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $decimals = $value >= 10 || $unit === 0 ? 0 : 1;
        return number_format($value, $decimals, ',', '.') . ' ' . $units[$unit];
    }

    private function sanitizeDisplay(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        $name = str_replace(["\r", "\n", "\t"], ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name);
        $name = str_replace(['\\', '/'], '', $name);
        $name = str_replace(['..'], '', $name);
        return $name;
    }
}
