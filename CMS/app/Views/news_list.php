<?php
declare(strict_types=1);

echo flash_render($flash ?? null);
$canCreate = function_exists('admin_can') && admin_can('news.create');
$canEdit = function_exists('admin_can') && admin_can('news.edit');
$canDelete = function_exists('admin_can') && admin_can('news.delete');
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
$selectedCategoryId = (int)($_GET['category_id'] ?? 0);
?>
<div class="pages-actions">
  <?php if ($canCreate): ?><a class="btn" href="/news/edit">Neue News</a><?php endif; ?>
  <div class="pages-actions-right">
    <a class="btn btn--ghost" href="/news/categories">Kategorien</a>
    <form method="get" action="/news" class="form-reset" style="display:flex;gap:.5rem;align-items:center;">
      <select class="pages-edit-input" name="category_id" style="min-width:220px;">
        <option value="0">Alle Kategorien</option>
        <?php foreach ($allCategories as $cat): $cid = (int)($cat['id'] ?? 0); ?>
          <option value="<?= $cid ?>" <?= $selectedCategoryId === $cid ? 'selected' : '' ?>><?= h((string)($cat['name'] ?? 'Kategorie')) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn--ghost">Filtern</button>
    </form>
    <?php if (($deletedCount ?? 0) > 0): ?><a class="btn btn--ghost" href="/news/deleted">Geloeschte News (<?= (int)$deletedCount ?>)</a><?php endif; ?>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead><tr><th>Titel</th><th>Kategorie</th><th>Status</th><th>Veroeffentlicht</th><th class="pages-col-actions">Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $id = (int)($r['id'] ?? 0);
      $title = (string)($r['title'] ?? '');
      $cat = trim((string)($r['category_name'] ?? ''));
      $isPublished = !empty($r['is_published']);
      $badgeClass = $isPublished ? 'pages-badge--live' : 'pages-badge--draft';
      $publishedAt = trim((string)($r['published_at'] ?? ''));
    ?>
      <tr>
        <td><strong><?= h($title) ?></strong><div class="pages-hint">/news/<?= h((string)($r['slug'] ?? '')) ?></div></td>
        <td><?= $cat !== '' ? h($cat) : '<span class="pages-hint">ohne Kategorie</span>' ?></td>
        <td><span class="pages-badge <?= $badgeClass ?>"><?= $isPublished ? 'Live' : 'Entwurf' ?></span></td>
        <td><?= $publishedAt !== '' ? h((string)date('d.m.Y H:i', (int)strtotime($publishedAt))) : '<span class="pages-hint">-</span>' ?></td>
        <td class="pages-col-actions">
          <div class="pages-actions-inline">
            <?php if ($canEdit): ?><a class="btn btn--ghost btn--badge btn--warn" href="/news/edit?id=<?= $id ?>">Bearbeiten</a><?php endif; ?>
            <?php if ($canDelete): ?>
            <form method="post" action="/news/delete" class="form-reset"><?= $csrfField ?><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" class="btn btn--ghost btn--badge btn--danger">Loeschen</button></form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
