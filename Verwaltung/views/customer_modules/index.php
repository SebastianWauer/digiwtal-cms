<?php
$title = 'Kunden-Module';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Module</h1>
                <p class="page-subtitle">Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong></p>
            </div>
            <div class="page-actions">
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

        <?php if (empty($modules)): ?>
            <p class="empty-state">Keine Module verfügbar.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Modul</th>
                            <th>Beschreibung</th>
                            <th>Aktiviert</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($m['display_name'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES); ?></td>
                            <td>
                                <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/modules/<?php echo (int)($m['id'] ?? 0); ?>" class="table-inline-form">
                                    <?php echo Csrf::field(); ?>
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="is_enabled" <?php echo ((int)($m['is_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                        <span>Aktiv</span>
                                    </label>
                                    <input class="input" type="date" name="expires_at" value="<?php echo htmlspecialchars(substr((string)($m['expires_at'] ?? ''), 0, 10), ENT_QUOTES); ?>">
                                    <button class="btn btn--primary btn--sm" type="submit">Speichern</button>
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
