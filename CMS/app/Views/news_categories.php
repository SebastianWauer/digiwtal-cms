<?php
declare(strict_types=1);

echo flash_render($flash ?? null);
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>
<div class="pages-actions"><a class="btn btn--ghost" href="/news">Zurueck zu News</a></div>
<div class="pages-card">
  <table class="pages-table">
    <thead><tr><th>Name</th><th>Slug</th><th class="pages-col-actions">Aktion</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $id=(int)($r['id']??0); ?>
      <tr>
        <td>
          <form method="post" action="/news/categories/save" class="form-reset" style="display:flex;gap:.5rem;align-items:center;">
            <?= $csrfField ?><input type="hidden" name="id" value="<?= $id ?>">
            <input class="pages-edit-input" type="text" name="name" value="<?= h((string)($r['name'] ?? '')) ?>" style="min-width:280px;">
            <button class="btn btn--ghost btn--badge" type="submit">Speichern</button>
          </form>
        </td>
        <td><?= h((string)($r['slug'] ?? '')) ?></td>
        <td class="pages-col-actions"></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
