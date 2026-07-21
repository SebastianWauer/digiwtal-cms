<?php
declare(strict_types=1);

function verwaltung_asset(string $path): string
{
    $clean = ltrim($path, '/');
    $absolute = __DIR__ . '/../public/' . $clean;
    $version = is_file($absolute) ? (string)filemtime($absolute) : '';
    return '/' . $clean . ($version !== '' ? ('?v=' . $version) : '');
}

function verwaltung_active_nav(string $uri): string
{
    return match (true) {
        str_starts_with($uri, '/admin/dashboard') => 'dashboard',
        str_starts_with($uri, '/admin/customers') => 'customers',
        str_starts_with($uri, '/admin/modules') => 'modules',
        str_starts_with($uri, '/admin/admin-users') => 'admins',
        str_starts_with($uri, '/admin/audit') => 'audit',
        default => '',
    };
}

$title = (string)($title ?? 'Verwaltung');
$titleClean = trim($title);
$tabTitle = ($titleClean === '' || $titleClean === 'Verwaltung')
    ? 'Verwaltung'
    : $titleClean . ' - Verwaltung';
$layoutMode = (string)($layoutMode ?? 'admin');
$requestPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$activeNav = (string)($activeNav ?? verwaltung_active_nav($requestPath));
$isSuperadmin = (($_SESSION['admin_role'] ?? '') === 'superadmin');
$adminEmail = (string)($_SESSION['admin_email'] ?? '');
$adminRole = $isSuperadmin ? 'Superadmin' : 'Operator';
?>
<!DOCTYPE html>
<html lang="de" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f1012">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Verwaltung">
    <link rel="manifest" href="/manifest.json">
    <title><?php echo htmlspecialchars($tabTitle, ENT_QUOTES); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(verwaltung_asset('assets/css/admin-layout.css'), ENT_QUOTES); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(verwaltung_asset('assets/css/admin-sidebar.css'), ENT_QUOTES); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(verwaltung_asset('assets/css/admin-components.css'), ENT_QUOTES); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(verwaltung_asset('assets/css/admin-app.css'), ENT_QUOTES); ?>">
</head>
<body class="<?php echo $layoutMode === 'auth' ? 'auth-page' : ''; ?>">
<?php if ($layoutMode === 'auth'): ?>
    <main class="auth-shell">
        <section class="auth-card">
            <div class="auth-brand" aria-label="DIGIWTAL Verwaltung">
                <img class="auth-logo" src="<?php echo htmlspecialchars(verwaltung_asset('assets/img/DIGIWTAL_hell.svg'), ENT_QUOTES); ?>" alt="DIGIWTAL">
            </div>
            <div class="auth-content">
                <?php echo $content; ?>
            </div>
            <div class="auth-footer">
                &copy; <?php echo htmlspecialchars(date('Y'), ENT_QUOTES); ?> DIGIWTAL Verwaltung
            </div>
        </section>
    </main>
