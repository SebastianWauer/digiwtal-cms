<?php
declare(strict_types=1);

echo flash_render($flash ?? null);
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
$id = (int)($row['id'] ?? 0);
$title = (string)($row['title'] ?? '');
$slug = (string)($row['slug'] ?? '');
$teaser = (string)($row['teaser'] ?? '');
$contentJson = (string)($row['content_json'] ?? '{"blocks":[]}');
$categoryId = (int)($row['category_id'] ?? 0);
$imageMediaId = (int)($row['image_media_id'] ?? 0);
$isPublished = !empty($row['is_published']);
$publishedAt = trim((string)($row['published_at'] ?? ''));
$publishedAtInput = $publishedAt !== '' ? date('Y-m-d\TH:i', (int)strtotime($publishedAt)) : '';
?>
<form method="post" action="/news/save" class="pages-edit-form">
  <?= $csrfField ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="pages-edit-grid2">
    <div class="pages-edit-field"><div class="pages-edit-field-label">Titel</div><input class="pages-edit-input" type="text" name="title" value="<?= h($title) ?>" required></div>
    <div class="pages-edit-field"><div class="pages-edit-field-label">Slug</div><input class="pages-edit-input" type="text" name="slug" value="<?= h($slug) ?>" placeholder="wird aus Titel erzeugt"></div>
  </div>
  <div class="pages-edit-field"><div class="pages-edit-field-label">Teaser</div><textarea class="pages-edit-textarea" name="teaser" rows="3"><?= h($teaser) ?></textarea></div>
  <div class="pages-edit-field"><div class="pages-edit-field-label">Inhalt (JSON)</div><textarea class="pages-edit-textarea" name="content_json" rows="10"><?= h($contentJson) ?></textarea></div>
  <div class="pages-edit-grid2">
    <div class="pages-edit-field">
      <div class="pages-edit-field-label">Kategorie</div>
      <select class="pages-edit-input" name="category_id">
        <option value="0">Keine</option>
        <?php foreach ($allCategories as $cat): $cid=(int)($cat['id']??0); ?>
          <option value="<?= $cid ?>" <?= $categoryId === $cid ? 'selected' : '' ?>><?= h((string)($cat['name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="pages-edit-field-hint">Oder neue Kategorie:</div>
      <input class="pages-edit-input" type="text" name="category_new" placeholder="Neue Kategorie anlegen">
    </div>
    <div class="pages-edit-field"><div class="pages-edit-field-label">Bild-Media-ID</div><input class="pages-edit-input" type="number" name="image_media_id" min="0" value="<?= $imageMediaId > 0 ? $imageMediaId : '' ?>"></div>
  </div>
  <div class="pages-edit-grid2">
    <label class="pages-edit-check"><input type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>><span>Veroeffentlicht</span></label>
    <div class="pages-edit-field"><div class="pages-edit-field-label">Veroeffentlicht am</div><input class="pages-edit-input" type="datetime-local" name="published_at" value="<?= h($publishedAtInput) ?>"></div>
  </div>
  <div class="pages-actions-inline" style="margin-top:1rem;">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--ghost" href="/news">Zurueck</a>
  </div>
</form>
