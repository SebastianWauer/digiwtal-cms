<?php
$title = 'Admin-Benutzer';
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Admin-Benutzer</h1>
                <p class="page-subtitle">Zugänge für Operatoren und Superadmins verwalten.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--primary btn--sm" href="/admin/admin-users/create">Einladen</a>
                <a class="btn btn--secondary btn--sm" href="/admin/dashboard">Dashboard</a>
            </div>
        </header>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars((string)$_SESSION['flash_success'], ENT_QUOTES); unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_errors'])): ?>
            <div class="alert alert--error">
                <?php foreach ($_SESSION['flash_errors'] as $e): ?>
                    <div><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div>
                <?php endforeach; unset($_SESSION['flash_errors']); ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Letzter Login</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $isSelf = (int)($u['id'] ?? 0) === (int)($_SESSION['admin_id'] ?? -1);
                    $roleClass = (($u['role'] ?? '') === 'superadmin') ? 'superadmin' : 'operator';
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES); ?>
                            <?php if ($isSelf): ?><span class="text-muted"> (du)</span><?php endif; ?>
                        </td>
                        <td><span class="status-pill status-pill--<?php echo $roleClass; ?>"><?php echo htmlspecialchars((string)($u['role'] ?? 'operator'), ENT_QUOTES); ?></span></td>
                        <td class="text-muted"><?php echo htmlspecialchars((string)($u['last_login_at'] ?? '—'), ENT_QUOTES); ?></td>
                        <td>
                            <a class="btn btn--ghost btn--sm" href="/admin/admin-users/<?php echo (int)$u['id']; ?>">Details</a>
                            <?php if (!$isSelf): ?>
                                <form method="POST" action="/admin/admin-users/<?php echo (int)$u['id']; ?>/delete" class="table-inline-form" onsubmit="return confirm('Admin-Benutzer wirklich löschen?')">
                                    <?php echo Csrf::field(); ?>
                                    <button class="btn btn--linkish link-action link-action--danger" type="submit">Löschen</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
