<?php
declare(strict_types=1);

echo flash_render($flash ?? null);

$csrf      = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
$verbunden = (bool)($verbunden ?? false);
$meldungen = is_array($meldungen ?? null) ? $meldungen : [];

$statusText = [
    'neu'         => 'Eingegangen',
    'in_arbeit'   => 'In Bearbeitung',
    'beantwortet' => 'Beantwortet',
    'erledigt'    => 'Erledigt',
];
$artText = ['problem' => 'Problem', 'vorschlag' => 'Vorschlag', 'frage' => 'Frage'];
$herkunft = (string)($_SERVER['HTTP_REFERER'] ?? '');
?>

<?php if (!$verbunden): ?>
  <div class="pages-toolbar" style="margin-bottom:1rem;">
    <p class="pages-hint">
      Diese Installation ist nicht mit der Verwaltung verbunden. Meldungen lassen sich deshalb
      nicht abschicken. Wende dich bitte direkt an deinen Betreuer.
    </p>
  </div>
<?php else: ?>

<form method="post" action="<?= cms_base_path() ?>/hilfe" class="form-reset" style="max-width:760px;">
  <?= $csrf ?>
  <input type="hidden" name="from_url" value="<?= h($herkunft) ?>">

  <div style="display:flex;flex-direction:column;gap:.75rem;">
    <label style="display:flex;flex-direction:column;gap:.35rem;">
      <span>Worum geht es?</span>
      <select class="pages-edit-input" name="kind">
        <option value="problem">Etwas funktioniert nicht</option>
        <option value="vorschlag">Ich habe einen Vorschlag</option>
        <option value="frage">Ich habe eine Frage</option>
      </select>
    </label>

    <label style="display:flex;flex-direction:column;gap:.35rem;">
      <span>Betreff</span>
      <input class="pages-edit-input" type="text" name="subject" maxlength="190" required
             placeholder="Kurz in einem Satz">
    </label>

    <label style="display:flex;flex-direction:column;gap:.35rem;">
      <span>Beschreibung</span>
      <textarea class="pages-edit-input" name="body" rows="8" required
                placeholder="Was hast du gemacht, was ist passiert, was hättest du erwartet?"></textarea>
    </label>

    <p class="pages-hint">
      CMS-Version, PHP-Version und die Seite, von der du kommst, werden automatisch mitgeschickt –
      das erspart Rückfragen.
    </p>

    <div>
      <button class="btn" type="submit">Meldung absenden</button>
    </div>
  </div>
</form>

<h2 style="margin-top:2rem;">Deine Meldungen</h2>

<?php if ($meldungen === []): ?>
  <p class="pages-hint">Noch keine Meldungen abgeschickt.</p>
<?php else: ?>
  <div class="pages-table-wrap" style="margin-top:.75rem;">
    <table class="pages-table">
      <thead>
        <tr>
          <th>Datum</th>
          <th>Art</th>
          <th>Betreff</th>
          <th>Status</th>
          <th>Antwort</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($meldungen as $meldung):
        $status = (string)($meldung['status'] ?? 'neu');
        $antwort = trim((string)($meldung['answer'] ?? ''));
      ?>
        <tr>
          <td><?= h((string)($meldung['created_at'] ?? '')) ?></td>
          <td><?= h($artText[(string)($meldung['kind'] ?? '')] ?? '—') ?></td>
          <td><?= h((string)($meldung['subject'] ?? '')) ?></td>
          <td><strong><?= h($statusText[$status] ?? $status) ?></strong></td>
          <td style="max-width:460px;white-space:pre-wrap;">
            <?php if ($antwort !== ''): ?>
              <?= h($antwort) ?>
            <?php else: ?>
              <span class="pages-hint">–</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php endif; ?>
