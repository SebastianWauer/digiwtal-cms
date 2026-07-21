<?php
declare(strict_types=1);

echo flash_render($flash ?? null);

$id = (int)($row['id'] ?? 0);
$title = (string)($row['title'] ?? '');
$subtitle = (string)($row['subtitle'] ?? '');
$description = (string)($row['description'] ?? '');
$eventDateFrom = trim((string)($row['event_date_from'] ?? ''));
$eventDateTo = trim((string)($row['event_date_to'] ?? ''));
$categoryIdsCsv = trim((string)($row['category_ids_csv'] ?? ''));
$selectedCategoryIds = $categoryIdsCsv !== ''
  ? array_values(array_unique(array_filter(array_map(static fn($v): int => (int)$v, explode(',', $categoryIdsCsv)), static fn(int $v): bool => $v > 0)))
  : [];
$selectedLookup = array_fill_keys($selectedCategoryIds, true);
$categoryImageMediaMap = is_array($row['category_image_media_map'] ?? null) ? $row['category_image_media_map'] : [];
$categoryLinksMap = is_array($row['category_links_map'] ?? null) ? $row['category_links_map'] : [];
$youtubeUrl = (string)($row['youtube_url'] ?? '');
$isPublished = !empty($row['is_published']);
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>

<form method="post" action="/events/save" class="pages-edit-form events-edit-form" id="eventEditForm">
  <?= $csrfField ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="pages-edit-layout">
    <section class="pages-edit-left">
      <div class="pages-edit-quicknav">
        <a href="#section-event-basis">Basis</a>
        <a href="#section-event-kategorien">Kategorien & Inhalte</a>
        <a href="#section-event-content">Inhalt</a>
      </div>

      <div class="pages-edit-card" id="section-event-basis">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Event-Basisdaten</div>
            <div class="pages-edit-card-sub">Titel, Datum und Sichtbarkeit schnell bearbeiten.</div>
          </div>
        </div>

        <div class="pages-edit-fields">
          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Titel</div>
            <input class="pages-edit-input" type="text" name="title" value="<?= h($title) ?>" required>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Untertitel</div>
            <input class="pages-edit-input" type="text" name="subtitle" value="<?= h($subtitle) ?>" placeholder="optional">
          </div>

          <div class="pages-edit-grid2">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Von (Datum)</div>
              <input class="pages-edit-input" type="date" name="event_date_from" value="<?= h($eventDateFrom) ?>">
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Bis (Datum)</div>
              <input class="pages-edit-input" type="date" name="event_date_to" value="<?= h($eventDateTo) ?>">
            </div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Globale YouTube-URL (optional)</div>
            <input class="pages-edit-input" type="text" name="youtube_url" value="<?= h($youtubeUrl) ?>" placeholder="https://www.youtube.com/watch?v=...">
          </div>
        </div>
      </div>

      <div class="pages-edit-card" id="section-event-kategorien">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Kategorien & Inhalte</div>
            <div class="pages-edit-card-sub">Wie im Page-Edit: Auswahl hier, Konfiguration pro Kategorie im Popup.</div>
          </div>
        </div>

        <div class="pages-edit-fields">
          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Kategorien wählen und konfigurieren</div>
            <div class="events-edit-selector-grid">
              <?php foreach ($allCategories as $cat): ?>
                <?php
                  $cid = (int)($cat['id'] ?? 0);
                  if ($cid <= 0) {
                    continue;
                  }
                  $catName = (string)($cat['name'] ?? 'Kategorie');
                  $isSelected = isset($selectedLookup[$cid]);
                  $savedLinks = is_array($categoryLinksMap[$cid] ?? null) ? $categoryLinksMap[$cid] : [];
                  $savedLinkCount = 0;
                  foreach ($savedLinks as $entry) {
                    if (!is_array($entry)) continue;
                    $entryLabel = trim((string)($entry['label'] ?? ''));
                    $entryUrl = trim((string)($entry['url'] ?? ''));
                    $entryPdf = (int)($entry['pdf_media_id'] ?? 0);
                    if ($entryLabel !== '' && ($entryUrl !== '' || $entryPdf > 0)) {
                      $savedLinkCount++;
                    }
                  }
                ?>
                <div class="events-edit-select-tile<?= $isSelected ? ' is-active' : '' ?>" data-category-tile="<?= $cid ?>">
                  <label class="pages-edit-check">
                    <input type="checkbox" name="category_ids[]" value="<?= $cid ?>" data-category-checkbox="<?= $cid ?>" <?= $isSelected ? 'checked' : '' ?>>
                    <span><?= h($catName) ?></span>
                  </label>
                  <div class="events-edit-select-tile__meta">
                    <span data-category-link-count="<?= $cid ?>"><?= (int)$savedLinkCount ?></span> Eintrag(e)
                  </div>
                  <button
                    type="button"
                    class="btn btn--ghost btn--sm"
                    data-open-category-modal="<?= $cid ?>"
                    <?= $isSelected ? '' : 'disabled' ?>
                  >
                    Kategorie konfigurieren
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="pages-edit-field-hint">Einträge pro Kategorie im Popup pflegen: Weiterleitungslink, YouTube oder PDF.</div>
          </div>

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Neue Kategorie(n) (optional)</div>
            <input class="pages-edit-input" type="text" name="category_new" value="" placeholder="z.B. Motorsport, TV, Live">
            <div class="pages-edit-field-hint">Mehrere neue Kategorien mit Komma trennen.</div>
          </div>
        </div>
      </div>

      <div class="pages-edit-card" id="section-event-content">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Inhalt</div>
            <div class="pages-edit-card-sub">Hauptbeschreibung für das Event.</div>
          </div>
        </div>

        <div class="pages-edit-fields">
          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Beschreibung</div>
            <textarea class="pages-edit-textarea" name="description" rows="8"><?= h($description) ?></textarea>
          </div>
        </div>
      </div>

      <div class="pages-edit-actionsbar">
        <button type="submit" class="btn">Speichern</button>
        <a class="btn btn--ghost" href="/events">Zurück</a>
      </div>
    </section>

    <aside class="pages-edit-right">
      <div class="pages-edit-card pages-edit-card--compact">
        <div class="pages-edit-card-title">Status</div>
        <div class="pages-edit-card-sub">Steuert die Sichtbarkeit im Frontend.</div>
        <div class="pages-edit-fields">
          <label class="pages-edit-switch pages-edit-switch--status">
            <input type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>>
            <span class="pages-edit-switch__slider" aria-hidden="true"></span>
            <span class="pages-edit-switch__label">Event ist sichtbar</span>
          </label>
        </div>
      </div>
    </aside>
  </div>

  <?php foreach ($allCategories as $cat): ?>
    <?php
      $cid = (int)($cat['id'] ?? 0);
      if ($cid <= 0) {
        continue;
      }
      $catName = (string)($cat['name'] ?? 'Kategorie');
      $cmid = (int)($categoryImageMediaMap[$cid] ?? 0);
      $savedLinks = is_array($categoryLinksMap[$cid] ?? null) ? $categoryLinksMap[$cid] : [];
    ?>
    <div class="pages-edit-card pages-edit-modal events-edit-modal-cat" id="eventCategoryModal-<?= $cid ?>" data-category-modal="<?= $cid ?>">
      <button type="button" class="pages-edit-modal__close" data-close-category-modal="<?= $cid ?>" aria-label="Schließen">×</button>
      <div class="pages-edit-card-head">
        <div>
          <div class="pages-edit-card-title">Kategorie konfigurieren: <?= h($catName) ?></div>
          <div class="pages-edit-card-sub">Bild plus mehrere Einträge (Weiterleitung, YouTube, PDF).</div>
        </div>
      </div>

      <div class="pages-edit-fields">
        <div class="events-edit-category-card__section">
          <?php media_picker_render('Bild für ' . $catName, 'category_image_media_ids[' . $cid . ']', $cmid > 0 ? (string)$cmid : '', ['showPreview' => true]); ?>
        </div>

        <div class="events-edit-category-card__section">
          <div class="pages-edit-field-label">Einträge</div>
          <div class="pages-edit-field-hint">Typ wählen und dann Felder ausfüllen. Für PDF: URL oder Media-ID nutzen. Für YouTube optional Start/Ende setzen (steuert die Einbettung in „Nächstes Event“).</div>

          <div class="events-edit-links-list" data-links-list="<?= $cid ?>">
            <?php if ($savedLinks !== []): ?>
              <?php foreach ($savedLinks as $entry): ?>
                <?php
                  if (!is_array($entry)) {
                    continue;
                  }
                  $entryType = strtolower(trim((string)($entry['type'] ?? 'link')));
                  if (!in_array($entryType, ['link', 'youtube', 'pdf'], true)) {
                    $entryType = 'link';
                  }
                  $entryLabel = trim((string)($entry['label'] ?? ''));
                  $entryUrl = trim((string)($entry['url'] ?? ''));
                  $entryPdf = (int)($entry['pdf_media_id'] ?? 0);
                  $entryYoutubeStart = trim((string)($entry['youtube_start_at'] ?? ''));
                  $entryYoutubeEnd = trim((string)($entry['youtube_end_at'] ?? ''));
                  if ($entryYoutubeStart !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $entryYoutubeStart) === 1) {
                    $entryYoutubeStart = str_replace(' ', 'T', substr($entryYoutubeStart, 0, 16));
                  }
                  if ($entryYoutubeEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $entryYoutubeEnd) === 1) {
                    $entryYoutubeEnd = str_replace(' ', 'T', substr($entryYoutubeEnd, 0, 16));
                  }
                ?>
                <div class="events-edit-link-row" data-link-row>
                  <div class="events-edit-link-row__head">
                    <select class="pages-edit-input" name="category_links[<?= $cid ?>][type][]" data-link-type>
                      <option value="link" <?= $entryType === 'link' ? 'selected' : '' ?>>Weiterleitung</option>
                      <option value="youtube" <?= $entryType === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                      <option value="pdf" <?= $entryType === 'pdf' ? 'selected' : '' ?>>PDF</option>
                    </select>
                    <input class="pages-edit-input" type="text" name="category_links[<?= $cid ?>][label][]" value="<?= h($entryLabel) ?>" placeholder="Beschriftung, z.B. Tickets">
                    <button class="btn btn--ghost events-edit-link-remove" type="button" data-link-remove>Entfernen</button>
                  </div>
                  <div class="events-edit-link-row__body">
                    <input class="pages-edit-input" type="text" name="category_links[<?= $cid ?>][url][]" value="<?= h($entryUrl) ?>" placeholder="https://..." data-link-url>
                    <input class="pages-edit-input" type="number" min="0" step="1" name="category_links[<?= $cid ?>][pdf_media_id][]" value="<?= $entryPdf > 0 ? (int)$entryPdf : '' ?>" placeholder="PDF Media-ID" data-link-pdf-id>
                    <input class="pages-edit-input" type="datetime-local" name="category_links[<?= $cid ?>][youtube_start_at][]" value="<?= h($entryYoutubeStart) ?>" placeholder="YouTube Start" data-link-youtube-start>
                    <input class="pages-edit-input" type="datetime-local" name="category_links[<?= $cid ?>][youtube_end_at][]" value="<?= h($entryYoutubeEnd) ?>" placeholder="YouTube Ende" data-link-youtube-end>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="events-edit-links-actions">
            <button class="btn btn--ghost events-edit-link-add" type="button" data-link-add="<?= $cid ?>">+ Eintrag hinzufügen</button>
            <a class="btn btn--ghost" href="/media" target="_blank" rel="noopener">Medien öffnen</a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="pages-edit-modal-backdrop" id="eventsEditModalBackdrop" hidden></div>