<?php else: ?>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand">
                <img class="brand__logo" src="<?php echo htmlspecialchars(verwaltung_asset('assets/img/DIGIWTAL_hell.svg'), ENT_QUOTES); ?>" alt="DIGIWTAL">
            </div>

            <nav class="nav">
                <a class="nav__item <?php echo $activeNav === 'dashboard' ? 'is-active' : ''; ?>" href="/admin/dashboard">
                    <span class="nav__icon">▦</span>
                    <span>Dashboard</span>
                </a>
                <a class="nav__item <?php echo $activeNav === 'customers' ? 'is-active' : ''; ?>" href="/admin/customers">
                    <span class="nav__icon">◎</span>
                    <span>Kunden</span>
                </a>
                <a class="nav__item <?php echo $activeNav === 'modules' ? 'is-active' : ''; ?>" href="/admin/modules">
                    <span class="nav__icon">◫</span>
                    <span>Module</span>
                </a>
                <?php if ($isSuperadmin): ?>
                    <a class="nav__item <?php echo $activeNav === 'admins' ? 'is-active' : ''; ?>" href="/admin/admin-users">
                        <span class="nav__icon">◌</span>
                        <span>Admins</span>
                    </a>
                <?php endif; ?>
                <a class="nav__item <?php echo $activeNav === 'audit' ? 'is-active' : ''; ?>" href="/admin/audit">
                    <span class="nav__icon">≣</span>
                    <span>Audit-Log</span>
                </a>
            </nav>

            <div class="sidebar__spacer"></div>

            <div class="sidebar-footer">
                <button type="button" id="pushToggleBtn" class="btn btn--secondary btn--sm btn--block">Push aktivieren</button>
                <div class="userbox">
                    <div class="userbox__name"><?php echo htmlspecialchars($adminEmail !== '' ? $adminEmail : 'Admin', ENT_QUOTES); ?></div>
                    <div class="userbox__role"><?php echo htmlspecialchars($adminRole, ENT_QUOTES); ?></div>
                </div>
                <a class="btn btn--ghost btn--block" href="/admin/logout">Logout</a>
            </div>
        </aside>

        <main class="main">
            <section class="panel">
                <div class="panel-body">
                    <?php echo $content; ?>
                </div>
            </section>
        </main>
    </div>

    <div class="status-modal" id="statusDetailModal" hidden>
        <div class="status-modal__backdrop" data-close-modal="1"></div>
        <div class="status-modal__panel">
            <div class="status-modal__head">
                <h2 class="status-modal__title">Health-Details</h2>
                <button type="button" class="btn btn--secondary btn--sm" data-close-modal="1">Schließen</button>
            </div>
            <div class="status-modal__body" id="statusDetailBody"></div>
        </div>
    </div>

    <script>
    (function () {
        var modal = document.getElementById('statusDetailModal');
        var body = document.getElementById('statusDetailBody');
        if (modal && body) {
            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-health-detail]');
                if (trigger) {
                    body.textContent = trigger.getAttribute('data-health-detail') || 'Keine Details vorhanden.';
                    modal.hidden = false;
                    return;
                }
                if (event.target.closest('[data-close-modal]')) {
                    modal.hidden = true;
                }
            });
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
            });
        }

        var pushBtn = document.getElementById('pushToggleBtn');

        function updatePushButton(subscribed) {
            if (!pushBtn) return;
            pushBtn.textContent = subscribed ? 'Push deaktivieren' : 'Push aktivieren';
            pushBtn.classList.toggle('btn--success', subscribed);
            pushBtn.classList.toggle('btn--secondary', !subscribed);
        }

        window.togglePushNotifications = function () {
            if (!('PushManager' in window) || !navigator.serviceWorker) {
                window.alert('Push-Notifications werden in diesem Browser nicht unterstützt.');
                return;
            }

            navigator.serviceWorker.ready.then(function (registration) {
                registration.pushManager.getSubscription().then(function (subscription) {
                    if (subscription) {
                        subscription.unsubscribe().then(function () {
                            fetch('/admin/push/unsubscribe', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ endpoint: subscription.endpoint })
                            });
                            updatePushButton(false);
                        });
                        return;
                    }

                    fetch('/admin/push/vapid-public')
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (!data.publicKey) return null;
                            var key = Uint8Array.from(
                                atob(data.publicKey.replace(/-/g, '+').replace(/_/g, '/')),
                                function (char) { return char.charCodeAt(0); }
                            );
                            return registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: key
                            });
                        })
                        .then(function (subscription) {
                            if (!subscription) return null;
                            return fetch('/admin/push/subscribe', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(subscription)
                            });
                        })
                        .then(function () {
                            updatePushButton(true);
                        })
                        .catch(function () {});
                });
            });
        };

        if (pushBtn) {
            pushBtn.addEventListener('click', window.togglePushNotifications);
            if ('PushManager' in window && navigator.serviceWorker) {
                navigator.serviceWorker.ready.then(function (registration) {
                    registration.pushManager.getSubscription().then(function (subscription) {
                        updatePushButton(!!subscription);
                    });
                });
            }
        }
    })();
    </script>
<?php endif; ?>
<?php if (!empty($extraScripts ?? '')): ?>
    <?php echo $extraScripts; ?>
<?php endif; ?>
</body>
</html>
