<?php
$title = 'Dashboard';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Kunden-Übersicht</h1>
                <p class="page-subtitle">Alle aktiven Kunden, Health-Signale und Runtime-Daten auf einen Blick.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/audit">Audit-Log</a>
                <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                    <a class="btn btn--info btn--sm" href="/admin/admin-users">Admins</a>
                <?php endif; ?>
                <a class="btn btn--primary btn--sm" href="/admin/customers">Kunden verwalten</a>
            </div>
        </header>

        <?php if (empty($customers)): ?>
            <p class="empty-state">Keine Kunden vorhanden.</p>
        <?php else: ?>
            <div class="dashboard-card-grid">
                <?php foreach ($customers as $c): ?>
                    <?php
                    $ampel = (string)($c['ampel'] ?? 'red');
                    $dashboardClass = match ($ampel) {
                        'green' => 'dashboard-card--green',
                        'yellow' => 'dashboard-card--yellow',
                        'red' => 'dashboard-card--red',
                        default => 'dashboard-card--muted',
                    };
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
                    $lastCheck = trim((string)($c['last_check_at'] ?? ''));
                    $lastSuccessful = trim((string)($c['last_successful_health_at'] ?? ''));
                    $lastDeployAt = trim((string)($c['last_deploy_at'] ?? ''));
                    $lastDeployStatus = trim((string)($c['last_deploy_status'] ?? ''));
                    $lastDeployType = trim((string)($c['last_deploy_type'] ?? ''));
                    $staleHealth = (bool)($c['stale_health'] ?? false);
                    $lastCheckLabel = '—';
                    if ($lastCheck !== '') {
                        $checkTs = strtotime($lastCheck);
                        if ($checkTs !== false) {
                            $diff = time() - $checkTs;
                            $lastCheckLabel = $diff < 3600 ? ('vor ' . ceil($diff / 60) . ' Min') : date('d.m.Y H:i', $checkTs);
                        }
                    }
                    $lastSuccessfulLabel = 'kein erfolgreicher Check';
                    if ($lastSuccessful !== '') {
                        $successTs = strtotime($lastSuccessful);
                        if ($successTs !== false) {
                            $diff = time() - $successTs;
                            $lastSuccessfulLabel = $diff < 3600 ? ('vor ' . ceil($diff / 60) . ' Min') : date('d.m.Y H:i', $successTs);
                        }
                    }
                    $lastDeployLabel = 'noch kein Deploy';
                    if ($lastDeployAt !== '') {
                        $deployTs = strtotime($lastDeployAt);
                        if ($deployTs !== false) {
                            $dateText = $deployTs !== false ? date('d.m.Y H:i', $deployTs) : $lastDeployAt;
                            $statusText = $lastDeployStatus !== '' ? $lastDeployStatus : 'unknown';
                            $typeText = $lastDeployType !== '' ? $lastDeployType : 'deploy';
                            $lastDeployLabel = strtoupper($typeText) . ' · ' . $statusText . ' · ' . $dateText;
                        }
                    }
                    ?>
                    <article class="dashboard-card <?php echo $dashboardClass; ?>">
                        <div class="dashboard-card__header">
                            <div class="dashboard-card__title-wrap">
                                <span class="status-dot <?php echo $ampelClass; ?>"></span>
                                <div>
                                    <h2 class="dashboard-card__title"><?php echo htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES); ?></h2>
                                    <div class="dashboard-card__domain"><?php echo htmlspecialchars((string)($c['domain'] ?? ''), ENT_QUOTES); ?></div>
                                </div>
                            </div>
                            <a class="btn btn--ghost btn--sm" href="/admin/customers/<?php echo (int)($c['id'] ?? 0); ?>">Öffnen</a>
                        </div>

                        <div class="dashboard-card__status-row">
                            <button type="button" class="badge-button badge-button--<?php echo $cmsClass; ?>" data-health-detail="<?php echo htmlspecialchars((string)($c['health_cms_detail'] ?? ''), ENT_QUOTES); ?>">
                                CMS: <?php echo htmlspecialchars($cmsHealth, ENT_QUOTES); ?>
                            </button>
                            <button type="button" class="badge-button badge-button--<?php echo $frontendClass; ?>" data-health-detail="<?php echo htmlspecialchars((string)($c['health_frontend_detail'] ?? ''), ENT_QUOTES); ?>">
                                Frontend: <?php echo htmlspecialchars($frontendHealth, ENT_QUOTES); ?>
                            </button>
                        </div>

                        <dl class="dashboard-card__meta">
                            <div>
                                <dt>Letzter Check</dt>
                                <dd><?php echo htmlspecialchars($lastCheckLabel, ENT_QUOTES); ?></dd>
                            </div>
                            <div>
                                <dt>Letzter erfolgreicher Check</dt>
                                <dd class="<?php echo $staleHealth ? 'dashboard-card__meta-value--stale' : ''; ?>">
                                    <?php echo htmlspecialchars($lastSuccessfulLabel, ENT_QUOTES); ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Letzter Deploy</dt>
                                <dd><?php echo htmlspecialchars($lastDeployLabel, ENT_QUOTES); ?></dd>
                            </div>
                            <div>
                                <dt>Runtime</dt>
                                <dd><?php echo (int)($c['response_ms'] ?? 0); ?>ms · CMS <?php echo htmlspecialchars((string)($c['cms_version'] ?? '-'), ENT_QUOTES); ?> · PHP <?php echo htmlspecialchars((string)($c['php_version'] ?? 'n/a'), ENT_QUOTES); ?></dd>
                            </div>
                        </dl>

                        <?php if ($staleHealth): ?>
                            <div class="hint-card hint-card--warning">
                                Seit mehr als 30 Minuten kein erfolgreicher Health-Check.
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
