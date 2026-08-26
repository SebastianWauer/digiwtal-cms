<?php
declare(strict_types=1);

echo flash_render($flash ?? null);
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>
<div class="pages-actions">
  <a class="btn btn--ghost" href="<?= cms_base_path() ?>/news">Zurueck zu News</a>
  <div class="pages-actions-right">
    <form method="post" action="<?= cms_base_path() ?>/news/purge" class="form-reset" onsubmit="return confirm('Papierkorb wirklich leeren?');"><?= $csrfField ?><button type="submit" class="btn btn--ghost btn--danger">Papierkorb leeren</button></form>
  </div>
</div>
<div class="pages-card">
  <table class="pages-table">
    <thead><tr><th>Titel</th><th>Kategorie</th><th class="pages-col-actions">Aktionen</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $id=(int)($r['id']??0); ?>
      <tr>
        <td><strong><?= h((string)($r['title'] ?? '')) ?></strong></td>
        <td><?= h((string)($r['category_name'] ?? '')) ?></td>
        <td class="pages-col-actions">
          <form method="post" action="<?= cms_base_path() ?>/news/restore" class="form-reset"><?= $csrfField ?><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" class="btn btn--ghost btn--warn btn--badge">Wiederherstellen</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