</form>

<script>
(() => {
  const categoryBoxes = Array.from(document.querySelectorAll('input[data-category-checkbox]'));
  const openModalBtns = Array.from(document.querySelectorAll('[data-open-category-modal]'));
  const closeModalBtns = Array.from(document.querySelectorAll('[data-close-category-modal]'));
  const modalBackdrop = document.getElementById('eventsEditModalBackdrop');

  const closeAllCategoryModals = () => {
    document.querySelectorAll('[data-category-modal]').forEach((modal) => {
      modal.classList.remove('is-open');
    });
    if (modalBackdrop) {
      modalBackdrop.classList.remove('is-open');
      modalBackdrop.hidden = true;
    }
  };

  const openCategoryModal = (cid) => {
    const box = document.querySelector('input[data-category-checkbox="' + cid + '"]');
    if (!box || !box.checked) return;
    const modal = document.querySelector('[data-category-modal="' + cid + '"]');
    if (!modal) return;
    closeAllCategoryModals();
    modal.classList.add('is-open');
    if (modalBackdrop) {
      modalBackdrop.hidden = false;
      modalBackdrop.classList.add('is-open');
    }
  };

  const rowIsValid = (row) => {
    const typeEl = row.querySelector('[data-link-type]');
    const labelEl = row.querySelector('input[name*="[label]"]');
    const urlEl = row.querySelector('[data-link-url]');
    const pdfEl = row.querySelector('[data-link-pdf-id]');
    const type = typeEl ? String(typeEl.value || '').trim().toLowerCase() : 'link';
    const label = labelEl ? String(labelEl.value || '').trim() : '';
    const url = urlEl ? String(urlEl.value || '').trim() : '';
    const pdfId = pdfEl ? Number(pdfEl.value || 0) : 0;
    if (label === '') return false;
    if (type === 'pdf') return url !== '' || pdfId > 0;
    return url !== '';
  };

  const refreshLinkCount = (cid) => {
    const list = document.querySelector('[data-links-list="' + cid + '"]');
    const target = document.querySelector('[data-category-link-count="' + cid + '"]');
    if (!list || !target) return;
    const rows = Array.from(list.querySelectorAll('[data-link-row]'));
    const count = rows.filter((row) => rowIsValid(row)).length;
    target.textContent = String(count);
  };

  const applyRowTypeUi = (row) => {
    const typeEl = row.querySelector('[data-link-type]');
    const urlEl = row.querySelector('[data-link-url]');
    const pdfEl = row.querySelector('[data-link-pdf-id]');
    const yStartEl = row.querySelector('[data-link-youtube-start]');
    const yEndEl = row.querySelector('[data-link-youtube-end]');
    if (!typeEl || !urlEl || !pdfEl || !yStartEl || !yEndEl) return;
    const type = String(typeEl.value || '').trim().toLowerCase();
    const body = pdfEl.closest('.events-edit-link-row__body');

    if (type === 'youtube') {
      urlEl.placeholder = 'https://www.youtube.com/watch?v=...';
      pdfEl.readOnly = true;
      yStartEl.readOnly = false;
      yEndEl.readOnly = false;
      if (body) {
        body.classList.remove('is-pdf');
        body.classList.add('is-youtube');
      }
    } else if (type === 'pdf') {
      urlEl.placeholder = 'https://... oder /media/file?id=...';
      pdfEl.readOnly = false;
      yStartEl.readOnly = true;
      yEndEl.readOnly = true;
      if (body) {
        body.classList.add('is-pdf');
        body.classList.remove('is-youtube');
      }
    } else {
      urlEl.placeholder = 'https://...';
      pdfEl.readOnly = true;
      yStartEl.readOnly = true;
      yEndEl.readOnly = true;
      if (body) {
        body.classList.remove('is-pdf');
        body.classList.remove('is-youtube');
      }
    }
  };

  const createLinkRow = (categoryId) => {
    const row = document.createElement('div');
    row.className = 'events-edit-link-row';
    row.setAttribute('data-link-row', '1');
    row.innerHTML = [
      '<div class="events-edit-link-row__head">',
        '<select class="pages-edit-input" name="category_links[' + categoryId + '][type][]" data-link-type>',
          '<option value="link">Weiterleitung</option>',
          '<option value="youtube">YouTube</option>',
          '<option value="pdf">PDF</option>',
        '</select>',
        '<input class="pages-edit-input" type="text" name="category_links[' + categoryId + '][label][]" placeholder="Beschriftung, z.B. Tickets">',
        '<button class="btn btn--ghost events-edit-link-remove" type="button" data-link-remove>Entfernen</button>',
      '</div>',
      '<div class="events-edit-link-row__body">',
        '<input class="pages-edit-input" type="text" name="category_links[' + categoryId + '][url][]" placeholder="https://..." data-link-url>',
        '<input class="pages-edit-input" type="number" min="0" step="1" name="category_links[' + categoryId + '][pdf_media_id][]" placeholder="PDF Media-ID" data-link-pdf-id>',
        '<input class="pages-edit-input" type="datetime-local" name="category_links[' + categoryId + '][youtube_start_at][]" placeholder="YouTube Start" data-link-youtube-start>',
        '<input class="pages-edit-input" type="datetime-local" name="category_links[' + categoryId + '][youtube_end_at][]" placeholder="YouTube Ende" data-link-youtube-end>',
      '</div>'
    ].join('');
    applyRowTypeUi(row);
    return row;
  };

  categoryBoxes.forEach((box) => {
    const cid = box.getAttribute('data-category-checkbox');
    if (!cid) return;

    const tile = document.querySelector('[data-category-tile="' + cid + '"]');
    const openBtn = document.querySelector('[data-open-category-modal="' + cid + '"]');
    const list = document.querySelector('[data-links-list="' + cid + '"]');
    const addBtn = document.querySelector('[data-link-add="' + cid + '"]');

    const toggle = () => {
      const active = box.checked;
      if (tile) tile.classList.toggle('is-active', active);
      if (openBtn) openBtn.disabled = !active;
      if (active && list && list.querySelector('[data-link-row]') === null) {
        list.appendChild(createLinkRow(cid));
      }
      refreshLinkCount(cid);
      if (!active) {
        closeAllCategoryModals();
      }
    };

    box.addEventListener('change', toggle);
    toggle();

    if (openBtn) {
      openBtn.addEventListener('click', () => openCategoryModal(cid));
    }

    if (addBtn && list) {
      addBtn.addEventListener('click', () => {
        list.appendChild(createLinkRow(cid));
        refreshLinkCount(cid);
      });
    }

    if (list) {
      list.querySelectorAll('[data-link-row]').forEach((row) => applyRowTypeUi(row));
      list.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const row = target.closest('[data-link-row]');
        if (!row) return;
        if (target.matches('[data-link-type]')) {
          applyRowTypeUi(row);
        }
        refreshLinkCount(cid);
      });
      list.addEventListener('input', () => refreshLinkCount(cid));
    }
  });

  closeModalBtns.forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      closeAllCategoryModals();
    });
  });

  if (modalBackdrop) {
    modalBackdrop.addEventListener('click', closeAllCategoryModals);
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAllCategoryModals();
    }
  });

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (!target.matches('[data-link-remove]')) return;

    const row = target.closest('[data-link-row]');
    const list = row ? row.parentElement : null;
    if (!row || !list) return;

    const cid = String(list.getAttribute('data-links-list') || '');
    row.remove();
    if (cid !== '') refreshLinkCount(cid);
  });
})();
</script>
