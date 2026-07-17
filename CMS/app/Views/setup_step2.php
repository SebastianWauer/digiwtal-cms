<?php
declare(strict_types=1);

/** @var int      $migrationCount  Anzahl gefundener .sql-Dateien */
/** @var int      $applied         Zuletzt angewendete Migrationen (nach POST) */
/** @var string[] $errors          Fehlerliste aus vorherigem POST */

$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS Setup – Schritt 2</title>
  <link rel="stylesheet" href="/assets/css/admin-layout.css">
  <link rel="stylesheet" href="/assets/css/admin-components.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-page,#111); }
    .setup-card { background:var(--bg-card,#1e1e1e); border:1px solid var(--border,#333); border-radius:10px; padding:2.5rem; width:100%; max-width:520px; }
    .setup-logo { font-size:1.5rem; font-weight:700; color:var(--color-primary,#7c6af7); margin-bottom:.25rem; }
    .setup-step { font-size:.8125rem; color:var(--text-muted,#888); margin-bottom:2rem; }
    .setup-h2 { font-size:1.125rem; font-weight:600; margin-bottom:1.25rem; color:var(--text,#f0f0f0); }
    .setup-info { background:var(--bg-alt,#252525); border-radius:8px; padding:1rem 1.25rem; font-size:.875rem; margin-bottom:1.25rem; color:var(--text,#ccc); }
    .setup-info strong { color:var(--text,#f0f0f0); }
    .setup-warn { background:#2a2210; border:1px solid #6a5a20; border-radius:6px; padding:.875rem 1rem; color:#ffd54f; font-size:.875rem; margin-bottom:1.25rem; }
    .setup-error { background:#3a1212; border:1px solid #7a2a2a; border-radius:6px; padding:.875rem 1rem; color:#ff8a80; font-size:.875rem; margin-bottom:1.25rem; }
    .setup-error ul { margin:.5rem 0 0 1.25rem; padding:0; }
    .setup-actions { margin-top:1.75rem; display:flex; justify-content:space-between; align-items:center; }
    .setup-separator { border:none; border-top:1px solid var(--border,#2a2a2a); margin:1.5rem 0; }
    .count-badge { display:inline-block; background:var(--color-primary,#7c6af7); color:#fff; border-radius:20px; padding:.1rem .6rem; font-size:.8125rem; font-weight:700; margin-left:.5rem; }
  </style>
</head>
<body>
<div class="setup-card">
  <div class="setup-logo">CMS Setup</div>
  <div class="setup-step">Schritt 2 von 3 – Datenbankmigrationen</div>

  <div class="setup-warn">
    ⚠️ <strong>Bitte zuerst ein Backup der Datenbank erstellen,</strong> bevor Migrationen ausgeführt werden.
  </div>

  <?php if (!empty($errors)): ?>
    <div class="setup-error">
      <strong>Fehler bei den Migrationen:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= $h((string)$e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="setup-h2">Migrationen</div>

  <div class="setup-info">
    Gefundene Migrationsdateien:
    <span class="count-badge"><?= $migrationCount ?></span>
    <br><br>
    Bereits angewendete Migrationen werden <strong>automatisch übersprungen</strong> (idempotent).
    Es werden nur neue Migrationen ausgeführt.
  </div>

  <?php if ($applied > 0 && empty($errors)): ?>
    <div class="setup-info" style="border:1px solid #2a5a2a; background:#192a19;">
      ✓ Zuletzt angewendet: <strong><?= $applied ?></strong> Migration(en).
    </div>
  <?php endif; ?>

  <hr class="setup-separator">

  <form method="post" action="/setup/step2">
    <?= admin_csrf_field() ?>
    <div class="setup-actions">
      <a href="/setup" class="btn btn--ghost btn--sm">← Zurück</a>
      <button type="submit" class="btn btn--primary">Migrationen ausführen →</button>
    </div>
  </form>
</div>
</body>
</html>
