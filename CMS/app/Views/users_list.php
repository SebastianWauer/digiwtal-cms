<?php
declare(strict_types=1);

/** @var array $rows */
/** @var int $deletedCount */
/** @var ?array $flash */

echo flash_render($flash ?? null);

$canCreate = function_exists('admin_can') && admin_can('users.create');
$canEdit   = function_exists('admin_can') && admin_can('users.edit');
$canDelete = function_exists('admin_can') && admin_can('users.delete');

$canViewRoles = function_exists('admin_can') && admin_can('roles.view');

$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>
<div class="pages-actions">
  <?php if ($canCreate): ?>
    <a class="btn" href="/users/edit">Neuer Benutzer</a>
  <?php endif; ?>

  <div class="pages-actions-right">
    <?php if ($canViewRoles): ?>
      <a class="btn btn--ghost" href="/roles">Rollen</a>
    <?php endif; ?>

    <?php if ($deletedCount > 0): ?>
      <a class="btn btn--ghost" href="/users/deleted">
        Gelöschte Benutzer (<?= (int)$deletedCount ?>)
      </a>
    <?php else: ?>
      <span class="pages-hint">Keine gelöschten Benutzer</span>
    <?php endif; ?>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Benutzername</th>
        <th>E-Mail</th>
        <th>Rolle</th>
        <th class="pages-col-status">Status</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $id       = (int)($r['id'] ?? 0);
        $name     = trim((string)($r['name'] ?? ''));
        $username = (string)($r['username'] ?? '');
        $email    = trim((string)($r['email'] ?? ''));
        $enabled  = !empty($r['enabled']);

        $roles = [];
        if (isset($r['roles']) && is_array($r['roles'])) $roles = $r['roles'];

        $canRowDelete = !empty($r['can_delete']); // Self/last admin protection kommt aus Controller/Repo
        $badgeClass = $enabled ? 'pages-badge--live' : 'pages-badge--draft';
        $badgeText  = $enabled ? 'Aktiv' : 'Gesperrt';
      ?>
      <tr>
        <td><?= $name !== '' ? h($name) : '<span class="pages-hint">—</span>' ?></td>
        <td><strong><?= h($username) ?></strong></td>
        <td><?= $email !== '' ? h($email) : '<span class="pages-hint">—</span>' ?></td>
        <td><?= $roles ? h(implode(', ', $roles)) : '<span class="pages-hint">—</span>' ?></td>
        <td class="pages-col-status"><span class="pages-badge <?= $badgeClass ?>"><?= h($badgeText) ?></span></td>

        <td class="pages-col-actions">
          <div class="pages-actions-inline">
            <?php if ($canEdit): ?>
              <a class="btn btn--ghost btn--badge btn--warn" href="/users/edit?id=<?= (int)$id ?>">Bearbeiten</a>
            <?php endif; ?>

            <?php if ($canDelete): ?>
              <?php if ($canRowDelete): ?>
                <form method="post" action="/users/delete" class="form-reset">
                  <?= $csrfField ?>
                  <input type="hidden" name="id" value="<?= (int)$id ?>">
                  <button type="submit" class="btn btn--ghost btn--badge btn--danger">Löschen</button>
                </form>
              <?php else: ?>
                <span class="pages-hint">geschützt</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
