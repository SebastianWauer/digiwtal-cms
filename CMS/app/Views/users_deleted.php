<?php
declare(strict_types=1);
echo flash_render($flash ?? null);

/** @var array $rows */
/** @var int $deletedCount */
?>

<?php
$canRestore = function_exists('admin_can') && admin_can('users.delete');
$csrfField  = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>

<div class="pages-actions">
  <a class="btn btn--ghost" href="/users">Zurück zu Benutzern</a>

  <div class="pages-actions-right">
    <a class="btn btn--ghost" href="/roles">Rollen</a>
    <?php if ($deletedCount > 0 && $canRestore): ?>
      <form method="post" action="/users/purge" class="form-reset" onsubmit="return confirm('Papierkorb wirklich leeren?');">
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
        <th>Username</th>
        <th class="pages-col-updated">Gelöscht</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $username = (string)($r['username'] ?? '');
        $deletedAt = (string)($r['deleted_at'] ?? '');
      ?>
      <tr class="is-deleted">
        <td><strong><?= h($username) ?></strong></td>
        <td class="pages-mono pages-nowrap"><?= h($deletedAt) ?></td>
        <td class="pages-col-actions">
          <?php if ($canRestore): ?>
            <form method="post" action="/users/restore" class="form-reset">
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
