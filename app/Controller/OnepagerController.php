<?php
declare(strict_types=1);
namespace App\Controller;

use App\Core\WebsiteMode;
use App\Services\OnepagerService;

final class OnepagerController
{
    private function requireEnabled(): bool
    {
        if (WebsiteMode::isOnepager()) return true;
        http_response_code(404);
        echo 'Not Found';
        return false;
    }

    public function index(): void
    {
        $user = \admin_require_perm('pages.view');
        if (!$this->requireEnabled()) return;
        $snapshot = (new OnepagerService(\admin_pdo()))->snapshot();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        \admin_layout_begin(['title'=>'Onepager','headline'=>'Onepager','theme'=>\admin_theme_for_user((int)$user['id']),'active'=>'pages','user'=>$user,'next'=>'/pages/onepager','pageCss'=>'onepager']);
        require __DIR__ . '/../Views/onepager.php';
        \admin_layout_end();
    }

    public function save(): void
    {
        \admin_require_perm('pages.edit');
        if (!$this->requireEnabled()) return;
        \admin_verify_csrf();
        try {
            $order = json_decode((string)($_POST['onepager_order'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            $selected = $_POST['onepager_selected'] ?? [];
            if (!is_array($order) || !is_array($selected)) throw new \DomainException('Ungueltige Auswahl.');
            foreach ($selected as &$id) {
                if (!is_string($id) || !ctype_digit($id)) throw new \DomainException('Ungueltige Seiten-ID.');
                $id = (int)$id;
            }
            unset($id);
            (new OnepagerService(\admin_pdo()))->save($order, $selected, (string)($_POST['onepager_revision'] ?? ''));
            $_SESSION['flash'] = ['type'=>'ok','msg'=>'Onepager-Einstellungen gespeichert.'];
        } catch (\DomainException|\JsonException $e) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>$e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Onepager-Einstellungen konnten nicht gespeichert werden.'];
        }
        header('Location: ' . \cms_base_path() . '/pages/onepager');
        exit;
    }
}
