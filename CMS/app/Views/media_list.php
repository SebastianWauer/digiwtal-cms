<?php
declare(strict_types=1);

if (!empty($_GET['picker'])) {
    // Picker-Parameter bei View-Wechsel & Filter-Submit erhalten
    echo '<base href="/media?picker=1">';
}

/**
 * Erwartete Variablen:
 * @var array  $rows
 * @var array  $folders
 * @var int    $folderId
 * @var string $q
 * @var string $ext
 * @var bool   $onlyUnused
 * @var string $view   // grid|list
 * @var ?array $flash
 */

echo flash_render($flash ?? null);
$pickerMode = (($_GET['picker'] ?? '') === '1');
$view = ($view === 'list') ? 'list' : 'grid';
$page = max(1, (int)($page ?? 1));
$perPage = max(1, (int)($perPage ?? 40));
$totalPages = max(1, (int)($totalPages ?? 1));
$total = max(0, (int)($total ?? 0));

$canEdit   = (function_exists('admin_can') && admin_can('media.edit'));
$canDelete = (function_exists('admin_can') && admin_can('media.delete'));

$folderNameById = [];
$folderById = [];
$childrenByParent = []; // parent_id(int|null) -> array of folder arrays
$parentById = [];       // id -> parent_id(int|null)


foreach ($folders as $f) {
    if (!is_array($f)) continue;
    $fid = (int)($f['id'] ?? 0);
    if ($fid <= 0) continue;

    $pidRaw = $f['parent_id'] ?? null;
    $pid = ($pidRaw === null) ? null : (int)$pidRaw;
    $name = (string)($f['name'] ?? '');

    $folderNameById[$fid] = $name;
    $folderById[$fid] = $f;
    $parentById[$fid] = $pid;

    $key = ($pid === null) ? 'null' : (string)$pid;
    $childrenByParent[$key] ??= [];
    $childrenByParent[$key][] = $f;
}

/**
 * Open-Set: alle Ancestors vom aktiven Folder sollen offen sein
 */
$openIds = [];
$cur = (int)$folderId;
$guard = 0;
while ($cur > 0 && $guard < 50) {
    $openIds[$cur] = true;
    $pid = $parentById[$cur] ?? null;
    if ($pid === null || $pid <= 0) break;
    $cur = (int)$pid;
    $guard++;
}

function media_folder_has_children(array $childrenByParent, int $id): bool {
    $k = (string)$id;
    return !empty($childrenByParent[$k]);
}
function media_folder_full_path(int $fid, array $parentById, array $folderNameById): string {
    if ($fid <= 0) return 'Root';

    $parts = [];
    $cur = $fid;
    $guard = 0;

    while ($cur > 0 && $guard < 50) {
        $parts[] = (string)($folderNameById[$cur] ?? ('#'.$cur));
        $pid = $parentById[$cur] ?? null;
        if ($pid === null) break;
        $cur = (int)$pid;
        $guard++;
    }

    $parts = array_reverse($parts);
    return mb_strtolower(implode('/', $parts), 'UTF-8');
}

