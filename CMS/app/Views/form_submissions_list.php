<?php
declare(strict_types=1);

echo flash_render($flash ?? null);

$qFilter = (string)($q ?? ($_GET['q'] ?? ''));
$activeFilter = (string)($filter ?? ($_GET['filter'] ?? 'open'));
$counts = is_array($counts ?? null) ? $counts : ['open' => 0, 'processed' => 0, 'deleted' => 0];
$csrf = function_exists('admin_csrf_field') ? admin_csrf_field() : '';

$tabs = [
  'open' => 'Offen',
  'processed' => 'Bearbeitet',
  'deleted' => 'Gelöscht',
  'all' => 'Alle',
];
?>

<div class="pages-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;">
  <form method="get" action="<?= cms_base_path() ?>/forms/submissions" class="form-reset" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
    <input class="pages-edit-input" type="text" name="q" value="<?= h($qFilter) ?>" placeholder="Suche: Datum, Name, E-Mail, Telefon, Nachricht" style="min-width:320px;">
    <input type="hidden" name="filter" value="<?= h($activeFilter) ?>">
    <button class="btn btn--ghost" type="submit">Filtern</button>
    <?php if ($qFilter !== ''): ?><a class="btn btn--ghost" href="<?= cms_base_path() ?>/forms/submissions?filter=<?= h($activeFilter) ?>">Reset</a><?php endif; ?>
  </form>
</div>

<div class="pages-toolbar" style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;">
  <?php foreach ($tabs as $key => $label):
    $url = cms_base_path() . '/forms/submissions?filter=' . rawurlencode($key) . ($qFilter !== '' ? ('&q=' . rawurlencode($qFilter)) : '');
    $count = $key === 'all' ? array_sum($counts) : (int)($counts[$key] ?? 0);
  ?>
    <a class="btn <?= $activeFilter === $key ? '' : 'btn--ghost' ?>" href="<?= h($url) ?>"><?= h($label) ?> (<?= (int)$count ?>)</a>
  <?php endforeach; ?>
</div>

<div class="pages-table-wrap" style="margin-top:1rem;">
<table class="pages-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Zeit</th>
      <th>Status</th>
      <th>Name</th>
      <th>E-Mail</th>
      <th>Telefon</th>
      <th>IP</th>
      <th>Nachricht</th>
      <th class="pages-col-actions">Aktionen</th>
    </tr>
  </thead>
  <tbody>
  <?php if (($items ?? []) === []): ?>
    <tr><td colspan="9"><span class="pages-hint">Keine Einreichungen vorhanden.</span></td></tr>
  <?php else: foreach ($items as $row):
    $isRecentOpen = ((string)$row['status'] === 'open' && !empty($row['is_recent']));
  ?>
    <tr<?= $isRecentOpen ? ' style="background:rgba(245, 158, 11, 0.12);"' : '' ?>>
      <td><?= (int)$row['id'] ?></td>
      <td><?= h((string)$row['created_at']) ?></td>
      <td>
        <strong><?= h((string)$row['status_label']) ?></strong>
        <?php if ($isRecentOpen): ?><span class="pages-hint" style="margin-left:.35rem;color:#b45309;">Neu</span><?php endif; ?>
      </td>
      <td><?= h((string)$row['name']) ?></td>
      <td><?= h((string)$row['email']) ?></td>
      <td><?= h((string)$row['phone']) ?></td>
      <td><?= h((string)$row['ip']) ?></td>
      <td style="max-width:420px;white-space:pre-wrap;"><?= h((string)$row['message']) ?></td>
      <td class="pages-col-actions">
        <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
          <?php if ((string)$row['status'] === 'deleted'): ?>
          <form method="post" action="<?= cms_base_path() ?>/forms/submissions/status" class="form-reset">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="status" value="open">
            <input type="hidden" name="return_filter" value="<?= h($activeFilter) ?>">
            <input type="hidden" name="return_q" value="<?= h($qFilter) ?>">
            <button class="btn btn--ghost btn--warn btn--badge" type="submit">Wiederherstellen</button>
          </form>
          <?php endif; ?>
          <?php if ((string)$row['status'] !== 'open' && (string)$row['status'] !== 'deleted'): ?>
          <form method="post" action="<?= cms_base_path() ?>/forms/submissions/status" class="form-reset">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="status" value="open">
            <input type="hidden" name="return_filter" value="<?= h($activeFilter) ?>">
            <input type="hidden" name="return_q" value="<?= h($qFilter) ?>">
            <button class="btn btn--ghost btn--badge" type="submit">Offen</button>
          </form>
          <?php endif; ?>
          <?php if ((string)$row['status'] !== 'processed'): ?>
          <form method="post" action="<?= cms_base_path() ?>/forms/submissions/status" class="form-reset">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="status" value="processed">
            <input type="hidden" name="return_filter" value="<?= h($activeFilter) ?>">
            <input type="hidden" name="return_q" value="<?= h($qFilter) ?>">
            <button class="btn btn--ghost btn--badge" type="submit">Bearbeitet</button>
          </form>
          <?php endif; ?>
          <?php if ((string)$row['status'] !== 'deleted'): ?>
          <form method="post" action="<?= cms_base_path() ?>/forms/submissions/status" class="form-reset">
            <?= $csrf ?>
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="status" value="deleted">
            <input type="hidden" name="return_filter" value="<?= h($activeFilter) ?>">
            <input type="hidden" name="return_q" value="<?= h($qFilter) ?>">
            <button class="btn btn--ghost btn--badge btn--danger" type="submit">Löschen</button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>
