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
        <section class="surface"><div class="hint-card hint-card--danger"><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div></section>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <section class="surface"><div class="hint-card"><?php echo htmlspecialchars((string)$success, ENT_QUOTES); ?></div></section>
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
        <h2 class="section-title">Rollout starten</h2>
        <?php if (!$github['token_da'] || $github['repo'] === ''): ?>
            <p class="section-copy">Damit dieser Knopf funktioniert, brauchen die Verwaltung folgende Werte in ihrer
               <code>.env</code>: <code>GITHUB_REPO</code> (z.&nbsp;B. <code>SebastianWauer/digiwtal-cms</code>),
               <code>GITHUB_TOKEN</code> (Fine-grained Token mit <em>Actions: Read and write</em>) und optional
               <code>GITHUB_WORKFLOW</code> sowie <code>GITHUB_BRANCH</code>.</p>
        <?php else: ?>
            <p class="section-copy">Startet <code><?php echo htmlspecialchars($github['workflow'], ENT_QUOTES); ?></code>
               in <code><?php echo htmlspecialchars($github['repo'], ENT_QUOTES); ?></code> auf Branch
               <code><?php echo htmlspecialchars($github['branch'], ENT_QUOTES); ?></code>.</p>
            <?php foreach ($kunden as $k): ?>
                <form method="post" action="/admin/ci/dispatch/<?php echo (int)$k['id']; ?>" class="inline-form">
                    <?php echo Csrf::field(); ?>
                    <span class="inline-form__label"><?php echo htmlspecialchars((string)$k['name'], ENT_QUOTES); ?></span>
                    <label><input type="checkbox" name="erstinstallation" value="1"> Erstinstallation</label>
                    <button class="btn btn--warning btn--sm" type="submit">Ausrollen</button>
                </form>
            <?php endforeach; ?>
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
        <table class="table">
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
        </table>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
