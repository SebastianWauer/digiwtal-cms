<?php
declare(strict_types=1);

/** @var array $rows */
/** @var int $deletedCount */

$canCreate = function_exists('admin_can') && admin_can('pages.create');
$canEdit   = function_exists('admin_can') && admin_can('pages.edit');
$canDelete = function_exists('admin_can') && admin_can('pages.delete');
?>

<div class="pages-actions">
  <?php if ($canCreate): ?>
    <a class="btn" href="/pages/edit">Neue Seite anlegen</a>
  <?php endif; ?>

  <div class="pages-actions-right">
    <?php if ($deletedCount > 0): ?>
      <a class="btn btn--ghost" href="/pages/deleted">
        Gelöschte Seiten (<?= (int)$deletedCount ?>)
      </a>
    <?php else: ?>
      <span class="pages-hint">Keine gelöschten Seiten</span>
    <?php endif; ?>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th>Titel</th>
        <th>Slug</th>
        <th class="pages-col-start">Startseite</th>
        <th class="pages-col-updated">Geändert</th>
        <th class="pages-col-status">Status</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $id      = (int)($r['id'] ?? 0);
          $slug    = (string)($r['slug'] ?? '');
          $title   = (string)($r['title'] ?? '');
          $updated = (string)($r['updated_at'] ?? '');
          $isHome  = !empty($r['is_home']);

          // Status pro Row berechnen
          $status = (string)($r['status'] ?? 'live');
          if (!in_array($status, ['live','draft'], true)) $status = 'live';

          $badgeClass = $status === 'draft' ? 'pages-badge--draft' : 'pages-badge--live';
          $badgeText  = $status === 'draft' ? 'Entwurf' : 'Live';
        ?>
        <tr>
          <td class="pages-title">
            <strong><?= h($title !== '' ? $title : '(ohne Titel)') ?></strong>
          </td>

          <td class="pages-slug">
            <span class="pages-mono"><?= h($slug === '/' ? '/' : ltrim($slug, '/')) ?></span>
          </td>

          <td class="pages-col-start">
            <?php if ($isHome): ?>
              <span class="pages-check" aria-label="Startseite">✓</span>
            <?php endif; ?>
          </td>

          <td class="pages-col-updated">
            <span class="pages-mono pages-nowrap"><?= h($updated) ?></span>
          </td>

          <td class="pages-col-status">
            <span class="pages-badge <?= $badgeClass ?>"><?= h($badgeText) ?></span>
          </td>

          <td class="pages-col-actions">
            <div class="pages-actions-inline">
              <?php if ($canEdit): ?>
                <a class="btn btn--ghost btn--badge btn--warn" href="/pages/edit?id=<?= (int)$id ?>">Bearbeiten</a>
              <?php endif; ?>

              <?php if ($canDelete): ?>
                <form method="post" action="/pages/delete" class="form-reset">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$id ?>">
                  <button type="submit" class="btn btn--ghost btn--badge btn--danger">Löschen</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
