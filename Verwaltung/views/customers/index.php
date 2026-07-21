<?php
$title = 'Kunden';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Kunden</h1>
                <p class="page-subtitle">Stammdaten, Health-Signale und Zugriffe pro Kunde verwalten.</p>
            </div>
            <div class="page-actions">
                <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                    <a class="btn btn--primary btn--sm" href="/admin/customers/create">Neuer Kunde</a>
                <?php endif; ?>
                <a class="btn btn--secondary btn--sm" href="/admin/dashboard">Dashboard</a>
            </div>
        </header>

        <?php if (isset($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($customers)): ?>
            <p class="empty-state">Keine Kunden vorhanden.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Name</th>
                            <th>Domain</th>
                            <th>Aktiv</th>
                            <th>Health CMS</th>
                            <th>Health Frontend</th>
                            <th class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <?php
                        $ampel = (string)($c['ampel'] ?? 'red');
                        $ampelClass = match ($ampel) {
                            'green' => 'status-dot--green',
                            'yellow' => 'status-dot--yellow',
                            'red' => 'status-dot--red',
                            default => 'status-dot--muted',
                        };
                        $cmsHealth = (string)($c['health_cms_status'] ?? $c['health_status'] ?? 'unknown');
                        $frontendHealth = (string)($c['health_frontend_status'] ?? 'n/a');
                        $cmsClass = match ($cmsHealth) {
                            'healthy' => 'healthy',
                            'degraded' => 'degraded',
                            'down' => 'down',
                            'timeout' => 'timeout',
                            default => 'unknown',
                        };
                        $frontendClass = match ($frontendHealth) {
                            'healthy' => 'healthy',
                            'degraded' => 'degraded',
                            'down' => 'down',
                            'timeout' => 'timeout',
                            'n/a' => 'na',
                            default => 'unknown',
                        };
                        ?>
                        <tr>
                            <td><span class="status-dot <?php echo $ampelClass; ?>"></span></td>
                            <td>
                                <a class="link-action" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>">
                                    <?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES); ?>
                                </a>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($c['domain'] ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo ((int)($c['is_active'] ?? 0) === 1) ? 'Ja' : 'Nein'; ?></td>
                            <td>
                                <button type="button" class="badge-button badge-button--<?php echo $cmsClass; ?>" data-health-detail="<?php echo htmlspecialchars((string)($c['health_cms_detail'] ?? ''), ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($cmsHealth, ENT_QUOTES); ?>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="badge-button badge-button--<?php echo $frontendClass; ?>" data-health-detail="<?php echo htmlspecialchars((string)($c['health_frontend_detail'] ?? ''), ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($frontendHealth, ENT_QUOTES); ?>
                                </button>
                            </td>
                            <td class="text-right">
                                <div class="table-actions">
                                    <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                                        <a class="link-action link-action--info" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/edit">Bearbeiten</a>
                                    <?php endif; ?>
                                    <a class="link-action" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/modules">Module</a>
                                    <a class="link-action link-action--success" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/vault">Tresor</a>
                                    <a class="link-action link-action--info" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/access">Zugang</a>
                                    <a class="link-action link-action--warning" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/deployments">Deploy</a>
                                    <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                                        <form method="POST" action="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>/toggle" class="table-inline-form">
                                            <?php echo Csrf::field(); ?>
                                            <button type="submit" class="btn btn--linkish link-action link-action--danger">
                                                <?php echo ((int)($c['is_active'] ?? 0) === 1) ? 'Deaktivieren' : 'Aktivieren'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
