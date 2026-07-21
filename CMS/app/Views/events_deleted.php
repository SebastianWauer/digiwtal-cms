<?php
declare(strict_types=1);

echo flash_render($flash ?? null);
$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>
<div class="pages-actions">
  <a class="btn btn--ghost" href="/events">Zurück zu Events</a>
  <div class="pages-actions-right">
    <form method="post" action="/events/purge" class="form-reset" onsubmit="return confirm('Papierkorb wirklich leeren?');">
      <?= $csrfField ?>
      <button type="submit" class="btn btn--ghost btn--danger">Papierkorb leeren</button>
    </form>
  </div>
</div>

<div class="pages-card">
  <table class="pages-table">
    <thead>
      <tr>
        <th>Titel</th>
        <th>Datum</th>
        <th>Kategorie</th>
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
        <td class="pages-col-actions">
          <form method="post" action="/events/restore" class="form-reset">
            <?= $csrfField ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn btn--ghost btn--warn btn--badge">Wiederherstellen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

