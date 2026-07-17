<?php
declare(strict_types=1);

echo flash_render($flash ?? null);

$canCreate = function_exists('admin_can') && admin_can('events.create');
$canEdit = function_exists('admin_can') && admin_can('events.edit');
$canDelete = function_exists('admin_can') && admin_can('events.delete');
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
$selectedCategoryId = (int)($_GET['category_id'] ?? 0);
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$yearOptions = is_array($availableYears ?? null) ? $availableYears : [];
?>
<div class="pages-actions">
  <?php if ($canCreate): ?>
    <a class="btn" href="/events/edit">Neues Event</a>
  <?php endif; ?>
  <div class="pages-actions-right">
    <a class="btn btn--ghost" href="/events/categories">Kategorien</a>

    <form method="get" action="/events" class="form-reset" style="display:flex;gap:.5rem;align-items:center;">
      <select class="pages-edit-input" name="category_id" style="min-width:220px;">
        <option value="0">Alle Kategorien</option>
        <?php foreach ($allCategories as $cat): ?>
          <?php $cid = (int)($cat['id'] ?? 0); ?>
          <option value="<?= $cid ?>" <?= $selectedCategoryId === $cid ? 'selected' : '' ?>>
            <?= h((string)($cat['name'] ?? 'Kategorie')) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select class="pages-edit-input" name="year" style="min-width:140px;">
        <?php foreach ($yearOptions as $yearOption): ?>
          <?php $yo = (int)$yearOption; if ($yo <= 0) continue; ?>
          <option value="<?= $yo ?>" <?= $selectedYear === $yo ? 'selected' : '' ?>><?= $yo ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn--ghost">Filtern</button>
    </form>

    <?php if (($deletedCount ?? 0) > 0): ?>
      <a class="btn btn--ghost" href="/events/deleted">Gelöschte Events (<?= (int)$deletedCount ?>)</a>
    <?php endif; ?>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th>Titel</th>
        <th>Datum</th>
        <th>Kategorie</th>
        <th>Status</th>
        <th class="pages-col-actions">Aktionen</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $id = (int)($r['id'] ?? 0);
        $title = (string)($r['title'] ?? '');
        $dateFrom = trim((string)($r['event_date_from'] ?? ''));
        $dateTo = trim((string)($r['event_date_to'] ?? ''));
        if ($dateFrom === '' && trim((string)($r['event_date'] ?? '')) !== '') {
          $dateFrom = (string)date('Y-m-d', (int)strtotime((string)$r['event_date']));
        }
        $cat = trim((string)($r['category_names'] ?? ''));
        if ($cat === '') {
          $cat = trim((string)($r['category_name'] ?? ''));
        }
        $isPublished = !empty($r['is_published']);
        $badgeClass = $isPublished ? 'pages-badge--live' : 'pages-badge--draft';
        $today = (string)date('Y-m-d');
        $effectiveEnd = $dateTo !== '' ? $dateTo : $dateFrom;
        if ($effectiveEnd === '' && trim((string)($r['event_date'] ?? '')) !== '') {
          $effectiveEnd = (string)date('Y-m-d', (int)strtotime((string)$r['event_date']));
        }
        $isExpired = $effectiveEnd !== '' && $effectiveEnd < $today;
        $dateLabel = '<span class="pages-hint">ohne Datum</span>';
        if ($dateFrom !== '' && $dateTo !== '') {
          $dateLabel = h((string)date('d.m.Y', (int)strtotime($dateFrom))) . ' - ' . h((string)date('d.m.Y', (int)strtotime($dateTo)));
        } elseif ($dateFrom !== '') {
          $dateLabel = h((string)date('d.m.Y', (int)strtotime($dateFrom)));
        } elseif ($dateTo !== '') {
          $dateLabel = h((string)date('d.m.Y', (int)strtotime($dateTo)));
        }
      ?>
      <tr>
        <td><strong><?= h($title) ?></strong></td>
        <td><?= $dateLabel ?></td>
        <td><?= $cat !== '' ? h($cat) : '<span class="pages-hint">ohne Kategorie</span>' ?></td>
        <td>
          <span class="pages-badge <?= $badgeClass ?>"><?= $isPublished ? 'Live' : 'Entwurf' ?></span>
          <?php if ($isExpired): ?>
            <span class="pages-badge pages-badge--expired">Abgelaufen</span>
          <?php endif; ?>
        </td>
        <td class="pages-col-actions">
          <div class="pages-actions-inline">
            <?php if ($canEdit): ?><a class="btn btn--ghost btn--badge btn--warn" href="/events/edit?id=<?= $id ?>">Bearbeiten</a><?php endif; ?>
            <?php if ($canDelete): ?>
              <form method="post" action="/events/delete" class="form-reset">
                <?= $csrfField ?>
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

