<?php
declare(strict_types=1);

/** @var array $rows */
/** @var array $headerNavigationRows */
/** @var array $footerNavigationRows */
/** @var int $deletedCount */

$canCreate = function_exists('admin_can') && admin_can('pages.create');
$canEdit   = function_exists('admin_can') && admin_can('pages.edit');
$canDelete = function_exists('admin_can') && admin_can('pages.delete');
$headerNavigationRows = is_array($headerNavigationRows ?? null) ? $headerNavigationRows : [];
$footerNavigationRows = is_array($footerNavigationRows ?? null) ? $footerNavigationRows : [];
$navAreaLabels = ['header' => 'Header', 'footer' => 'Footer', 'both' => 'Header & Footer'];

$renderOrderModal = static function (string $area, string $title, array $navigationRows) use ($navAreaLabels): void {
    $modalId = 'pages-order-modal-' . $area;
    ?>
    <div class="pages-order-modal" id="<?= h($modalId) ?>" data-order-modal="<?= h($area) ?>" hidden>
      <button type="button" class="pages-order-modal__backdrop" data-order-close aria-label="Popup schließen"></button>
      <section class="pages-order-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?= h($modalId) ?>-title">
        <header class="pages-order-modal__head">
          <div>
            <h2 id="<?= h($modalId) ?>-title"><?= h($title) ?> sortieren</h2>
            <p>Seiten ziehen oder mit den Pfeilen verschieben und anschließend speichern.</p>
          </div>
          <button type="button" class="pages-order-modal__close" data-order-close aria-label="Popup schließen">×</button>
        </header>

        <?php if ($navigationRows === []): ?>
          <div class="pages-order-empty">In diesem Bereich sind aktuell keine Seiten vorhanden.</div>
        <?php else: ?>
          <form method="post" action="<?= cms_base_path() ?>/pages/navigation-order" class="pages-order-form" data-order-form>
            <?= admin_csrf_field() ?>
            <input type="hidden" name="navigation_area" value="<?= h($area) ?>">
            <input type="hidden" name="navigation_order" value="" data-order-value>

            <ol class="pages-order-list" data-order-list>
              <?php foreach ($navigationRows as $index => $navRow): ?>
                <?php
                  $navId = (int)($navRow['id'] ?? 0);
                  $navTitle = trim((string)($navRow['nav_label'] ?? ''));
                  if ($navTitle === '') $navTitle = trim((string)($navRow['title'] ?? ''));
                  if ($navTitle === '') $navTitle = 'Seite #' . $navId;
                  $navArea = (string)($navRow['nav_area'] ?? $area);
                  $navStatus = (string)($navRow['status'] ?? 'live');
                ?>
                <li class="pages-order-item" data-page-id="<?= $navId ?>" draggable="true">
                  <span class="pages-order-position" aria-hidden="true"><?= $index + 1 ?></span>
                  <button type="button" class="pages-order-handle" aria-label="<?= h($navTitle) ?> verschieben" title="Ziehen zum Sortieren">⠿</button>
                  <span class="pages-order-copy">
                    <strong><?= h($navTitle) ?></strong>
                    <span class="pages-order-meta">
                      <span><?= h((string)($navRow['slug'] ?? '')) ?></span>
                      <?php if ($navArea === 'both'): ?><span class="pages-order-area"><?= h($navAreaLabels['both']) ?></span><?php endif; ?>
                      <?php if ($navStatus === 'draft'): ?><span class="pages-order-draft">Entwurf</span><?php endif; ?>
                    </span>
                  </span>
                  <span class="pages-order-controls">
                    <button type="button" data-order-move="up" aria-label="<?= h($navTitle) ?> nach oben">↑</button>
                    <button type="button" data-order-move="down" aria-label="<?= h($navTitle) ?> nach unten">↓</button>
                  </span>
                </li>
              <?php endforeach; ?>
            </ol>

            <div class="pages-order-save">
              <button type="button" class="btn btn--ghost" data-order-close>Abbrechen</button>
              <button type="submit" class="btn">Reihenfolge speichern</button>
            </div>
          </form>
        <?php endif; ?>
      </section>
    </div>
    <?php
};

echo flash_render($flash ?? null);
?>

