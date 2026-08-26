<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repositories\NewsRepositoryDb;
use App\Repositories\NewsCategoryRepositoryDb;

final class NewsController
{
    private function deps(array $user): array
    {
        $theme = \admin_theme_for_user((int)($user['id'] ?? 0));
        $pdo = \admin_pdo();
        return [$user, $theme, $pdo, new NewsRepositoryDb($pdo), new NewsCategoryRepositoryDb($pdo)];
    }

    private static function slugify(string $title): string
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower(trim($title), 'UTF-8') : strtolower(trim($title));
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s !== '' ? $s : 'news';
    }

    public function index(): void
    {
        $user = \admin_require_perm('news.view');
        [$user, $theme, $_pdo, $news, $categories] = $this->deps($user);
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        try {
            $rows = $news->listActive($categoryId > 0 ? $categoryId : null);
            $allCategories = $categories->listActive();
            $deletedCount = $news->countDeleted();
        } catch (\Throwable $e) {
            $rows = [];
            $allCategories = [];
            $deletedCount = 0;
            $flash = ['type' => 'error', 'msg' => 'News konnten nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'News', 'theme' => $theme, 'active' => 'news', 'user' => $user,
            'next' => '/news', 'pageCss' => 'pages-list', 'headline' => 'News',
            'subtitle' => 'News erstellen, kategorisieren und veroeffentlichen.',
        ]);
        require __DIR__ . '/../Views/news_list.php';
        \admin_layout_end();
    }

    public function deleted(): void
    {
        $user = \admin_require_perm('news.view');
        [$user, $theme, $_pdo, $news] = $this->deps($user);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        try {
            $rows = $news->listDeleted();
            $deletedCount = $news->countDeleted();
        } catch (\Throwable $e) {
            $rows = [];
            $deletedCount = 0;
            $flash = ['type' => 'error', 'msg' => 'Papierkorb konnte nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'Geloeschte News', 'theme' => $theme, 'active' => 'news', 'user' => $user,
            'next' => '/news', 'pageCss' => 'pages-list', 'headline' => 'News', 'subtitle' => 'Papierkorb fuer News.',
        ]);
        require __DIR__ . '/../Views/news_deleted.php';
        \admin_layout_end();
    }

    public function categories(): void
    {
        $user = \admin_require_perm('news.view');
        \admin_require_perm('news.edit');
        [$user, $theme, $_pdo, $_news, $categories] = $this->deps($user);
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        try {
            $rows = $categories->listActive();
        } catch (\Throwable $e) {
            $rows = [];
            $flash = ['type' => 'error', 'msg' => 'Kategorien konnten nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'News-Kategorien', 'theme' => $theme, 'active' => 'news', 'user' => $user,
            'next' => '/news/categories', 'pageCss' => 'pages-list', 'headline' => 'News-Kategorien',
            'subtitle' => 'Kategorien ansehen und bearbeiten.',
        ]);
        require __DIR__ . '/../Views/news_categories.php';
        \admin_layout_end();
    }

    public function saveCategory(): void
    {
        $user = \admin_require_perm('news.view');
        \admin_require_perm('news.edit');
        [$user, $_theme, $_pdo, $_news, $categories] = $this->deps($user);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; return; }
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Kategorie-ID und Name sind erforderlich.'];
            header('Location: ' . cms_base_path() . '/news/categories');
            exit;
        }

        try {
            $existing = $categories->findById($id);
            if (!is_array($existing)) {
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Kategorie nicht gefunden.'];
                header('Location: ' . cms_base_path() . '/news/categories');
                exit;
            }
            $categories->update($id, $name);
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Kategorie gespeichert.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Kategorie konnte nicht gespeichert werden: ' . $e->getMessage()];
        }

        header('Location: ' . cms_base_path() . '/news/categories');
        exit;
    }

    public function edit(): void
    {
        $user = \admin_require_perm('news.view');
        [$user, $theme, $_pdo, $news, $categories] = $this->deps($user);
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) \admin_require_perm('news.edit'); else \admin_require_perm('news.create');

        $flash = null;
        try {
            $row = $id > 0 ? $news->findById($id) : null;
            $allCategories = $categories->listActive();
        } catch (\Throwable $e) {
            $row = null;
            $allCategories = [];
            $flash = ['type' => 'error', 'msg' => 'News konnte nicht geladen werden: ' . $e->getMessage()];
        }

        if (!is_array($row)) {
            $row = [
                'id' => 0, 'title' => '', 'slug' => '', 'teaser' => '', 'content_json' => '', 'category_id' => null,
                'image_media_id' => null, 'is_published' => 1, 'published_at' => '', 'is_deleted' => 0,
            ];
        }
        if (!empty($row['is_deleted'])) { header('Location: ' . cms_base_path() . '/news/deleted'); exit; }

        \admin_layout_begin([
            'title' => 'News bearbeiten', 'theme' => $theme, 'active' => 'news', 'user' => $user,
            'next' => '/news', 'pageCss' => 'pages-edit', 'headline' => 'News',
            'subtitle' => 'Titel, Teaser, Inhalt, Kategorie und Bild.',
        ]);
        require __DIR__ . '/../Views/news_edit.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        $user = \admin_require_perm('news.view');
        [$user, $_theme, $_pdo, $news, $categories] = $this->deps($user);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; return; }
        \admin_verify_csrf();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) \admin_require_perm('news.edit'); else \admin_require_perm('news.create');

        $title = trim((string)($_POST['title'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $teaser = trim((string)($_POST['teaser'] ?? ''));
        $contentJson = trim((string)($_POST['content_json'] ?? ''));
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $imageMediaId = (int)($_POST['image_media_id'] ?? 0);
        $isPublished = !empty($_POST['is_published']);
        $publishedAtRaw = trim((string)($_POST['published_at'] ?? ''));
        $newCategory = trim((string)($_POST['category_new'] ?? ''));

        if ($title === '') {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Titel ist erforderlich.'];
            header('Location: ' . cms_base_path() . '/news/edit' . ($id > 0 ? ('?id=' . $id) : ''));
            exit;
        }
        if ($slug === '') {
            $slug = self::slugify($title);
        } else {
            $slug = self::slugify($slug);
        }

        if ($newCategory !== '') {
            $catSlug = NewsCategoryRepositoryDb::slugify($newCategory);
            $existing = $categories->findBySlug($catSlug);
            if (is_array($existing)) {
                $categoryId = (int)($existing['id'] ?? 0);
            } else {
                $categoryId = $categories->create($newCategory, $catSlug);
            }
        }

        $publishedAt = null;
        if ($publishedAtRaw !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $publishedAtRaw) === 1) {
                $publishedAt = str_replace('T', ' ', $publishedAtRaw) . ':00';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $publishedAtRaw) === 1) {
                $publishedAt = strlen($publishedAtRaw) === 16 ? ($publishedAtRaw . ':00') : $publishedAtRaw;
            }
        }
        if ($publishedAt === null && $isPublished) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($contentJson === '') {
            $contentJson = (string)json_encode(['blocks' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $decoded = json_decode($contentJson, true);
            if (!is_array($decoded)) {
                $contentJson = (string)json_encode([
                    'blocks' => [[
                        'type' => 'text',
                        'text' => $contentJson,
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $contentJson = (string)json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $candidate = $slug;
        $i = 2;
        while ($news->slugExistsForOther($candidate, $id > 0 ? $id : 0)) {
            $candidate = $slug . '-' . $i;
            $i++;
        }
        $slug = $candidate;

        $news->save(
            $id > 0 ? $id : null,
            $title,
            $slug,
            $teaser,
            $contentJson,
            $categoryId > 0 ? $categoryId : null,
            $imageMediaId > 0 ? $imageMediaId : null,
            $isPublished,
            $publishedAt
        );

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'News gespeichert.'];
        header('Location: ' . cms_base_path() . '/news');
        exit;
    }

    public function delete(): void
    {
        \admin_require_perm('news.delete');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; return; }
        \admin_verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $news = new NewsRepositoryDb(\admin_pdo());
            $news->softDelete($id);
        }
        header('Location: ' . cms_base_path() . '/news');
        exit;
    }

    public function restore(): void
    {
        \admin_require_perm('news.delete');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; return; }
        \admin_verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $news = new NewsRepositoryDb(\admin_pdo());
            $news->restore($id);
        }
        header('Location: ' . cms_base_path() . '/news/deleted');
        exit;
    }

    public function purge(): void
    {
        \admin_require_perm('news.delete');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; return; }
        \admin_verify_csrf();
        $news = new NewsRepositoryDb(\admin_pdo());
        $n = $news->purgeDeleted();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => $n > 0 ? ('Papierkorb geleert (' . $n . ').') : 'Papierkorb ist bereits leer.'];
        header('Location: ' . cms_base_path() . '/news/deleted');
        exit;
    }
}
