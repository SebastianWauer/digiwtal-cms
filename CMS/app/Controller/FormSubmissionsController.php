<?php
declare(strict_types=1);

namespace App\Controller;

final class FormSubmissionsController
{
    private const STATUSES = ['open', 'processed', 'deleted'];
    private static function lower(string $v): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
    }
    private static function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        if (function_exists('mb_strpos')) {
            return mb_strpos($haystack, $needle) !== false;
        }
        return strpos($haystack, $needle) !== false;
    }
    private static function hasStatusColumn(\PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT status FROM form_submissions LIMIT 0');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalizeStatus(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === 'new') {
            return 'open';
        }
        return in_array($s, self::STATUSES, true) ? $s : 'open';
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Offen',
            'processed' => 'Bearbeitet',
            'deleted' => 'Geloescht',
            default => 'Offen',
        };
    }

    private static function ensureSchema(\PDO $pdo): void
    {
        $hasStatus = self::hasStatusColumn($pdo);
        if (!$hasStatus) {
            try {
                $pdo->exec("ALTER TABLE `form_submissions` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'open' AFTER `ip`");
            } catch (\Throwable) {
                return;
            }
        }

        // Legacy-Status "new" auf "open" migrieren
        try {
            $pdo->exec("UPDATE form_submissions SET status = 'open' WHERE status = 'new' OR status = '' OR status IS NULL");
        } catch (\Throwable) {
            // Fallback: ignore
        }
    }

    public function index(): void
    {
        $user = \admin_require_perm('forms.view');
        $theme = \admin_theme_for_user((int)$user['id']);
        $pdo = \admin_pdo();
        $flash = null;
        $items = [];
        $counts = ['open' => 0, 'processed' => 0, 'deleted' => 0];
        $q = trim((string)($_GET['q'] ?? ''));
        $filter = strtolower(trim((string)($_GET['filter'] ?? 'open')));
        if (!in_array($filter, ['open', 'processed', 'deleted', 'all'], true)) {
            $filter = 'open';
        }

        try {
            self::ensureSchema($pdo);

            $hasStatus = self::hasStatusColumn($pdo);
            $statusSelect = $hasStatus ? 'status' : "'open' AS status";
            $sql = "SELECT id, form_id, data_json, ip, $statusSelect, created_at FROM form_submissions";
            $where = [];
            $params = [];

            if ($hasStatus) {
                if ($filter === 'open') {
                    $where[] = "(status = 'open' OR status = 'new' OR status = '' OR status IS NULL)";
                } elseif ($filter === 'processed') {
                    $where[] = "status = 'processed'";
                } elseif ($filter === 'deleted') {
                    $where[] = "status = 'deleted'";
                }
            } elseif ($filter !== 'all' && $filter !== 'open') {
                // Ohne Statusspalte koennen nur offene/alle Eintraege angezeigt werden.
                $where[] = "1 = 0";
            }

            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 600';

            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
            if (!is_array($rows)) {
                $rows = [];
            }

            $recentCutoff = strtotime('yesterday 00:00:00');
            $needle = self::lower($q);
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $decoded = json_decode((string)($r['data_json'] ?? ''), true);
                $data = is_array($decoded) ? $decoded : [];
                $status = self::normalizeStatus((string)($r['status'] ?? 'open'));
                $createdAt = (string)($r['created_at'] ?? '');
                $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
                $isRecent = ($createdTs !== false && $createdTs >= $recentCutoff);

                $item = [
                    'id' => (int)($r['id'] ?? 0),
                    'form_id' => (string)($r['form_id'] ?? ''),
                    'ip' => (string)($r['ip'] ?? ''),
                    'created_at' => $createdAt,
                    'status' => $status,
                    'status_label' => self::statusLabel($status),
                    'is_recent' => $isRecent,
                    'name' => trim((string)($data['name'] ?? '')),
                    'email' => trim((string)($data['email'] ?? '')),
                    'phone' => trim((string)($data['phone'] ?? '')),
                    'message' => trim((string)($data['message'] ?? '')),
                ];

                if ($needle !== '') {
                    $haystack = self::lower(
                        implode("\n", [
                            (string)$item['created_at'],
                            (string)date('d.m.Y H:i:s', $createdTs !== false ? (int)$createdTs : time()),
                            (string)$item['name'],
                            (string)$item['email'],
                            (string)$item['phone'],
                            (string)$item['message'],
                        ])
                    );
                    if (!self::contains($haystack, $needle)) {
                        continue;
                    }
                }

                $items[] = $item;
            }

            if ($hasStatus) {
                $qCounts = $pdo->query("SELECT status, COUNT(*) c FROM form_submissions GROUP BY status");
                $cr = $qCounts ? $qCounts->fetchAll() : [];
                foreach (is_array($cr) ? $cr : [] as $row) {
                    if (!is_array($row)) continue;
                    $s = self::normalizeStatus((string)($row['status'] ?? ''));
                    $counts[$s] = (int)($counts[$s] ?? 0) + (int)($row['c'] ?? 0);
                }
            } else {
                $counts['open'] = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions")->fetchColumn();
            }
        } catch (\Throwable $e) {
            $flash = ['type' => 'error', 'msg' => 'Formulare konnten nicht geladen werden: ' . $e->getMessage()];
        }

        \admin_layout_begin([
            'title' => 'Formulareingaben',
            'theme' => $theme,
            'active' => 'forms',
            'user' => $user,
            'next' => '/forms/submissions',
            'pageCss' => 'pages-list',
            'headline' => 'Formulareingaben',
            'subtitle' => 'Eingegangene Kontaktanfragen aus dem Frontend.',
        ]);

        require __DIR__ . '/../Views/form_submissions_list.php';
        \admin_layout_end();
    }

    public function status(): void
    {
        $user = \admin_require_perm('forms.edit');
        unset($user);
        \admin_verify_csrf();
        $pdo = \admin_pdo();
        self::ensureSchema($pdo);

        $id = (int)($_POST['id'] ?? 0);
        $nextStatus = self::normalizeStatus((string)($_POST['status'] ?? 'open'));
        if ($id <= 0) {
            header('Location: ' . cms_base_path() . '/forms/submissions');
            exit;
        }

        $st = $pdo->prepare('UPDATE form_submissions SET status = :s WHERE id = :id LIMIT 1');
        $st->execute([':s' => $nextStatus, ':id' => $id]);

        $filter = strtolower(trim((string)($_POST['return_filter'] ?? 'open')));
        if (!in_array($filter, ['open', 'processed', 'deleted', 'all'], true)) {
            $filter = 'open';
        }
        $q = trim((string)($_POST['return_q'] ?? ''));

        $target = cms_base_path() . '/forms/submissions?filter=' . rawurlencode($filter);
        if ($q !== '') {
            $target .= '&q=' . rawurlencode($q);
        }
        header('Location: ' . $target);
        exit;
    }
}
