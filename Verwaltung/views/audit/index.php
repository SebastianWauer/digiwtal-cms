<?php
$title = 'Audit-Log';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Audit-Log</h1>
                <p class="page-subtitle">Login, Deployments, Benutzeraktionen und Systemereignisse nachvollziehen.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/dashboard">Dashboard</a>
            </div>
        </header>

        <form method="GET" class="form-grid form-grid--2">
            <div class="field">
                <label for="action">Aktion</label>
                <input class="input" type="text" id="action" name="action" value="<?php echo htmlspecialchars((string)($_GET['action'] ?? ''), ENT_QUOTES); ?>" placeholder="z.B. deploy">
            </div>
            <div class="field">
                <label for="entity">Entität</label>
                <select class="select" id="entity" name="entity">
                    <option value="">Alle</option>
                    <?php foreach (['auth', 'customer', 'deployment', 'admin_user', 'webhook_token'] as $entity): ?>
                        <option value="<?php echo $entity; ?>" <?php echo (($_GET['entity'] ?? '') === $entity) ? 'selected' : ''; ?>><?php echo $entity; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="submit-row">
                <button class="btn btn--primary" type="submit">Filtern</button>
                <a class="btn btn--secondary" href="/admin/audit">Reset</a>
            </div>
        </form>

        <p class="section-copy"><?php echo (int)$total; ?> Einträge gesamt.</p>

        <?php if (empty($entries)): ?>
            <p class="empty-state">Keine Einträge gefunden.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Zeitpunkt</th>
                            <th>Admin</th>
                            <th>Aktion</th>
                            <th>Entität</th>
                            <th>Detail</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($e['created_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string)($e['admin_email'] ?? '—'), ENT_QUOTES); ?></td>
                            <td><span class="status-pill status-pill--unknown mono"><?php echo htmlspecialchars((string)($e['action'] ?? ''), ENT_QUOTES); ?></span></td>
                            <td class="text-muted">
                                <?php echo htmlspecialchars((string)($e['entity_type'] ?? '—'), ENT_QUOTES); ?>
                                <?php if ($e['entity_id']): ?> <span class="mono">#<?php echo (int)$e['entity_id']; ?></span><?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($e['detail'] ?? ''), ENT_QUOTES); ?></td>
                            <td class="mono text-muted"><?php echo htmlspecialchars((string)($e['ip'] ?? ''), ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php for ($page = 1; $page <= $pages; $page++): ?>
                    <?php
                    $query = $_GET;
                    $query['page'] = $page;
                    ?>
                    <a class="btn <?php echo $page === $currentPage ? 'btn--primary' : 'btn--secondary'; ?> btn--sm pagination__item" href="/admin/audit?<?php echo htmlspecialchars(http_build_query($query), ENT_QUOTES); ?>">
                        <?php echo $page; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
