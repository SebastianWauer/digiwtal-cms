<?php
$title = 'CI-Tokens';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Rollout über GitHub</h1>
                <p class="page-subtitle">Tokens für die Pipeline und Start eines Rollouts</p>
            </div>
        </header>
    </section>

    <?php foreach ($errors as $e): ?>
        <section class="surface"><div class="alert alert--error"><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div></section>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <section class="surface"><div class="alert alert--success"><?php echo htmlspecialchars((string)$success, ENT_QUOTES); ?></div></section>
    <?php endif; ?>

    <?php if ($newToken): ?>
        <section class="surface">
            <div class="hint-card hint-card--warning">
                <h2 class="section-title">Token einmalig anzeigen</h2>
                <p class="section-copy">Dieser Token wird nur jetzt angezeigt und ist danach nicht mehr lesbar.
                   Trag ihn in GitHub unter <em>Settings → Secrets and variables → Actions</em> als
                   <code>VERWALTUNG_CI_TOKEN</code> ein.</p>
                <code class="code-block"><?php echo htmlspecialchars($newToken, ENT_QUOTES); ?></code>
            </div>
        </section>
    <?php endif; ?>

    <section class="surface">
        <h2 class="section-title">Monitoring-Diagnose</h2>
        <p class="section-copy">Diesen vom Server ermittelten Pfad ohne weitere Argumente in den IONOS-Cronjob eintragen:</p>
        <code class="code-block"><?php echo htmlspecialchars((string)$cronInfo['path'], ENT_QUOTES); ?></code>
        <dl class="detail-grid">
            <div class="detail-item">
                <dt class="detail-item__label">Datei</dt>
                <dd class="detail-item__value"><?php echo $cronInfo['exists'] ? 'vorhanden' : 'fehlt'; ?></dd>
            </div>
            <div class="detail-item">
                <dt class="detail-item__label">Ausführbar</dt>
                <dd class="detail-item__value"><?php echo $cronInfo['executable'] ? 'ja' : 'nein'; ?></dd>
            </div>
            <div class="detail-item">
                <dt class="detail-item__label">Web-PHP</dt>
                <dd class="detail-item__value mono"><?php echo htmlspecialchars(
                    (string)$cronInfo['php_binary'] . ' · ' . (string)$cronInfo['php_sapi'],
                    ENT_QUOTES
                ); ?></dd>
            </div>
        </dl>
        <form method="post" action="/admin/ci/health-run">
            <?php echo Csrf::field(); ?>
            <button class="btn btn--primary btn--sm" type="submit">Prüflauf jetzt</button>
        </form>
    </section>

    <section class="surface">
        <h2 class="section-title">Rollout starten</h2>
        <?php if (!$github['token_da'] || $github['repo'] === ''): ?>
            <p class="section-copy">Damit dieser Knopf funktioniert, brauchen die Verwaltung folgende Werte in ihrer
               <code>.env</code>: <code>GITHUB_REPO</code> (z.&nbsp;B. <code>SebastianWauer/digiwtal-cms</code>),
               <code>GITHUB_TOKEN</code> (Fine-grained Token mit <em>Actions: Read and write</em>) und optional
               <code>GITHUB_WORKFLOW</code> sowie <code>GITHUB_BRANCH</code>.</p>
        <?php else: ?>
            <p class="section-copy">
                Rollt den aktuellen Stand von Branch <code><?php echo htmlspecialchars($github['branch'], ENT_QUOTES); ?></code>
                an alle freigeschalteten Kunden mit wirksamem Abo und vollständiger Konfiguration aus.
                Migrationen werden automatisch ausgeführt.
            </p>

            <div class="detail-grid">
                <div class="detail-item">
                    <dt class="detail-item__label">Bereit</dt>
                    <dd class="detail-item__value"><?php echo count($rollout['ready']); ?> Kunde(n)</dd>
                </div>
                <div class="detail-item">
                    <dt class="detail-item__label">Übersprungen</dt>
                    <dd class="detail-item__value"><?php echo count($rollout['skipped']); ?> Kunde(n)</dd>
                </div>
            </div>

            <?php if ($rollout['ready'] !== []): ?>
                <p class="section-copy">
                    <strong>Teilnehmende Kunden:</strong>
                    <?php echo htmlspecialchars(implode(', ', array_column($rollout['ready'], 'name')), ENT_QUOTES); ?>
                </p>
                <form method="post" action="/admin/ci/dispatch">
                    <?php echo Csrf::field(); ?>
                    <button class="btn btn--warning" type="submit">Aktuellen Stand an alle aktiven Kunden ausrollen</button>
                </form>
            <?php else: ?>
                <div class="hint-card hint-card--warning">Aktuell erfüllt kein Kunde alle Rollout-Voraussetzungen.</div>
            <?php endif; ?>

            <?php if ($rollout['skipped'] !== []): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Übersprungener Kunde</th><th>Grund</th></tr></thead>
                        <tbody>
                        <?php foreach ($rollout['skipped'] as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$customer['name'], ENT_QUOTES); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars((string)$customer['rollout_skip_reason'], ENT_QUOTES); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2 class="section-title">Neues CI-Token</h2>
        <form method="post" action="/admin/ci-tokens">
            <?php echo Csrf::field(); ?>
            <input class="input" type="text" name="label" placeholder="Bezeichnung, z. B. github-actions" maxlength="100">
            <button class="btn btn--primary btn--sm" type="submit">Token erzeugen</button>
        </form>
    </section>

    <section class="surface">
        <h2 class="section-title">Vorhandene Tokens</h2>
        <?php if ($tokens === []): ?>
            <p class="section-copy">Noch keine Tokens angelegt.</p>
        <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Bezeichnung</th><th>Zuletzt genutzt</th><th>Von</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tokens as $t): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$t['label'], ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars((string)($t['last_used_at'] ?? 'nie'), ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars((string)($t['last_used_ip'] ?? ''), ENT_QUOTES); ?></td>
                    <td><?php echo $t['revoked_at'] ? 'widerrufen' : 'aktiv'; ?></td>
                    <td>
                        <?php if (!$t['revoked_at']): ?>
                        <form method="post" action="/admin/ci-tokens/<?php echo (int)$t['id']; ?>/revoke">
                            <?php echo Csrf::field(); ?>
                            <button class="btn btn--linkish link-action link-action--danger" type="submit">Widerrufen</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
