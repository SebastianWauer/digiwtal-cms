<?php
declare(strict_types=1);

/** @var array $rows */
/** @var int $deletedCount */
/** @var ?array $flash */

echo flash_render($flash ?? null);

$canCreate = function_exists('admin_can') && admin_can('roles.create');
$canEdit   = function_exists('admin_can') && admin_can('roles.edit');
$canDelete = function_exists('admin_can') && admin_can('roles.delete');

$canViewUsers = function_exists('admin_can') && admin_can('users.view');

$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>
<div class="pages-actions">
  <?php if ($canCreate): ?>
    <a class="btn" href="/roles/edit">Neue Rolle</a>
  <?php endif; ?>

  <div class="pages-actions-right">
    <?php if ($canViewUsers): ?>
      <a class="btn btn--ghost" href="/users">Benutzer</a>
    <?php endif; ?>

    <?php if ($deletedCount > 0): ?>
      <a class="btn btn--ghost" href="/roles/deleted">
        Gelöschte Rollen (<?= (int)$deletedCount ?>)
      </a>
    <?php else: ?>
      <span class="pages-hint">Keine gelöschten Rollen</span>
    <?php endif; ?>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th>Key</th>
        <th>Name</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $key = (string)($r['key'] ?? '');
        $name = (string)($r['name'] ?? '');
        $isAdmin = ($key === 'admin');
      ?>
      <tr>
        <td class="pages-mono"><strong><?= h($key) ?></strong></td>
        <td><?= h($name) ?></td>
        <td class="pages-col-actions">
          <div class="pages-actions-inline">
            <?php if ($canEdit): ?>
              <a class="btn btn--ghost btn--badge btn--warn" href="/roles/edit?id=<?= (int)$id ?>">Bearbeiten</a>
            <?php endif; ?>

            <?php if ($canDelete): ?>
              <?php if ($isAdmin): ?>
                <span class="pages-hint">admin geschützt</span>
              <?php else: ?>
                <form method="post" action="/roles/delete" class="form-reset">
                  <?= $csrfField ?>
                  <input type="hidden" name="id" value="<?= (int)$id ?>">
                  <button type="submit" class="btn btn--ghost btn--badge btn--danger">Löschen</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
