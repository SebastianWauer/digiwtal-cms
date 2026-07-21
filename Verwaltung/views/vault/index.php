<?php
$title = 'Server-Tresor';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Server-Tresor</h1>
                <p class="page-subtitle">Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong></p>
            </div>
            <div class="page-actions">
                <a class="btn btn--primary btn--sm" href="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/vault/create">Neu</a>
                <a class="btn btn--secondary btn--sm" href="/admin/customers">Kunden</a>
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

        <?php if (empty($entries)): ?>
            <p class="empty-state">Keine Zugangsdaten vorhanden.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Host</th>
                            <th>Username</th>
                            <th>Aktualisiert</th>
                            <th class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($e['label'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($e['host'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($e['username'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($e['updated_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-right">
                                <div class="table-actions">
                                    <form method="POST" action="/admin/vault/<?php echo (int)($e['id'] ?? 0); ?>/reveal" class="table-inline-form">
                                        <?php echo Csrf::field(); ?>
                                        <button class="btn btn--linkish link-action link-action--info" type="submit">Anzeigen</button>
                                    </form>
                                    <form method="POST" action="/admin/vault/<?php echo (int)($e['id'] ?? 0); ?>/rotate" class="table-inline-form">
                                        <?php echo Csrf::field(); ?>
                                        <input class="input" type="password" name="secret" placeholder="Neues Secret" required>
                                        <button class="btn btn--secondary btn--sm" type="submit">Rotieren</button>
                                    </form>
                                    <form method="POST" action="/admin/vault/<?php echo (int)($e['id'] ?? 0); ?>/delete" class="table-inline-form" onsubmit="return confirm('Wirklich löschen?');">
                                        <?php echo Csrf::field(); ?>
                                        <button class="btn btn--linkish link-action link-action--danger" type="submit">Löschen</button>
                                    </form>
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
