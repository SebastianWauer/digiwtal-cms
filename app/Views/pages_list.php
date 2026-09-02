<?php
declare(strict_types=1);

/** @var array $rows */
/** @var array $navigationRows */
/** @var int $deletedCount */

$canCreate = function_exists('admin_can') && admin_can('pages.create');
$canEdit   = function_exists('admin_can') && admin_can('pages.edit');
$canDelete = function_exists('admin_can') && admin_can('pages.delete');
$navigationRows = is_array($navigationRows ?? null) ? $navigationRows : [];
$navAreaLabels = ['header' => 'Header', 'footer' => 'Footer', 'both' => 'Header & Footer'];

echo flash_render($flash ?? null);
?>

<div class="pages-actions">
  <?php if ($canCreate): ?>
    <a class="btn" href="<?= cms_base_path() ?>/pages/edit">Neue Seite anlegen</a>
  <?php endif; ?>

  <div class="pages-actions-right">
    <?php if ($deletedCount > 0): ?>
      <a class="btn btn--ghost" href="<?= cms_base_path() ?>/pages/deleted">
        Gelöschte Seiten (<?= (int)$deletedCount ?>)
      </a>
    <?php else: ?>
      <span class="pages-hint">Keine gelöschten Seiten</span>
    <?php endif; ?>
  </div>
</div>

<section class="pages-order-card" aria-labelledby="navigation-order-heading">
  <div class="pages-order-head">
    <div>
      <h2 id="navigation-order-heading">Navigationsreihenfolge</h2>
      <p>Die Reihenfolge gilt zentral für Website, Sidebar und Seiten-Karussell. Ziehe Seiten an die gewünschte Position oder nutze die Pfeile.</p>
    </div>
  </div>

  <?php if ($navigationRows === []): ?>
    <div class="pages-order-empty">Aktuell wird keine Seite in der Navigation angezeigt.</div>
  <?php else: ?>
    <form method="post" action="<?= cms_base_path() ?>/pages/navigation-order" class="pages-order-form" id="navigationOrderForm">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="navigation_order" id="navigationOrderValue" value="">

      <ol class="pages-order-list" id="navigationOrderList">
        <?php foreach ($navigationRows as $index => $navRow): ?>
          <?php
            $navId = (int)($navRow['id'] ?? 0);
            $navTitle = trim((string)($navRow['nav_label'] ?? ''));
            if ($navTitle === '') $navTitle = trim((string)($navRow['title'] ?? ''));
            if ($navTitle === '') $navTitle = 'Seite #' . $navId;
            $navArea = (string)($navRow['nav_area'] ?? 'header');
            $navStatus = (string)($navRow['status'] ?? 'live');
          ?>
          <li class="pages-order-item<?= $canEdit ? '' : ' is-readonly' ?>" data-page-id="<?= $navId ?>" draggable="<?= $canEdit ? 'true' : 'false' ?>">
            <span class="pages-order-position" aria-hidden="true"><?= $index + 1 ?></span>
            <?php if ($canEdit): ?>
              <button type="button" class="pages-order-handle" aria-label="<?= h($navTitle) ?> verschieben" title="Ziehen zum Sortieren">⠿</button>
            <?php endif; ?>
            <span class="pages-order-copy">
              <strong><?= h($navTitle) ?></strong>
              <span class="pages-order-meta">
                <span><?= h((string)($navRow['slug'] ?? '')) ?></span>
                <span class="pages-order-area"><?= h($navAreaLabels[$navArea] ?? 'Header') ?></span>
                <?php if ($navStatus === 'draft'): ?><span class="pages-order-draft">Entwurf</span><?php endif; ?>
              </span>
            </span>
            <?php if ($canEdit): ?>
              <span class="pages-order-controls">
                <button type="button" data-order-move="up" aria-label="<?= h($navTitle) ?> nach oben">↑</button>
                <button type="button" data-order-move="down" aria-label="<?= h($navTitle) ?> nach unten">↓</button>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>

      <?php if ($canEdit): ?>
        <div class="pages-order-save">
          <span class="pages-hint">Änderungen werden erst mit diesem Button übernommen.</span>
          <button type="submit" class="btn">Reihenfolge speichern</button>
        </div>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</section>

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
                <a class="btn btn--ghost btn--badge btn--warn" href="<?= cms_base_path() ?>/pages/edit?id=<?= (int)$id ?>">Bearbeiten</a>
              <?php endif; ?>

              <?php if ($canDelete): ?>
                <form method="post" action="<?= cms_base_path() ?>/pages/delete" class="form-reset">
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

<?php if ($canEdit && $navigationRows !== []): ?>
<script>
(() => {
  const list = document.getElementById('navigationOrderList');
  const form = document.getElementById('navigationOrderForm');
  const value = document.getElementById('navigationOrderValue');
  if (!list || !form || !value) return;

  let dragged = null;

  const sync = () => {
    const items = [...list.querySelectorAll('.pages-order-item')];
    items.forEach((item, index) => {
      const position = item.querySelector('.pages-order-position');
      if (position) position.textContent = String(index + 1);
      const up = item.querySelector('[data-order-move="up"]');
      const down = item.querySelector('[data-order-move="down"]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === items.length - 1;
    });
    value.value = JSON.stringify(items.map(item => Number(item.dataset.pageId)));
  };

  list.addEventListener('dragstart', event => {
    const item = event.target.closest('.pages-order-item');
    if (!item) return;
    dragged = item;
    item.classList.add('is-dragging');
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
  });

  list.addEventListener('dragover', event => {
    if (!dragged) return;
    event.preventDefault();
    const target = event.target.closest('.pages-order-item');
    if (!target || target === dragged) return;
    const rect = target.getBoundingClientRect();
    list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
  });

  list.addEventListener('drop', event => {
    if (!dragged) return;
    event.preventDefault();
    sync();
  });

  list.addEventListener('dragend', () => {
    if (dragged) dragged.classList.remove('is-dragging');
    dragged = null;
    sync();
  });

  list.addEventListener('click', event => {
    const button = event.target.closest('[data-order-move]');
    if (!button) return;
    const item = button.closest('.pages-order-item');
    if (!item) return;
    if (button.dataset.orderMove === 'up' && item.previousElementSibling) {
      list.insertBefore(item, item.previousElementSibling);
    }
    if (button.dataset.orderMove === 'down' && item.nextElementSibling) {
      list.insertBefore(item.nextElementSibling, item);
    }
    sync();
  });

  form.addEventListener('submit', sync);
  sync();
})();
</script>
<?php endif; ?>