function media_render_folder_nodes(
    array $childrenByParent,
    array $folderNameById,
    array $openIds,
    int $activeFolderId,
    string $view,
    bool $canEdit,
    int $parentIdOrNullFlag, // -1 means NULL, otherwise parent id
    int $depth = 0
): void {
    $key = ($parentIdOrNullFlag === -1) ? 'null' : (string)$parentIdOrNullFlag;
    $nodes = $childrenByParent[$key] ?? [];
    if (!$nodes) return;

    foreach ($nodes as $f) {
        if (!is_array($f)) continue;

        $fid = (int)($f['id'] ?? 0);
        if ($fid <= 0) continue;

        $name = (string)($f['name'] ?? '');
        $active = ($fid === (int)$activeFolderId);

        $hasChildren = media_folder_has_children($childrenByParent, $fid);
        $isOpen = isset($openIds[$fid]);

        $nodeClasses = 'media-folder-node';
        if ($hasChildren) {
            $nodeClasses .= $isOpen ? ' is-open' : ' is-collapsed';
        }

        $depthClass = 'media-folder--d' . (string)min(8, max(0, $depth));
        ?>
        <div class="<?= $nodeClasses ?>" data-folder-node="<?= (int)$fid ?>">
            <div class="media-folder-row">
                <?php if ($hasChildren): ?>
                    <button type="button"
                            class="media-folder-toggle"
                            aria-label="Ordner ein-/ausklappen"
                            data-folder-toggle="<?= (int)$fid ?>">
                        <span class="media-folder-toggle__chev" aria-hidden="true">›</span>
                    </button>
                <?php else: ?>
                    <span class="media-folder-toggle media-folder-toggle--spacer" aria-hidden="true"></span>
                <?php endif; ?>

                <a
                    class="media-folder <?= $active ? 'is-active' : '' ?> <?= $depthClass ?>"
                    href="/media?folder=<?= (int)$fid ?>&view=<?= h($view) ?>"
                    data-folder-id="<?= (int)$fid ?>"
                    <?php if ($canEdit && $fid > 1): ?>data-folder-drag-id="<?= (int)$fid ?>" draggable="true"<?php endif; ?>
                >
                    <span class="media-folder__icon" aria-hidden="true">📁</span>
                    <span class="media-folder__name"><?= h($name) ?></span>
                </a>
            </div>

            <?php if ($hasChildren): ?>
                <div class="media-folder-children" data-folder-children="<?= (int)$fid ?>">
                    <?php media_render_folder_nodes($childrenByParent, $folderNameById, $openIds, $activeFolderId, $view, $canEdit, $fid, $depth + 1); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

?>

<div class="media-page">

  <?php if ($canEdit): ?>
  <!-- Upload -->
  <div class="media-upload">
    <div class="media-upload__head">
      <div class="media-upload__title">Dateien hochladen</div>
      <div class="media-upload__actions">
        <button type="button" class="btn btn--ghost btn--sm" id="mediaBrowseBtn">Durchsuchen…</button>
        <button type="button" class="btn btn--primary btn--sm" id="mediaUploadBtn">Hochladen</button>
      </div>
    </div>

    <div class="media-dropzone" id="mediaDropzone">
      <div class="media-dropzone__hint">Drag &amp; Drop hier rein oder auf „Durchsuchen…“ klicken.</div>
      <div class="media-upload-preview" id="mediaUploadPreview" aria-hidden="true"></div>
    </div>

    <form method="post" action="/media/upload" enctype="multipart/form-data" class="media-upload__form" id="mediaUploadForm">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="folder_id" value="<?= (int)$folderId ?>">
      <input type="file" name="files[]" id="mediaFileInput" multiple class="media-file-input">
    </form>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="get" action="/media" class="media-filters">
    <input type="hidden" name="folder" value="<?= (int)$folderId ?>">
    <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">

    <div class="media-filters__row">
      <div class="media-filters__field">
        <div class="media-filters__label">Ansicht</div>
        <select name="view" class="media-select" id="mediaViewSelect">
          <option value="grid" <?= $view === 'grid' ? 'selected' : '' ?>>Kachelansicht</option>
          <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>Listenansicht</option>
        </select>
      </div>

      <div class="media-filters__field media-filters__field--grow">
        <div class="media-filters__label">Suche</div>
        <input type="text" name="q" class="media-input" value="<?= h($q) ?>" placeholder="Dateiname, Titel, Beschreibung…">
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

      <label class="media-check">
        <input type="checkbox" name="unused" value="1" <?= $onlyUnused ? 'checked' : '' ?>>
        <span>Nur unbenutzte Medien</span>
      </label>

      <button class="btn btn--sm" type="submit">Filter anwenden</button>
    </div>
  </form>

  <div class="media-main">

    <!-- Left: folders -->
    <aside class="media-folders">
      <div class="media-folders__head">
        <div class="media-folders__title">Ordner</div>
        <?php if ($canEdit && (int)$folderId > 1 && isset($folderById[(int)$folderId])): ?>
          <form method="post" action="/media/folder/delete" class="media-folders__delete" onsubmit="return confirm('Ordner wirklich löschen? Nur leere Ordner ohne Unterordner können gelöscht werden.');">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="folder_id" value="<?= (int)$folderId ?>">
            <button type="submit" class="btn btn--ghost btn--danger btn--sm">Löschen</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($canEdit): ?>
        <form method="post" action="/media/folder/create" class="media-folder-form is-open" id="mediaFolderForm">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="parent_id" value="<?= ((int)$folderId) === 0 ? 1 : (int)$folderId ?>">
          <div class="media-folder-input">
            <input type="text" name="name" class="media-input" placeholder="Neuer Ordner…">
            <button type="submit" class="btn btn--sm btn--primary add-folder-btn">+</button>
          </div>
        </form>
      <?php endif; ?>

      <div class="media-folder-tree" id="mediaFolderTree">
        <?php media_render_folder_nodes($childrenByParent, $folderNameById, $openIds, (int)$folderId, $view, $canEdit, -1, 0); ?>
      </div>
    </aside>

    <!-- Right: list/grid -->
    <section class="media-content">

      <div class="media-content__bar">
        <form method="post" action="/media/delete" id="mediaDeleteForm" class="media-bulk">
          <?= admin_csrf_field() ?>

          <div class="media-bulk__left">
            <?php if ($canDelete): ?>
              <button type="submit" class="btn btn--ghost btn--danger btn--sm">Löschen</button>
            <?php endif; ?>

            <a href="/media?folder=0&view=<?= h($view) ?>" class="btn btn--ghost btn--sm">Alle Medien</a>

            <?php if ($canDelete): ?>
              <a href="/media/deleted?view=<?= h($view) ?>" class="btn btn--ghost btn--sm">Papierkorb</a>
            <?php endif; ?>
          </div>

          <div class="media-bulk__hint">
            <?= $canDelete
              ? 'Hinweis: Verwendete Medien können nicht gelöscht werden und sind nicht auswählbar.'
              : 'Hinweis: Du darfst Medien nicht löschen (keine Berechtigung).'
            ?>
          </div>
        </form>
      </div>

      <?php if (!$rows): ?>
        <div class="pages-hint">Keine Medien gefunden.</div>
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
                  <th>Verwendet</th>
                  <th>Aktionen</th>
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
                    $usage = (int)($r['usage_count'] ?? 0);

                    $size = (int)($r['size_bytes'] ?? 0);
                    $sizeKb = ($size > 0) ? round($size / 1024, 1) : 0;

                    $fid = (int)($r['folder_id'] ?? 0);
                    $path = media_folder_full_path($fid, $parentById, $folderNameById);

                    $w = (int)($r['width'] ?? 0);
                    $hgt = (int)($r['height'] ?? 0);

                    $disabled = ($usage > 0) || !$canDelete;
                  ?>
                  <tr
                    class="media-drag-item"
                    data-media-id="<?= (int)$id ?>"
                    <?= $canEdit ? 'draggable="true"' : '' ?>
                  >
                    <td class="media-col-check">
                      <?php if ($canDelete): ?>
                        <input type="checkbox" name="id[]" form="mediaDeleteForm" value="<?= (int)$id ?>" <?= $disabled ? 'disabled' : '' ?>>
                      <?php endif; ?>
                    </td>

                    <td class="media-col-preview">
                      <?php if (strtolower($extR) === 'pdf'): ?>
                        <iframe class="media-preview-embed media-preview-embed--table" src="/media/file?id=<?= (int)$id ?>#toolbar=0&navpanes=0&scrollbar=0&view=FitH" title="PDF-Vorschau" loading="lazy"></iframe>
                      <?php else: ?>
                        <img src="/media/thumb?id=<?= (int)$id ?>" alt="">
                      <?php endif; ?>
                    </td>

                    <td>
                      <div class="media-row-file">
                        <div class="media-row-file__name"><?= h($display) ?></div>
                        <div class="media-row-file__meta">Pfad: <?= h($path) ?></div>
                      </div>
                    </td>

                    <td><?= h(strtoupper($extR)) ?></td>

                    <td>
                      <?= h((string)$sizeKb) ?> KB
                      <?php if ($w > 0 && $hgt > 0): ?>
                        <div class="media-row-dim"><?= (int)$w ?>×<?= (int)$hgt ?> px</div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <span class="media-used <?= (int)$usage === 0 ? 'pages-badge pages-badge--live' : 'pages-badge pages-badge--draft' ?>">
                        <?= (int)$usage ?>
                      </span>
                    </td>

                    <!-- ✅ FIX: Aktionen müssen in eine eigene TD-Zelle -->
                    <td class="media-col-actions">
                      <?php if ($pickerMode): ?>
                      <button
                        type="button"
                        class="btn btn--primary btn--sm"
                        data-pick-id="<?= (int)$id ?>"
                        data-pick-url="/media/file?id=<?= (int)$id ?>"
                      >Auswählen</button>
                    <?php else: ?>
                      <a class="btn btn--ghost btn--sm" href="/media/edit?id=<?= (int)$id ?>">Details</a>
                    <?php endif; ?>
                    </td>
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
                $usage = (int)($r['usage_count'] ?? 0);

                $size = (int)($r['size_bytes'] ?? 0);
                $sizeKb = ($size > 0) ? round($size / 1024, 1) : 0;

                $fid = (int)($r['folder_id'] ?? 0);
                $path = media_folder_full_path($fid, $parentById, $folderNameById);

                $w = (int)($r['width'] ?? 0);
                $hgt = (int)($r['height'] ?? 0);

                $disabled = ($usage > 0) || !$canDelete;
              ?>
              <div
                class="media-card media-drag-item"
                data-media-id="<?= (int)$id ?>"
                <?= $canEdit ? 'draggable="true"' : '' ?>
              >
                <div class="media-card__thumb">
                  <?php if (strtolower($extR) === 'pdf'): ?>
                    <iframe class="media-preview-embed" src="/media/file?id=<?= (int)$id ?>#toolbar=0&navpanes=0&scrollbar=0&view=FitH" title="PDF-Vorschau" loading="lazy"></iframe>
                  <?php else: ?>
                    <img src="/media/thumb?id=<?= (int)$id ?>" alt="">
                  <?php endif; ?>
                  <?php if ($canDelete): ?>
                  <label class="media-card__check">
                    <input type="checkbox" name="id[]" form="mediaDeleteForm" value="<?= (int)$id ?>" <?= $disabled ? 'disabled' : '' ?>>
                  </label>
                  <?php endif; ?>
                </div>

                <div class="media-card__body">
                  <div class="cms-media-card-title" title="<?= h($display) ?>">
                    <?= h($display) ?>
                  </div>

                  <div class="cms-media-card-meta">
                    <span>ID: <?= (int)$id ?></span>
                    <span>Typ: <?= h(strtoupper($extR)) ?></span>
                  </div>

                  <div class="cms-media-card-path">
                    Pfad: <?= h($path) ?>
                  </div>

                  <div class="cms-media-card-size">
                    <span><?= h((string)$sizeKb) ?> KB</span>
                    <?php if ($w > 0 && $hgt > 0): ?>
                      <span><?= (int)$w ?>×<?= (int)$hgt ?> px</span>
                    <?php endif; ?>
                  </div>

                  <div class="media-card__footer">
                    <td>
                      <span class="pages-badge <?= (int)$usage === 0 ? 'pages-badge--live' : 'pages-badge--draft' ?>">
                        <?= (int)$usage ?>
                      </span>
                    </td>
                    <?php if ($pickerMode): ?>
                      <button
                        type="button"
                        class="btn btn--primary btn--sm"
                        data-pick-id="<?= (int)$id ?>"
                        data-pick-url="/media/file?id=<?= (int)$id ?>"
                      >Auswählen</button>
                    <?php else: ?>
                      <a class="btn btn--ghost btn--sm" href="/media/edit?id=<?= (int)$id ?>">Details</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>

      <?php
        $baseParams = [
          'folder' => (int)$folderId,
          'view' => $view,
          'q' => $q,
          'ext' => $ext,
          'per_page' => (int)$perPage,
        ];
        if ($onlyUnused) {
            $baseParams['unused'] = '1';
        }
        if ($pickerMode) {
            $baseParams['picker'] = '1';
        }
        $prevParams = array_merge($baseParams, ['page' => max(1, $page - 1)]);
        $nextParams = array_merge($baseParams, ['page' => min($totalPages, $page + 1)]);
      ?>
      <div class="media-content__bar">
        <div class="media-bulk__left">
          <a class="btn btn--ghost btn--sm <?= $page <= 1 ? 'is-disabled' : '' ?>" href="/media?<?= h(http_build_query($prevParams)) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>Zurück</a>
          <span class="pages-hint">Seite <?= (int)$page ?> / <?= (int)$totalPages ?> · <?= (int)$total ?> Medien</span>
          <a class="btn btn--ghost btn--sm <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="/media?<?= h(http_build_query($nextParams)) ?>" <?= $page >= $totalPages ? 'aria-disabled="true"' : '' ?>>Weiter</a>
        </div>
      </div>

    </section>
  </div>
</div>

<?php if (!$pickerMode): ?>
<div class="media-lightbox" id="mediaLightbox" hidden>
  <div class="media-lightbox__backdrop" data-media-lightbox-close="1"></div>
  <div class="media-lightbox__panel" role="dialog" aria-modal="true" aria-label="Bildvorschau">
    <button type="button" class="btn btn--ghost btn--sm media-lightbox__close" data-media-lightbox-close="1">Schließen</button>
    <img id="mediaLightboxImage" class="media-lightbox__image" alt="">
    <div class="media-lightbox__actions">
      <a id="mediaLightboxEdit" class="btn btn--primary btn--sm" href="/media">Zur Bearbeitung</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<script>
(() => {
  // --- Upload UI ---
  const input = document.getElementById('mediaFileInput');
  const browseBtn = document.getElementById('mediaBrowseBtn');
  const uploadBtn = document.getElementById('mediaUploadBtn');
  const form = document.getElementById('mediaUploadForm');
  const dropzone = document.getElementById('mediaDropzone');
  const preview = document.getElementById('mediaUploadPreview');

  function humanPreview(files) {
    if (!files || !files.length) {
      preview.innerHTML = '';
      preview.setAttribute('aria-hidden', 'true');
      return;
    }

    const max = 10;
    const list = Array.from(files);
    const shown = list.slice(0, max);
    const rest = list.length - shown.length;

    preview.innerHTML = '';
    preview.setAttribute('aria-hidden', 'false');

    shown.forEach(f => {
      const item = document.createElement('div');
      item.className = 'media-upload-preview__item';

      const isImg = /^image\//.test(f.type) || /\.(png|jpe?g|webp|svg)$/i.test(f.name);
      if (isImg) {
        const img = document.createElement('img');
        img.alt = '';
        img.className = 'media-upload-preview__img';
        item.appendChild(img);

        const reader = new FileReader();
        reader.onload = () => { img.src = reader.result; };
        reader.readAsDataURL(f);
      } else {
        const box = document.createElement('div');
        box.className = 'media-upload-preview__file';
        box.textContent = f.name;
        item.appendChild(box);
      }

      preview.appendChild(item);
    });

    if (rest > 0) {
      const more = document.createElement('div');
      more.className = 'media-upload-preview__more';
      more.textContent = '+' + rest;
      preview.appendChild(more);
    }
  }

  browseBtn?.addEventListener('click', () => input?.click());
  uploadBtn?.addEventListener('click', () => form?.submit());

  input?.addEventListener('change', () => {
    humanPreview(input.files);
  });

  function onDropFiles(files) {
    if (!files || !files.length) return;
    const dt = new DataTransfer();
    Array.from(files).forEach(f => dt.items.add(f));
    input.files = dt.files;
    humanPreview(input.files);
  }

  dropzone?.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('is-drag');
  });

  dropzone?.addEventListener('dragleave', () => {
    dropzone.classList.remove('is-drag');
  });

  dropzone?.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('is-drag');
    onDropFiles(e.dataTransfer.files);
  });

  // --- ✅ Folder Tree Toggle ---
  const tree = document.getElementById('mediaFolderTree');
  if (tree) {
    tree.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-folder-toggle]');
      if (!btn) return;

      const id = btn.getAttribute('data-folder-toggle');
      if (!id) return;

      const node = tree.querySelector('[data-folder-node="' + id + '"]');
      if (!node) return;

      const isCollapsed = node.classList.contains('is-collapsed');
      node.classList.toggle('is-collapsed', !isCollapsed);
      node.classList.toggle('is-open', isCollapsed);

      try {
        const key = 'cms_media_tree';
        const raw = localStorage.getItem(key) || '{}';
        const st = JSON.parse(raw);
        st[id] = isCollapsed ? 1 : 0;
        localStorage.setItem(key, JSON.stringify(st));
      } catch (_) {}
    });

    try {
      const key = 'cms_media_tree';
      const raw = localStorage.getItem(key) || '{}';
      const st = JSON.parse(raw);
      Object.keys(st || {}).forEach(id => {
        const node = tree.querySelector('[data-folder-node="' + id + '"]');
        if (!node) return;
        if (node.classList.contains('is-open') && st[id] === 0) return;

        const wantOpen = (st[id] === 1);
        if (wantOpen) {
          node.classList.remove('is-collapsed');
          node.classList.add('is-open');
        } else {
          if (!node.classList.contains('is-open')) {
            node.classList.add('is-collapsed');
          }
        }
      });
    } catch (_) {}
  }

  // --- ✅ Drag&Drop: Media -> Folder ---
  const token = document.querySelector('input[name="_token"]')?.value || '';

  const dragItems = document.querySelectorAll('.media-drag-item[data-media-id]');
  dragItems.forEach(el => {
    el.addEventListener('dragstart', (e) => {
      const mid = el.getAttribute('data-media-id') || '';
      if (!mid) return;

      el.classList.add('is-dragging');
      try {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', mid);
      } catch (_) {}
    });

    el.addEventListener('dragend', () => {
      el.classList.remove('is-dragging');
    });
  });

  async function moveMedia(mediaId, folderId) {
    const fd = new FormData();
    fd.append('media_id', String(mediaId));
    fd.append('folder_id', String(folderId));
    if (token) fd.append('_token', token);

    const res = await fetch('/media/move', {
      method: 'POST',
      body: fd,
      headers: token ? {'X-CSRF-Token': token} : {},
      credentials: 'same-origin'
    });

    let data = null;
    try { data = await res.json(); } catch (_) {}

    if (!res.ok || !data || !data.ok) {
      const msg = (data && data.error) ? data.error : 'Move failed';
      alert('Verschieben fehlgeschlagen: ' + msg);
      return false;
    }
    return true;
  }

  async function moveFolder(folderId, targetParentId) {
    const fd = new FormData();
    fd.append('folder_id', String(folderId));
    fd.append('target_parent_id', String(targetParentId));
    if (token) fd.append('_token', token);

    const res = await fetch('/media/folder/move', {
      method: 'POST',
      body: fd,
      headers: token ? {'X-CSRF-Token': token} : {},
      credentials: 'same-origin'
    });

    if (res.redirected && res.url) {
      window.location.href = res.url;
      return true;
    }

    return res.ok;
  }

  const draggableFolders = document.querySelectorAll('.media-folder[data-folder-drag-id]');
  draggableFolders.forEach(a => {
    a.addEventListener('dragstart', (e) => {
      const folderDragId = a.getAttribute('data-folder-drag-id') || '';
      if (!folderDragId) return;

      try {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('application/x-media-folder', folderDragId);
      } catch (_) {}
    });
  });

  const folders = document.querySelectorAll('.media-folder[data-folder-id]');
  folders.forEach(a => {
    a.addEventListener('dragover', (e) => {
      e.preventDefault();
      a.classList.add('is-dropover');
      try { e.dataTransfer.dropEffect = 'move'; } catch (_) {}
    });
    a.addEventListener('dragleave', () => {
      a.classList.remove('is-dropover');
    });
    a.addEventListener('drop', async (e) => {
      e.preventDefault();
      a.classList.remove('is-dropover');

      const folderId = parseInt(a.getAttribute('data-folder-id') || '0', 10);
      if (!folderId) return;

      const draggedFolderId = parseInt((e.dataTransfer && e.dataTransfer.getData('application/x-media-folder')) || '0', 10);
      if (draggedFolderId > 0) {
        const ok = await moveFolder(draggedFolderId, folderId);
        if (ok) window.location.reload();
        return;
      }

      const mediaId = parseInt((e.dataTransfer && e.dataTransfer.getData('text/plain')) || '0', 10);
      if (!mediaId) return;

      const ok = await moveMedia(mediaId, folderId);
      if (ok) window.location.reload();
    });
  });

  // --- ✅ Pref speichern: media.view (grid/list) ---
  const viewSel = document.getElementById('mediaViewSelect');
  if (viewSel) {
    viewSel.addEventListener('change', () => {
      const v = (viewSel.value === 'list') ? 'list' : 'grid';

      // CSRF aus admin_csrf_field() (bestehendes Muster)
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

      // bestehendes Verhalten beibehalten (Reload mit GET-Param)
      viewSel.form?.submit();
    });
  }
})();
</script>
<?php endif; ?>
<?php if (!$pickerMode): ?>
<script>
(() => {
  const lightbox = document.getElementById('mediaLightbox');
  const imageEl = document.getElementById('mediaLightboxImage');
  const editEl = document.getElementById('mediaLightboxEdit');
  if (!lightbox || !imageEl || !editEl) return;

  function closeLightbox() {
    lightbox.hidden = true;
    imageEl.removeAttribute('src');
    document.body.style.overflow = '';
  }

  function openLightbox(mediaId) {
    const id = parseInt(String(mediaId || '0'), 10);
    if (!id) return;
    imageEl.src = '/media/file?id=' + id;
    editEl.href = '/media/edit?id=' + id;
    lightbox.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  document.addEventListener('click', (e) => {
    const closeTarget = e.target.closest('[data-media-lightbox-close="1"]');
    if (closeTarget) {
      e.preventDefault();
      closeLightbox();
      return;
    }

    const previewImage = e.target.closest('.media-col-preview img, .media-card__thumb img');
    if (!previewImage) return;
    const item = previewImage.closest('[data-media-id]');
    if (!item) return;
    e.preventDefault();
    openLightbox(item.getAttribute('data-media-id'));
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !lightbox.hidden) {
      closeLightbox();
    }
  });
})();
</script>
<?php endif; ?>
<?php if ($pickerMode): ?>
<script>
document.addEventListener('click', function(e){
  const btn = e.target.closest('[data-pick-id]');
  if (!btn) return;
  const id = parseInt(btn.getAttribute('data-pick-id'), 10) || 0;
  const url = String(btn.getAttribute('data-pick-url') || '').trim();
  if (id <= 0) return;
  if (!url) return;
  try {
    localStorage.setItem('cms_media_picked', JSON.stringify({
      id: id,
      url: url,
      ts: Date.now()
    }));
  } catch (_) {}

  if (window.opener && !window.opener.closed) {
    window.opener.postMessage({ type: 'media_picked', url: url }, '*');
    window.opener.postMessage({ type: 'media-picked', id: id }, '*');
    window.close();
    return;
  }

  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'media_picked', url: url }, '*');
    window.parent.postMessage({ type: 'media-picked', id: id }, '*');
  }
});
</script>
<?php endif; ?>
