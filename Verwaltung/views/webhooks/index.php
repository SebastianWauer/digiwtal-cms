<?php
$title = 'Webhooks: ' . htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES);
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Webhook-Tokens</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/customers/<?php echo (int)$customer['id']; ?>">Kunde</a>
            </div>
        </header>
    </section>

    <?php if ($newToken): ?>
        <section class="surface">
            <div class="hint-card hint-card--warning">
                <h2 class="section-title">Token einmalig anzeigen</h2>
                <p class="section-copy">Dieser Token wird nur jetzt einmal angezeigt und ist danach nicht mehr lesbar.</p>
                <code class="code-block"><?php echo htmlspecialchars($newToken, ENT_QUOTES); ?></code>
                <p class="section-copy">Verwendung: <code>curl -X POST https://verwaltung.digiwtal.de/webhook/deploy -H "X-Webhook-Token: &lt;token&gt;"</code></p>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($success && !$newToken): ?>
        <div class="alert alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert--error">
            <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e, ENT_QUOTES); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="surface">
        <h2 class="section-title">Neuen Webhook-Token erstellen</h2>
        <form method="POST" action="/admin/customers/<?php echo (int)$customer['id']; ?>/webhooks" class="form-stack">
            <?php echo Csrf::field(); ?>
            <div class="form-grid form-grid--label-inline">
                <div class="field">
                    <label for="label">Label</label>
                    <input class="input" type="text" id="label" name="label" placeholder="z.B. GitHub Actions" maxlength="100">
                </div>
                <div class="field">
                    <label for="deploy_type">Deploy-Typ</label>
                    <select class="select" id="deploy_type" name="deploy_type">
                        <option value="cms">CMS</option>
                        <option value="frontend">Frontend</option>
                        <option value="full">Full (CMS + Frontend)</option>
                    </select>
                </div>
            </div>
            <div class="submit-row">
                <button class="btn btn--primary" type="submit">Token generieren</button>
            </div>
        </form>
    </section>

    <section class="surface">
        <h2 class="section-title">Aktive Tokens (<?php echo count($tokens); ?>)</h2>
        <?php if (empty($tokens)): ?>
            <p class="empty-state">Noch keine Webhook-Tokens.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Typ</th>
                            <th>Zuletzt genutzt</th>
                            <th>Letzter Deploy</th>
                            <th>Erstellt</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tokens as $t): ?>
                        <?php
                        $lastDeploymentId = (int)($t['last_deployment_id'] ?? 0);
                        $lastDeploymentStatus = trim((string)($t['last_deployment_status'] ?? ''));
                        $lastDeploymentCreatedAt = trim((string)($t['last_deployment_created_at'] ?? ''));
                        $lastDeploymentStatusClass = match ($lastDeploymentStatus) {
                            'success' => 'healthy',
                            'running' => 'degraded',
                            'failed' => 'down',
                            'rolled_back' => 'timeout',
                            default => 'unknown',
                        };
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($t['label'] ?? ''), ENT_QUOTES); ?></td>
                            <td><span class="status-pill status-pill--unknown"><?php echo htmlspecialchars(strtoupper((string)($t['deploy_type'] ?? '')), ENT_QUOTES); ?></span></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($t['last_used_at'] ?? '—'), ENT_QUOTES); ?></td>
                            <td>
                                <?php if ($lastDeploymentId > 0): ?>
                                    <div>
                                        <a class="link-action" href="/admin/customers/<?php echo (int)$customer['id']; ?>/deployments#deployment-<?php echo $lastDeploymentId; ?>">
                                            Deploy #<?php echo $lastDeploymentId; ?>
                                        </a>
                                    </div>
                                    <div>
                                        <span class="status-pill status-pill--<?php echo $lastDeploymentStatusClass; ?>">
                                            <?php echo htmlspecialchars($lastDeploymentStatus !== '' ? $lastDeploymentStatus : 'unknown', ENT_QUOTES); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted">
                                        <?php echo htmlspecialchars($lastDeploymentCreatedAt !== '' ? $lastDeploymentCreatedAt : '—', ENT_QUOTES); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Noch kein zugeordneter Deploy</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($t['created_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td>
                                <form method="POST" action="/admin/webhooks/<?php echo (int)$t['id']; ?>/delete" class="table-inline-form" onsubmit="return confirm('Token wirklich löschen?')">
                                    <?php echo Csrf::field(); ?>
                                    <button class="btn btn--linkish link-action link-action--danger" type="submit">Löschen</button>
                                </form>
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
