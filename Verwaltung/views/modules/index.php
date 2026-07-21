<?php
$title = 'Module';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Module</h1>
                <p class="page-subtitle">Verfügbare Modulbausteine des Systems.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/dashboard">Zurück</a>
            </div>
        </header>

        <?php if (empty($modules)): ?>
            <p class="empty-state">Keine Module vorhanden.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Name</th>
                            <th>Beschreibung</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $m): ?>
                        <tr>
                            <td class="mono"><?php echo htmlspecialchars((string)($m['key_name'] ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string)($m['display_name'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($m['description'] ?? ''), ENT_QUOTES); ?></td>
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
