<?php
declare(strict_types=1);

/** @var array  $cfg    db_config() Ergebnis */
/** @var string $error  Fehlermeldung aus vorherigem POST */

$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$host    = $h((string)($cfg['host']     ?? '127.0.0.1'));
$dbName  = $h((string)($cfg['name']     ?? ($cfg['database'] ?? '')));
$dbUser  = $h((string)($cfg['user']     ?? ($cfg['username'] ?? '')));
$appEnv  = $h(\App\Core\Env::get('APP_ENV', 'production'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS Setup – Schritt 1</title>
  <link rel="stylesheet" href="/assets/css/admin-layout.css">
  <link rel="stylesheet" href="/assets/css/admin-components.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-page,#111); }
    .setup-card { background:var(--bg-card,#1e1e1e); border:1px solid var(--border,#333); border-radius:10px; padding:2.5rem; width:100%; max-width:520px; }
    .setup-logo { font-size:1.5rem; font-weight:700; color:var(--color-primary,#7c6af7); margin-bottom:.25rem; }
    .setup-step { font-size:.8125rem; color:var(--text-muted,#888); margin-bottom:2rem; }
    .setup-h2 { font-size:1.125rem; font-weight:600; margin-bottom:1.25rem; color:var(--text,#f0f0f0); }
    .setup-row { display:flex; justify-content:space-between; align-items:center; padding:.5rem 0; border-bottom:1px solid var(--border,#2a2a2a); font-size:.875rem; }
    .setup-row:last-of-type { border-bottom:none; }
    .setup-label { color:var(--text-muted,#888); }
    .setup-value { color:var(--text,#f0f0f0); font-family:monospace; }
    .setup-badge { display:inline-block; padding:.15rem .5rem; border-radius:4px; font-size:.75rem; font-weight:600; }
    .badge-ok    { background:#1a3a1a; color:#4caf50; }
    .badge-prod  { background:#3a1a1a; color:#f44336; }
    .badge-dev   { background:#1a2a3a; color:#64b5f6; }
    .setup-error { background:#3a1212; border:1px solid #7a2a2a; border-radius:6px; padding:.875rem 1rem; color:#ff8a80; font-size:.875rem; margin-bottom:1.25rem; }
    .setup-actions { margin-top:1.75rem; display:flex; justify-content:flex-end; }
    .setup-separator { border:none; border-top:1px solid var(--border,#2a2a2a); margin:1.5rem 0; }
  </style>
</head>
<body>
<div class="setup-card">
  <div class="setup-logo">CMS Setup</div>
  <div class="setup-step">Schritt 1 von 3 – Datenbankverbindung prüfen</div>

  <?php if ($error !== ''): ?>
    <div class="setup-error">
      <strong>Verbindung fehlgeschlagen:</strong><br>
      <?= $h($error) ?>
    </div>
  <?php endif; ?>

  <div class="setup-h2">Konfiguration</div>

  <div class="setup-row">
    <span class="setup-label">APP_ENV</span>
    <span class="setup-value">
      <span class="setup-badge <?= $appEnv === 'production' ? 'badge-prod' : 'badge-dev' ?>">
        <?= $appEnv ?>
      </span>
    </span>
  </div>
  <div class="setup-row">
    <span class="setup-label">DB Host</span>
    <span class="setup-value"><?= $host ?></span>
  </div>
  <div class="setup-row">
    <span class="setup-label">DB Name</span>
    <span class="setup-value"><?= $dbName !== '' ? $dbName : '<em style="color:#666">nicht gesetzt</em>' ?></span>
  </div>
  <div class="setup-row">
    <span class="setup-label">DB User</span>
    <span class="setup-value"><?= $dbUser !== '' ? $dbUser : '<em style="color:#666">nicht gesetzt</em>' ?></span>
  </div>
  <div class="setup-row">
    <span class="setup-label">DB Passwort</span>
    <span class="setup-value" style="color:#555">••••••••</span>
  </div>

  <hr class="setup-separator">

  <form method="post" action="/setup/step1">
    <?= admin_csrf_field() ?>
    <div class="setup-actions">
      <button type="submit" class="btn btn--primary">Verbindung testen →</button>
    </div>
  </form>
</div>
</body>
</html>
