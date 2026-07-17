<?php
declare(strict_types=1);
echo flash_render($flash ?? null);
/** @var array $rows */
/** @var int $deletedCount */

$canRestore = function_exists('admin_can') && admin_can('pages.delete'); // Restore = delete-Recht
?>

<div class="pages-actions">
  <a class="btn btn--ghost" href="/pages">Zurück zu Seiten</a>

  <div class="pages-actions-right">
    <?php if ($deletedCount > 0 && $canRestore): ?>
      <form method="post" action="/pages/purge" class="form-reset" onsubmit="return confirm('Papierkorb wirklich leeren?');">
        <?= admin_csrf_field() ?>
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
        <th>Titel</th>
        <th>Slug</th>
        <th class="pages-col-updated">Gelöscht</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $id        = (int)($r['id'] ?? 0);
          $slug      = (string)($r['slug'] ?? '');
          $title     = (string)($r['title'] ?? '');
          $deletedAt = (string)($r['deleted_at'] ?? '');
        ?>
        <tr class="is-deleted">
          <td class="pages-title">
            <strong><?= h($title !== '' ? $title : '(ohne Titel)') ?></strong>
          </td>

          <td class="pages-slug">
            <span class="pages-mono"><?= h($slug === '/' ? '/' : ltrim($slug, '/')) ?></span>
          </td>

          <td class="pages-col-updated">
            <span class="pages-mono pages-nowrap"><?= h($deletedAt) ?></span>
          </td>

          <td class="pages-col-actions">
            <div class="pages-actions-inline">
              <?php if ($canRestore): ?>
                <form method="post" action="/pages/restore" class="form-reset">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$id ?>">
                  <button type="submit" class="btn btn--ghost btn--badge btn--warn">Wiederherstellen</button>
                </form>
              <?php else: ?>
                <span class="pages-hint">keine Berechtigung</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
