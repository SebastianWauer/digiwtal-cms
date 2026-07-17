<?php
declare(strict_types=1);

/**
 * Erwartete Variablen:
 * @var array  $rows
 * @var array  $folders
 * @var string $q
 * @var string $ext
 * @var string $view
 * @var ?array $flash
 */

echo flash_render($flash ?? null);

$view = ($view === 'grid') ? 'grid' : 'list';

// Folder Lookup (id => name)
$folderNameById = [];
foreach ($folders as $f) {
  if (!is_array($f)) continue;
  $fid = (int)($f['id'] ?? 0);
  if ($fid <= 0) continue;
  $folderNameById[$fid] = (string)($f['name'] ?? '');
}
?>

<div class="media-page">

  <form method="get" action="/media/deleted" class="media-filters" id="mediaDeletedFilters">
    <div class="media-filters__row">
      <div class="media-filters__field">
        <div class="media-filters__label">Ansicht</div>
        <select name="view" class="media-select" id="mediaViewSelect">
          <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>Listenansicht</option>
          <option value="grid" <?= $view === 'grid' ? 'selected' : '' ?>>Kachelansicht</option>
        </select>
      </div>

      <div class="media-filters__field media-filters__field--grow">
        <div class="media-filters__label">Suche</div>
        <input type="text" name="q" class="media-input" value="<?= h($q) ?>" placeholder="Dateiname, Titel…">
      </div>

      <div class="media-filters__field">
        <div class="media-filters__label">Typ</div>
        <select name="ext" class="media-select">
          <option value="">Alle</option>
          <?php foreach (['jpg','jpeg','png','webp','svg','pdf'] as $e): ?>
            <option value="<?= h($e) ?>" <?= $ext === $e ? 'selected' : '' ?>><?= h(strtoupper($e)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button class="btn btn--sm" type="submit">Filter anwenden</button>
      <a class="btn btn--ghost btn--sm" href="/media">Zurück</a>
    </div>
  </form>

<div class="media-content__bar">
  <form method="post" action="/media/restore" id="mediaRestoreForm" class="media-bulk">
    <?= admin_csrf_field() ?>

    <button type="submit" class="btn btn--ghost btn--sm">
      Wiederherstellen
    </button>
  </form>

  <form method="post"
        action="/media/purge"
        class="media-bulk"
        onsubmit="return confirm('Papierkorb wirklich leeren?');">
    <?= admin_csrf_field() ?>
    <button type="submit" class="btn btn--ghost btn--sm btn--danger">
      Papierkorb leeren
    </button>
  </form>

  <div class="media-bulk__hint">
    Papierkorb: Medien auswählen und wiederherstellen.
  </div>
</div>

  <?php if (!$rows): ?>
    <div class="pages-hint">Papierkorb ist leer.</div>
  <?php else: ?>

    <?php if ($view === 'list'): ?>
      <div class="media-table-wrap">
        <table class="media-table">
          <thead>
            <tr>
              <th class="media-col-check"></th>
              <th>Vorschau</th>
              <th>Datei</th>
              <th>Typ</th>
              <th>Größe</th>
              <th>Gelöscht</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                if (!is_array($r)) continue;
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;

                $extR = (string)($r['ext'] ?? '');
                $display = (string)($r['display_filename'] ?? '');
                $size = (int)($r['size_bytes'] ?? 0);
                $sizeKb = ($size > 0) ? round($size / 1024, 1) : 0;

                $fid = (int)($r['folder_id'] ?? 0);
                $path = $folderNameById[$fid] ?? 'root';

                $deletedAt = (string)($r['deleted_at'] ?? '');
              ?>
              <tr>
                <td class="media-col-check">
                  <input type="checkbox" name="id[]" form="mediaRestoreForm" value="<?= $id ?>">
                </td>
                <td class="media-col-preview">
                  <?php if (strtolower($extR) === 'pdf'): ?>
                    <iframe class="media-preview-embed media-preview-embed--table" src="/media/file?id=<?= $id ?>#toolbar=0&navpanes=0&scrollbar=0&view=FitH" title="PDF-Vorschau" loading="lazy"></iframe>
                  <?php else: ?>
                    <img src="/media/thumb?id=<?= $id ?>" alt="">
                  <?php endif; ?>
                </td>
                <td>
                  <div class="media-row-file">
                    <div class="media-row-file__name"><?= h($display) ?></div>
                    <div class="media-row-file__meta">Pfad: <?= h($path) ?> · ID: <?= (int)$id ?></div>
                  </div>
                </td>
                <td><?= h(strtoupper($extR)) ?></td>
                <td><?= h((string)$sizeKb) ?> KB</td>
                <td><?= h($deletedAt !== '' ? $deletedAt : '–') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($rows as $r): ?>
          <?php
            if (!is_array($r)) continue;
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;

            $extR = (string)($r['ext'] ?? '');
            $display = (string)($r['display_filename'] ?? '');
            $size = (int)($r['size_bytes'] ?? 0);
            $sizeKb = ($size > 0) ? round($size / 1024, 1) : 0;

            $fid = (int)($r['folder_id'] ?? 0);
            $path = $folderNameById[$fid] ?? 'root';
          ?>
          <div class="media-card">
            <div class="media-card__thumb">
              <?php if (strtolower($extR) === 'pdf'): ?>
                <iframe class="media-preview-embed" src="/media/file?id=<?= $id ?>#toolbar=0&navpanes=0&scrollbar=0&view=FitH" title="PDF-Vorschau" loading="lazy"></iframe>
              <?php else: ?>
                <img src="/media/thumb?id=<?= $id ?>" alt="">
              <?php endif; ?>
              <label class="media-card__check">
                <input type="checkbox" name="id[]" form="mediaRestoreForm" value="<?= $id ?>">
              </label>
            </div>

            <div class="media-card__body">
              <div class="media-card__name"><?= h($display) ?></div>

              <div class="media-card__meta">
                <div>Typ: <?= h(strtoupper($extR)) ?></div>
                <div>ID: <?= (int)$id ?></div>
                <div>Pfad: <?= h($path) ?></div>
                <div><?= h((string)$sizeKb) ?> KB</div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

<script>
(() => {
  const viewSel = document.getElementById('mediaViewSelect');
  if (!viewSel) return;

  viewSel.addEventListener('change', () => {
    const v = (viewSel.value === 'grid') ? 'grid' : 'list';

    // CSRF aus admin_csrf_field()
    const csrf = document.querySelector('input[name="_token"]')?.value || '';
    if (csrf) {
      const body = new URLSearchParams();
      body.set('pref_key', 'media.view');
      body.set('pref_value', v);

      fetch('/prefs', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-CSRF-Token': csrf
        },
        body: body.toString(),
        credentials: 'same-origin'
      }).catch(() => {});
    }

    viewSel.form?.submit();
  });
})();
</script>