<div class="pages-actions">
  <div class="pages-actions-left">
    <?php if ($canCreate): ?>
      <a class="btn" href="<?= cms_base_path() ?>/pages/edit">Neue Seite anlegen</a>
    <?php endif; ?>
  </div>

  <div class="pages-actions-right">
    <?php if ($canEdit): ?>
      <button type="button" class="btn btn--ghost" data-order-open="header">Header sortieren</button>
      <button type="button" class="btn btn--ghost" data-order-open="footer">Footer sortieren</button>
    <?php endif; ?>
    <?php if ($deletedCount > 0): ?>
      <a class="btn btn--ghost" href="<?= cms_base_path() ?>/pages/deleted">Gelöschte Seiten (<?= (int)$deletedCount ?>)</a>
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
        <th class="pages-col-navigation">Header / Footer</th>
        <th class="pages-col-updated">Geändert</th>
        <th class="pages-col-status">Status</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $id = (int)($r['id'] ?? 0);
          $slug = (string)($r['slug'] ?? '');
          $title = (string)($r['title'] ?? '');
          $updated = (string)($r['updated_at'] ?? '');
          $isHome = !empty($r['is_home']);
          $navVisible = (int)($r['nav_visible'] ?? 0) === 1;
          $navArea = (string)($r['nav_area'] ?? 'header');
          $navText = $navVisible ? ($navAreaLabels[$navArea] ?? 'Header') : 'Keine';
          $navClass = $navVisible ? 'pages-nav-badge--' . ($navArea === 'both' ? 'both' : $navArea) : 'pages-nav-badge--none';
          $status = (string)($r['status'] ?? 'live');
          if (!in_array($status, ['live', 'draft'], true)) $status = 'live';
          $badgeClass = $status === 'draft' ? 'pages-badge--draft' : 'pages-badge--live';
          $badgeText = $status === 'draft' ? 'Entwurf' : 'Live';
        ?>
        <tr>
          <td class="pages-title"><strong><?= h($title !== '' ? $title : '(ohne Titel)') ?></strong></td>
          <td class="pages-slug"><span class="pages-mono"><?= h($slug === '/' ? '/' : ltrim($slug, '/')) ?></span></td>
          <td class="pages-col-start">
            <?php if ($isHome): ?><span class="pages-check" aria-label="Startseite">✓</span><?php endif; ?>
          </td>
          <td class="pages-col-navigation"><span class="pages-nav-badge <?= h($navClass) ?>"><?= h($navText) ?></span></td>
          <td class="pages-col-updated"><span class="pages-mono pages-nowrap"><?= h($updated) ?></span></td>
          <td class="pages-col-status"><span class="pages-badge <?= $badgeClass ?>"><?= h($badgeText) ?></span></td>
          <td class="pages-col-actions">
            <div class="pages-actions-inline">
              <?php if ($canEdit): ?>
                <a class="btn btn--ghost btn--badge btn--warn" href="<?= cms_base_path() ?>/pages/edit?id=<?= $id ?>">Bearbeiten</a>
              <?php endif; ?>
              <?php if ($canDelete): ?>
                <form method="post" action="<?= cms_base_path() ?>/pages/delete" class="form-reset">
                  <?= admin_csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
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

<?php if ($canEdit): ?>
  <?php $renderOrderModal('header', 'Header', $headerNavigationRows); ?>
  <?php $renderOrderModal('footer', 'Footer', $footerNavigationRows); ?>

  <script>
  (() => {
    const modals = [...document.querySelectorAll('[data-order-modal]')];
    let activeModal = null;

    const closeModal = () => {
      if (!activeModal) return;
      activeModal.hidden = true;
      activeModal = null;
      document.body.classList.remove('pages-order-modal-open');
    };

    const openModal = (area) => {
      const modal = modals.find((item) => item.dataset.orderModal === area);
      if (!modal) return;
      activeModal = modal;
      modal.hidden = false;
      document.body.classList.add('pages-order-modal-open');
      modal.querySelector('.pages-order-modal__close')?.focus();
    };

    document.addEventListener('click', (event) => {
      const openButton = event.target.closest('[data-order-open]');
      if (openButton) openModal(openButton.dataset.orderOpen || '');
      if (event.target.closest('[data-order-close]')) closeModal();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && activeModal) closeModal();
    });

    modals.forEach((modal) => {
      const form = modal.querySelector('[data-order-form]');
      const list = modal.querySelector('[data-order-list]');
      const value = modal.querySelector('[data-order-value]');
      if (!form || !list || !value) return;

      let dragged = null;
      const sync = () => {
        const items = [...list.querySelectorAll('.pages-order-item')];
        items.forEach((item, index) => {
          const position = item.querySelector('.pages-order-position');
          const up = item.querySelector('[data-order-move="up"]');
          const down = item.querySelector('[data-order-move="down"]');
          if (position) position.textContent = String(index + 1);
          if (up) up.disabled = index === 0;
          if (down) down.disabled = index === items.length - 1;
        });
        value.value = JSON.stringify(items.map((item) => Number(item.dataset.pageId)));
      };

      list.addEventListener('dragstart', (event) => {
        const item = event.target.closest('.pages-order-item');
        if (!item) return;
        dragged = item;
        item.classList.add('is-dragging');
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
      });
      list.addEventListener('dragover', (event) => {
        if (!dragged) return;
        event.preventDefault();
        const target = event.target.closest('.pages-order-item');
        if (!target || target === dragged) return;
        const rect = target.getBoundingClientRect();
        list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
      });
      list.addEventListener('drop', (event) => {
        if (!dragged) return;
        event.preventDefault();
        sync();
      });
      list.addEventListener('dragend', () => {
        if (dragged) dragged.classList.remove('is-dragging');
        dragged = null;
        sync();
      });
      list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-order-move]');
        if (!button) return;
        const item = button.closest('.pages-order-item');
        if (!item) return;
        if (button.dataset.orderMove === 'up' && item.previousElementSibling) {
          list.insertBefore(item, item.previousElementSibling);
        } else if (button.dataset.orderMove === 'down' && item.nextElementSibling) {
          list.insertBefore(item.nextElementSibling, item);
        }
        sync();
      });
      form.addEventListener('submit', sync);
      sync();
    });
  })();
  </script>
<?php endif; ?>
