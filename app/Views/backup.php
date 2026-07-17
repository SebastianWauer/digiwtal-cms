<?php
declare(strict_types=1);

/** @var array<int, array{name:string, path:string, size_kb:float, created_at:string}> $backups */

$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>

<div class="bk-wrap">

  <!-- ACTIONS -->
  <section class="card">
    <h3>Datenbank exportieren</h3>
    <p class="bk-sub">Exportiert alle relevanten Tabellen als SQL-Datei und speichert sie zusätzlich unter <code>storage/backups/</code>.</p>

    <form method="post" action="/backup/db" class="bk-form">
      <?= admin_csrf_field() ?>
      <button type="submit" class="btn btn--primary">SQL-Backup herunterladen</button>
    </form>
  </section>

  <!-- BACKUP LIST -->
  <section class="card">
    <h3>Letzte Backups</h3>

    <?php if (empty($backups)): ?>
      <p class="bk-empty">Noch keine Backups vorhanden.</p>
    <?php else: ?>
      <table class="bk-table">
        <thead>
          <tr>
            <th>Dateiname</th>
            <th>Erstellt am</th>
            <th class="bk-right">Größe</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($backups as $b): ?>
            <tr>
              <td class="bk-name"><?= $h($b['name']) ?></td>
              <td><?= $h($b['created_at']) ?></td>
              <td class="bk-right bk-muted"><?= $h((string)$b['size_kb']) ?> KB</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

</div>

<style>
.bk-wrap        { display: flex; flex-direction: column; gap: 1.5rem; max-width: 860px; }
.bk-sub         { margin: .25rem 0 1rem; color: var(--c-text-muted, #888); font-size: .875rem; }
.bk-form        { margin-top: .5rem; }
.bk-empty       { color: var(--c-text-muted, #888); font-size: .9rem; }
.bk-table       { width: 100%; border-collapse: collapse; font-size: .875rem; }
.bk-table th,
.bk-table td    { padding: .5rem .75rem; border-bottom: 1px solid var(--c-border, #333); text-align: left; }
.bk-table th    { font-weight: 600; color: var(--c-text-muted, #888); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
.bk-table tbody tr:last-child td { border-bottom: none; }
.bk-table tbody tr:hover td      { background: var(--c-row-hover, rgba(255,255,255,.04)); }
.bk-right       { text-align: right !important; }
.bk-muted       { color: var(--c-text-muted, #888); }
.bk-name        { font-family: monospace; font-size: .8125rem; word-break: break-all; }
</style>
