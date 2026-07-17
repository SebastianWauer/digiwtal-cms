<?php
declare(strict_types=1);

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function admin_version(): string
{
    $verFile = __DIR__ . '/../../config/version.php';
    $ver = is_file($verFile) ? require $verFile : [];
    if (!is_array($ver)) $ver = [];
    return (string)($ver['cms_version'] ?? '—');
}

/**
 * Gibt eine lesbare Rollen-Beschriftung für die Sidebar zurück.
 * Quelle: DB (user_roles -> roles).
 *
 * Regeln:
 * - Nur nicht-gelöschte Rollen (roles.is_deleted = 0 OR NULL)
 * - Mehrere Rollen werden als "Rolle1, Rolle2" angezeigt
 * - Wenn keine Rolle: "—"
 */
function admin_role_label(array $user): string
{
    static $cache = []; // userId => label

    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) return '—';

    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    try {
        // admin_pdo() ist im Bootstrap vor components.php geladen
        $pdo = admin_pdo();

        $stmt = $pdo->prepare("
            SELECT r.name
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
              AND (r.is_deleted = 0 OR r.is_deleted IS NULL)
            ORDER BY r.name ASC
        ");
        $stmt->execute([':uid' => $uid]);
        $rows = $stmt->fetchAll();

        $names = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $n = trim((string)($r['name'] ?? ''));
                if ($n !== '') $names[] = $n;
            }
        }

        $names = array_values(array_unique($names));
        $label = $names ? implode(', ', $names) : '—';

        $cache[$uid] = $label;
        return $label;
    } catch (\Throwable $e) {
        $cache[$uid] = '—';
        return '—';
    }
}

function flash_render(?array $flash): void
{
    if (!is_array($flash)) return;
    $type = (string)($flash['type'] ?? 'ok');
    $msg  = (string)($flash['msg'] ?? '');
    if ($msg === '') return;

    $type = in_array($type, ['ok','error'], true) ? $type : 'ok';

    // Flash-Nachricht HTML
    echo '<div class="flash flash--' . h($type) . '">';
    echo h($msg);
    echo '</div>';

    // JavaScript zum Ausblenden nach 3 Sekunden
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const flashMessage = document.querySelector(".flash");
            if (flashMessage) {
                setTimeout(function() {
                    flashMessage.style.opacity = 0; // Fade out
                    setTimeout(function() {
                        flashMessage.style.display = "none"; // Entfernt die Nachricht nach dem Ausblenden
                    }, 500); // Warte, bis die Animation vorbei ist
                }, 3000); // 3 Sekunden warten
            }
        });
    </script>';
}

function site_favicon_url(): ?string
{
    static $cached = null;
    if ($cached !== null) return $cached;

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT `value`
            FROM site_settings
            WHERE `key` = 'favicon_media_id'
            LIMIT 1
        ");
        $stmt->execute();
        $id = (int)$stmt->fetchColumn();

        if ($id > 0) {
            $cached = '/media/file?id=' . $id;
            return $cached;
        }
    } catch (\Throwable $e) {
        // bewusst still – kein Favicon ist kein kritischer Fehler
    }

    $cached = null;
    return null;
}
function site_cms_logo_url(string $theme): ?string
{
    static $cache = [];

    if (isset($cache[$theme])) {
        return $cache[$theme];
    }

    try {
        $pdo = db();
        $key = ($theme === 'light')
            ? 'cms_logo_light_media_id'
            : 'cms_logo_dark_media_id';

        $stmt = $pdo->prepare("
            SELECT `value`
            FROM site_settings
            WHERE `key` = :k
            LIMIT 1
        ");
        $stmt->execute([':k' => $key]);
        $id = (int)$stmt->fetchColumn();

        if ($id > 0) {
            return $cache[$theme] = '/media/file?id=' . $id;
        }
    } catch (\Throwable $e) {
        // bewusst still
    }

    return $cache[$theme] = null;
}
