<?php
$title = 'Kunde: ' . htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES);
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <div class="customer-title">
                    <h1 class="page-title"><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></h1>
                    <span class="customer-domain"><?php echo htmlspecialchars((string)($customer['domain'] ?? ''), ENT_QUOTES); ?></span>
                </div>
                <?php
                $aboStatus = (string)($customer['abo_status'] ?? 'inactive');
                $aboClass = $aboStatus === 'active' ? 'healthy' : 'down';
                $isActive = (int)($customer['is_active'] ?? 0) === 1;
                ?>
                <div class="header-meta">
                    <span class="status-pill status-pill--<?php echo $aboClass; ?>"><?php echo htmlspecialchars(strtoupper($aboStatus), ENT_QUOTES); ?></span>
                    <span class="mini-status"><?php echo $isActive ? 'Aktiv' : 'Inaktiv'; ?></span>
                </div>
            </div>
            <div class="page-actions">
                <?php if (($_SESSION['admin_role'] ?? '') === 'superadmin'): ?>
                    <a class="btn btn--info btn--sm" href="/admin/customers/<?php echo (int)$customer['id']; ?>/edit">Bearbeiten</a>
                <?php endif; ?>
                <a class="btn btn--warning btn--sm" href="/admin/customers/<?php echo (int)$customer['id']; ?>/deployments">Deploy</a>
                <a class="btn btn--secondary btn--sm" href="/admin/customers/<?php echo (int)$customer['id']; ?>/access">Zugang</a>
                <a class="btn btn--success btn--sm" href="/admin/customers/<?php echo (int)$customer['id']; ?>/webhooks">Webhooks</a>
                <a class="btn btn--secondary btn--sm" href="/admin/customers">Kunden</a>
            </div>
        </header>
    </section>

    <section class="surface">
        <h2 class="section-title">Aktueller Status</h2>
        <?php if ($latestCheck): ?>
            <?php
            $cmsStatus = (string)($latestCheck['cms_status'] ?? $latestCheck['status'] ?? 'unknown');
            $frontendStatus = (string)($latestCheck['frontend_status'] ?? 'n/a');
            $cmsClass = match ($cmsStatus) {
                'healthy' => 'healthy',
                'degraded' => 'degraded',
                default => 'down',
            };
            $frontendClass = match ($frontendStatus) {
                'healthy' => 'healthy',
                'degraded' => 'degraded',
                'n/a' => 'unknown',
                default => 'down',
            };
            ?>
            <div class="stats-hero">
                <div class="stats-hero__signal-group">
                    <div class="stats-hero__signal">
                        <span class="stats-hero__signal-dot stats-hero__signal-dot--<?php echo $cmsClass; ?>"></span>
                        <div class="stats-hero__signal-label">CMS <?php echo htmlspecialchars(strtoupper($cmsStatus), ENT_QUOTES); ?></div>
                    </div>
                    <div class="stats-hero__signal">
                        <span class="stats-hero__signal-dot stats-hero__signal-dot--<?php echo $frontendClass; ?>"></span>
                        <div class="stats-hero__signal-label">Frontend <?php echo htmlspecialchars(strtoupper($frontendStatus), ENT_QUOTES); ?></div>
                    </div>
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-item__label">Response CMS</div>
                        <div class="detail-item__value"><?php echo (int)($latestCheck['response_ms'] ?? 0); ?>ms</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item__label">Response Frontend</div>
                        <div class="detail-item__value">
                            <?php
                            $frontendResponse = $latestCheck['frontend_response_ms'] ?? null;
                            echo $frontendResponse !== null ? ((int)$frontendResponse . 'ms') : '—';
                            ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item__label">CMS Version</div>
                        <div class="detail-item__value"><?php echo htmlspecialchars((string)($latestCheck['cms_version'] ?? '—'), ENT_QUOTES); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item__label">PHP</div>
                        <div class="detail-item__value"><?php echo htmlspecialchars((string)($latestCheck['php_version'] ?? '—'), ENT_QUOTES); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-item__label">Letzter Check</div>
                        <div class="detail-item__value"><?php echo htmlspecialchars((string)($latestCheck['checked_at'] ?? '—'), ENT_QUOTES); ?></div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="empty-state">Noch kein Health-Check durchgeführt.</p>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2 class="section-title">Health-Verlauf</h2>
        <p class="section-copy">Die letzten 50 Checks inklusive Response-Zeit und Runtime-Versionen.</p>
        <?php if (empty($healthHistory)): ?>
            <p class="empty-state">Noch keine Einträge.</p>
        <?php else: ?>
            <div class="timeline-bars">
                <?php foreach (array_reverse($healthHistory) as $hc): ?>
                    <?php
                    $s = (string)($hc['status'] ?? 'unknown');
                    $timelineClass = match ($s) {
                        'healthy' => 'timeline-bar--healthy',
                        'degraded' => 'timeline-bar--degraded',
                        default => 'timeline-bar--down',
                    };
                    $tip = htmlspecialchars($s . ' – ' . ($hc['checked_at'] ?? ''), ENT_QUOTES);
                    ?>
                    <div class="timeline-bar <?php echo $timelineClass; ?>" title="<?php echo $tip; ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>CMS</th>
                            <th>Frontend</th>
                            <th>Zeitpunkt</th>
                            <th class="text-right">Resp. CMS</th>
                            <th class="text-right">Resp. Frontend</th>
                            <th>CMS</th>
                            <th>PHP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($healthHistory as $hc): ?>
                        <?php
                        $cmsStatus = (string)($hc['cms_status'] ?? $hc['status'] ?? 'unknown');
                        $frontendStatus = (string)($hc['frontend_status'] ?? 'n/a');
                        $cmsBadgeClass = match ($cmsStatus) {
                            'healthy' => 'healthy',
                            'degraded' => 'degraded',
                            default => 'down',
                        };
                        $frontendBadgeClass = match ($frontendStatus) {
                            'healthy' => 'healthy',
                            'degraded' => 'degraded',
                            'n/a' => 'unknown',
                            default => 'down',
                        };
                        ?>
                        <tr>
                            <td><span class="status-pill status-pill--<?php echo $cmsBadgeClass; ?>"><?php echo htmlspecialchars(strtoupper($cmsStatus), ENT_QUOTES); ?></span></td>
                            <td><span class="status-pill status-pill--<?php echo $frontendBadgeClass; ?>"><?php echo htmlspecialchars(strtoupper($frontendStatus), ENT_QUOTES); ?></span></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($hc['checked_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-right mono"><?php echo (int)($hc['response_ms'] ?? 0); ?>ms</td>
                            <td class="text-right mono">
                                <?php
                                $frontendHistoryResponse = $hc['frontend_response_ms'] ?? null;
                                echo $frontendHistoryResponse !== null ? ((int)$frontendHistoryResponse . 'ms') : '—';
                                ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($hc['cms_version'] ?? '—'), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($hc['php_version'] ?? '—'), ENT_QUOTES); ?></td>
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
