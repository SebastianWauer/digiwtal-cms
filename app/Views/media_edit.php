<?php
declare(strict_types=1);

/**
 * Erwartet:
 * @var array  $row
 * @var array  $folders
 * @var array  $usages   // optional (kann leer sein)
 * @var ?array $flash
 * @var bool   $canEdit
 */

echo flash_render($flash ?? null);

$id       = (int)($row['id'] ?? 0);
$display  = (string)($row['display_filename'] ?? '');
$orig     = (string)($row['original_filename'] ?? '');
$ext      = (string)($row['ext'] ?? '');
$mime     = (string)($row['mime'] ?? '');
$size     = (int)($row['size_bytes'] ?? 0);
$usageCnt = (int)($row['usage_count'] ?? 0);
$fid      = (int)($row['folder_id'] ?? 0);

$title = (string)($row['title'] ?? '');
$alt   = (string)($row['alt_text'] ?? '');
$desc  = (string)($row['description'] ?? '');

$fxRaw = $row['focus_x'] ?? null;
$fyRaw = $row['focus_y'] ?? null;
$fx    = ($fxRaw === null || $fxRaw === '') ? '' : (string)$fxRaw;
$fy    = ($fyRaw === null || $fyRaw === '') ? '' : (string)$fyRaw;

$folderNameById = [];
foreach ($folders as $f) {
    if (!is_array($f)) continue;
    $folderNameById[(int)($f['id'] ?? 0)] = (string)($f['name'] ?? '');
}
$folderName = $folderNameById[$fid] ?? 'root';

$sizeKb = $size > 0 ? round($size / 1024, 1) : 0;
$w = (int)($row['width'] ?? 0);
$h = (int)($row['height'] ?? 0);
$isPdf = strtolower($ext) === 'pdf';
$supportsFocus = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp', 'svg'], true);
$previewUrl = '/media/file?id=' . (int)$id;
$pdfPreviewUrl = $previewUrl . '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
$canRotate = $canEdit && in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'], true);

$usages = $usages ?? [];
?>

