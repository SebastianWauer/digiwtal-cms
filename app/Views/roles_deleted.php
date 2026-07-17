<?php
declare(strict_types=1);
echo flash_render($flash ?? null);

/** @var array $rows */
/** @var int $deletedCount */

$canRestore = function_exists('admin_can') && admin_can('roles.delete');
$csrfField  = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>

<div class="pages-actions">
  <a class="btn btn--ghost" href="/roles">Zurück zu Rollen</a>

  <div class="pages-actions-right">
    <a class="btn btn--ghost" href="/users">Benutzer</a>
    <?php if ($deletedCount > 0 && $canRestore): ?>
      <form method="post" action="/roles/purge" class="form-reset" onsubmit="return confirm('Papierkorb wirklich leeren?');">
        <?= $csrfField ?>
        <button type="submit" class="btn btn--ghost btn--sm btn--danger">Papierkorb leeren</button>
      </form>
    <?php endif; ?>
    <span class="pages-hint"><?= (int)$deletedCount ?> gelöscht</span>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th>Key</th>
        <th>Name</th>
        <th class="pages-col-updated">Gelöscht</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $key = (string)($r['key'] ?? '');
        $name = (string)($r['name'] ?? '');
        $deletedAt = (string)($r['deleted_at'] ?? '');
      ?>
      <tr class="is-deleted">
        <td class="pages-mono"><strong><?= h($key) ?></strong></td>
        <td><?= h($name) ?></td>
        <td class="pages-mono pages-nowrap"><?= h($deletedAt) ?></td>
        <td class="pages-col-actions">
          <?php if ($canRestore): ?>
            <form method="post" action="/roles/restore" class="form-reset">
              <?= $csrfField ?>
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <button type="submit" class="btn btn--ghost btn--badge btn--warn">Wiederherstellen</button>
            </form>
          <?php else: ?>
            <span class="pages-hint">keine Berechtigung</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>

  </table>
</div>
