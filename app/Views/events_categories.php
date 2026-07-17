<?php
declare(strict_types=1);

echo flash_render($flash ?? null);

$canEdit = function_exists('admin_can') && admin_can('events.edit');
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>

<div class="pages-actions">
  <a class="btn btn--ghost" href="/events">Zurück zu Events</a>
</div>

<div class="pages-card" style="margin-bottom:1rem;border-left:4px solid #f59e0b;">
  <div class="pages-hint" style="padding:.85rem 1rem;">
    Hinweis: Wenn du einen Kategorienamen änderst, wird der Slug automatisch mitgeändert.
    Bereits gesetzte Kategorien im Pagebuilder-Events-Block (Filter nach Slug) verlieren dadurch ihre Zuordnung und müssen neu ausgewählt werden.
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th style="width:80px;">ID</th>
        <th>Name</th>
        <th style="width:320px;">Serien-Logo</th>
        <th style="width:180px;">Farbe</th>
        <th>Slug</th>
        <th style="width:220px;">Aktion</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $name = (string)($r['name'] ?? '');
        $slug = (string)($r['slug'] ?? '');
        $logoMediaId = (int)($r['logo_media_id'] ?? 0);
        $colorHex = strtoupper(trim((string)($r['color_hex'] ?? '')));
        if (preg_match('/^#[0-9A-F]{6}$/', $colorHex) !== 1) {
          $colorHex = '#D32F2F';
        }
      ?>
      <tr>
        <?php $formId = 'cat-save-' . $id; ?>
        <td><?= $id ?></td>
        <td>
          <input form="<?= h($formId) ?>" class="pages-edit-input" type="text" name="name" value="<?= h($name) ?>" style="min-width:280px;" <?= $canEdit ? '' : 'readonly' ?>>
        </td>
        <td>
          <?php $logoInputName = 'logo_media_id_row_' . $id; ?>
          <div class="mp">
            <div class="mp__meta">
              <div class="mp__actions">
                <input form="<?= h($formId) ?>" class="ss-input mp__input" type="text" name="<?= h($logoInputName) ?>" value="<?= $logoMediaId > 0 ? h((string)$logoMediaId) : '' ?>" placeholder="Media-ID" <?= $canEdit ? '' : 'readonly' ?>>
                <?php if ($canEdit): ?>
                  <button type="button" class="btn btn--ghost btn--sm mp__open" data-mp-input="<?= h($logoInputName) ?>" data-mp-title="<?= h('Serien-Logo für ' . $name) ?>">Aus Medien wählen</button>
                <?php endif; ?>
              </div>
            </div>
            <div class="mp__preview">
              <?php if ($logoMediaId > 0): ?>
                <img src="/media/thumb?id=<?= $logoMediaId ?>" alt="">
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td>
            <input form="<?= h($formId) ?>" type="color" name="color_hex" value="<?= h($colorHex) ?>" title="Kategoriefarbe" <?= $canEdit ? '' : 'disabled' ?>>
        </td>
        <td><code><?= h($slug) ?></code></td>
        <td>
          <form id="<?= h($formId) ?>" method="post" action="/events/categories/save" class="form-reset">
            <?= $csrfField ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <?php if ($canEdit): ?>
              <button type="submit" class="btn btn--ghost">Speichern</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (($rows ?? []) === []): ?>
      <tr><td colspan="6"><span class="pages-hint">Keine Kategorien vorhanden.</span></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