<div class="media-edit">

  <div class="media-edit__grid">

    <!-- LEFT: Preview + Info -->
    <aside class="media-edit__left card">
      <div class="media-edit__preview<?= $isPdf ? ' is-pdf' : '' ?>">
        <?php if ($isPdf): ?>
          <iframe class="media-edit__preview-frame" src="<?= h($pdfPreviewUrl) ?>" title="PDF-Vorschau" loading="lazy"></iframe>
        <?php else: ?>
          <img src="<?= h($previewUrl) ?>" alt="" id="mediaFocusPreviewImage">
          <?php if ($supportsFocus): ?>
            <span class="media-edit__focus-dot" id="mediaFocusDot" aria-hidden="true"></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="media-edit__meta">
        <div class="media-edit__meta-row"><span>ID</span><strong><?= (int)$id ?></strong></div>
        <div class="media-edit__meta-row"><span>Pfad</span><strong><?= h($folderName) ?></strong></div>
        <div class="media-edit__meta-row"><span>Dateiname (Original)</span><strong><?= h($orig) ?></strong></div>
        <div class="media-edit__meta-row"><span>Dateiname (intern)</span><strong><?= h((string)$id) ?>.<?= h($ext) ?></strong></div>
        <div class="media-edit__meta-row"><span>Typ</span><strong><?= h(strtoupper($ext)) ?></strong></div>
        <div class="media-edit__meta-row"><span>Größe</span><strong><?= h((string)$sizeKb) ?> KB<?php if ($w>0 && $h>0): ?> · <?= (int)$w ?>×<?= (int)$h ?> px<?php endif; ?></strong></div>
        <div class="media-edit__meta-row"><span>Verwendet</span><strong><?= (int)$usageCnt ?>×</strong></div>
        <div class="media-edit__meta-row"><span>Hochgeladen am</span><strong><?= h((string)($row['created_at'] ?? '')) ?></strong></div>
      </div>

      <div class="media-edit__actions">
        <a class="btn btn--ghost btn--sm" href="/media?folder=<?= (int)$fid ?>">Zurück zur Medienübersicht</a>
        <a class="btn btn--ghost btn--sm" href="/media/file?id=<?= (int)$id ?>" target="_blank" rel="noopener">Datei öffnen</a>
        <?php if ($canRotate): ?>
          <form method="post" action="/media/rotate" class="media-edit__inline-form">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="direction" value="ccw">
            <button type="submit" class="btn btn--ghost btn--sm" title="90° gegen den Uhrzeigersinn">↺ 90°</button>
          </form>
          <form method="post" action="/media/rotate" class="media-edit__inline-form">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="direction" value="cw">
            <button type="submit" class="btn btn--ghost btn--sm" title="90° im Uhrzeigersinn">↻ 90°</button>
          </form>
        <?php endif; ?>
      </div>
    </aside>

    <!-- RIGHT: Form -->
    <section class="media-edit__right card">

      <form method="post" action="/media/save" class="media-edit__form">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="media-edit__title">Medium bearbeiten</div>

        <div class="field">
          <label class="label" for="title">Titel (optional)</label>
          <input
            class="input"
            type="text"
            id="title"
            name="title"
            value="<?= h($title) ?>"
            <?= $canEdit ? '' : 'disabled' ?>
          >
          <div class="hint">Wird z.B. als Bildtitel oder Caption genutzt.</div>
        </div>

        <div class="field">
          <label class="label" for="display_filename">Dateiname (angezeigter Name)</label>
          <input
            class="input"
            type="text"
            id="display_filename"
            name="display_filename"
            value="<?= h($display) ?>"
            <?= $canEdit ? '' : 'disabled' ?>
            required
          >
          <div class="hint">Ändert nur den angezeigten Dateinamen im System, nicht die physische Datei im Storage.</div>
        </div>

        <div class="field">
          <label class="label" for="folder_id">Ordner</label>
          <select
            class="select"
            id="folder_id"
            name="folder_id"
            <?= $canEdit ? '' : 'disabled' ?>
          >
            <?php foreach ($folders as $f): ?>
              <?php
                if (!is_array($f)) continue;
                $optId = (int)($f['id'] ?? 0);
                if ($optId <= 0) continue;
                $optName = (string)($f['name'] ?? '');
              ?>
              <option value="<?= (int)$optId ?>" <?= $optId === $fid ? 'selected' : '' ?>>
                <?= h($optName) ?><?= $optId === 1 ? ' (Root)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Ordnerwechsel ändert die Zuordnung im CMS.</div>
        </div>

        <div class="field">
          <label class="label" for="alt_text">Alt Text (für Bilder, Barrierefreiheit &amp; SEO)</label>
          <textarea
            class="textarea"
            id="alt_text"
            name="alt_text"
            rows="3"
            <?= $canEdit ? '' : 'disabled' ?>
          ><?= h($alt) ?></textarea>
          <div class="hint">Beschreibt den Bildinhalt, wichtig für Screenreader und Suchmaschinen.</div>
        </div>

        <div class="field">
          <label class="label" for="description">Beschreibung (optional)</label>
          <textarea
            class="textarea"
            id="description"
            name="description"
            rows="4"
            <?= $canEdit ? '' : 'disabled' ?>
          ><?= h($desc) ?></textarea>
        </div>

        <?php if ($supportsFocus): ?>
        <div class="field">
          <div class="label">Fokuspunkt (für Zuschnitte, -1 bis 1)</div>
          <div class="focus-grid">
            <div>
              <label class="sublabel" for="focus_x">X (%)</label>
              <input
                class="input"
                type="number"
                id="focus_x"
                name="focus_x"
                min="-1"
                max="1"
                step="0.0001"
                value="<?= h($fx) ?>"
                <?= $canEdit ? '' : 'disabled' ?>
              >
            </div>
            <div>
              <label class="sublabel" for="focus_y">Y (%)</label>
              <input
                class="input"
                type="number"
                id="focus_y"
                name="focus_y"
                min="-1"
                max="1"
                step="0.0001"
                value="<?= h($fy) ?>"
                <?= $canEdit ? '' : 'disabled' ?>
              >
            </div>
          </div>
          <div class="hint">Klicke ins Bild, um den Fokuspunkt zu setzen. Wertebereich: -1 (links/oben) bis 1 (rechts/unten).</div>
        </div>
        <?php elseif ($isPdf): ?>
        <div class="field">
          <div class="label">PDF-Vorschau</div>
          <div class="hint">Die Datei wird hier direkt im Browser eingebettet. Fokuspunkt und Rotation sind für PDFs nicht relevant.</div>
        </div>
        <?php endif; ?>

        <div class="form-actions">
          <?php if ($canEdit): ?>
            <button class="btn btn--primary" type="submit">Metadaten speichern</button>
            <a class="btn btn--ghost" href="/media/edit?id=<?= (int)$id ?>">Abbrechen</a>
          <?php else: ?>
            <div class="no-perm">Hinweis: Du hast keine Berechtigung zum Bearbeiten (<code>media.edit</code>).</div>
          <?php endif; ?>
        </div>
      </form>

      <div class="media-edit__usage">
        <div class="media-edit__usage-title">Verwendungen dieses Mediums</div>

        <?php if (!$usages): ?>
          <div class="usage-empty">Keine Verwendungen gefunden.</div>
        <?php else: ?>
          <div class="usage-table-wrap">
            <table class="usage-table">
              <thead>
                <tr>
                  <th>Typ</th>
                  <th>Entity-ID</th>
                  <th>Feld</th>
                  <th>Seit</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($usages as $u): ?>
                  <?php if (!is_array($u)) continue; ?>
                  <tr>
                    <td><?= h((string)($u['entity_type'] ?? $u['type'] ?? '')) ?></td>
                    <td><?= (int)($u['entity_id'] ?? 0) ?></td>
                    <td><?= h((string)($u['field'] ?? '')) ?></td>
                    <td><?= h((string)($u['created_at'] ?? $u['since'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </section>
  </div>
</div>

<?php if ($canEdit && $supportsFocus): ?>
<script>
(() => {
  const img = document.getElementById('mediaFocusPreviewImage');
  const dot = document.getElementById('mediaFocusDot');
  const inputX = document.getElementById('focus_x');
  const inputY = document.getElementById('focus_y');
  if (!img || !dot || !inputX || !inputY) return;

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  function setDotFromValues() {
    const x = Number(inputX.value);
    const y = Number(inputY.value);
    if (!Number.isFinite(x) || !Number.isFinite(y)) {
      dot.style.display = 'none';
      return;
    }
    const px = ((clamp(x, -1, 1) + 1) / 2) * 100;
    const py = ((clamp(y, -1, 1) + 1) / 2) * 100;
    dot.style.left = px + '%';
    dot.style.top = py + '%';
    dot.style.display = 'block';
  }

  function renderedImageBox() {
    const rect = img.getBoundingClientRect();
    const nw = img.naturalWidth || 0;
    const nh = img.naturalHeight || 0;
    if (nw <= 0 || nh <= 0 || rect.width <= 0 || rect.height <= 0) {
      return null;
    }

    const scale = Math.min(rect.width / nw, rect.height / nh);
    const w = nw * scale;
    const h = nh * scale;
    const x = rect.left + (rect.width - w) / 2;
    const y = rect.top + (rect.height - h) / 2;
    return { x, y, w, h };
  }

  img.addEventListener('click', (ev) => {
    const box = renderedImageBox();
    if (!box) return;

    const relX = (ev.clientX - box.x) / box.w;
    const relY = (ev.clientY - box.y) / box.h;

    if (relX < 0 || relX > 1 || relY < 0 || relY > 1) {
      return;
    }

    const fx = (relX * 2) - 1;
    const fy = (relY * 2) - 1;

    inputX.value = fx.toFixed(4);
    inputY.value = fy.toFixed(4);
    setDotFromValues();
  });

  inputX.addEventListener('input', setDotFromValues);
  inputY.addEventListener('input', setDotFromValues);
  img.addEventListener('load', setDotFromValues);

  setDotFromValues();
})();
</script>
<?php endif; ?>
