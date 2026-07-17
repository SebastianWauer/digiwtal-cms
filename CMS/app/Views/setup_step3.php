<?php
declare(strict_types=1);

/** @var string[] $errors  Validierungsfehler aus vorherigem POST */
/** @var array    $old     Alte Formularwerte für Repopulation */

$h   = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$old = is_array($old) ? $old : [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS Setup – Schritt 3</title>
  <link rel="stylesheet" href="/assets/css/admin-layout.css">
  <link rel="stylesheet" href="/assets/css/admin-components.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-page,#111); }
    .setup-card { background:var(--bg-card,#1e1e1e); border:1px solid var(--border,#333); border-radius:10px; padding:2.5rem; width:100%; max-width:560px; }
    .setup-logo { font-size:1.5rem; font-weight:700; color:var(--color-primary,#7c6af7); margin-bottom:.25rem; }
    .setup-step { font-size:.8125rem; color:var(--text-muted,#888); margin-bottom:2rem; }
    .setup-h2 { font-size:1rem; font-weight:600; margin:1.5rem 0 1rem; color:var(--text,#f0f0f0); text-transform:uppercase; letter-spacing:.05em; font-size:.8125rem; }
    .setup-h2:first-of-type { margin-top:0; }
    .setup-field { margin-bottom:1rem; }
    .setup-label { display:block; font-size:.8125rem; color:var(--text-muted,#999); margin-bottom:.35rem; }
    .setup-input { width:100%; padding:.6rem .75rem; background:var(--bg-input,#2a2a2a); border:1px solid var(--border,#444); border-radius:6px; color:var(--text,#f0f0f0); font-size:.9375rem; font-family:inherit; }
    .setup-input:focus { outline:none; border-color:var(--color-primary,#7c6af7); }
    .setup-hint { font-size:.75rem; color:var(--text-muted,#666); margin-top:.3rem; }
    .setup-error { background:#3a1212; border:1px solid #7a2a2a; border-radius:6px; padding:.875rem 1rem; color:#ff8a80; font-size:.875rem; margin-bottom:1.5rem; }
    .setup-error ul { margin:.5rem 0 0 1.25rem; padding:0; }
    .setup-actions { margin-top:1.75rem; display:flex; justify-content:space-between; align-items:center; }
    .setup-separator { border:none; border-top:1px solid var(--border,#2a2a2a); margin:1.5rem 0; }
    .setup-opt-label { font-size:.75rem; color:var(--text-muted,#666); }
  </style>
</head>
<body>
<div class="setup-card">
  <div class="setup-logo">CMS Setup</div>
  <div class="setup-step">Schritt 3 von 3 – Admin-Account und Site-Einstellungen</div>

  <?php if (!empty($errors)): ?>
    <div class="setup-error">
      <strong>Bitte Fehler korrigieren:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= $h((string)$e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="/setup/finish" autocomplete="off">
    <?= admin_csrf_field() ?>

    <!-- Admin Account -->
    <div class="setup-h2">Admin-Account</div>

    <div class="setup-field">
      <label class="setup-label" for="admin_email">E-Mail-Adresse</label>
      <input class="setup-input" type="email" id="admin_email" name="admin_email"
             value="<?= $h((string)($old['admin_email'] ?? '')) ?>"
             placeholder="admin@example.com" required autocomplete="off">
    </div>

    <div class="setup-field">
      <label class="setup-label" for="admin_password">Passwort</label>
      <input class="setup-input" type="password" id="admin_password" name="admin_password"
             placeholder="Mindestens 10 Zeichen" required autocomplete="new-password">
      <div class="setup-hint">Mindestens 10 Zeichen.</div>
    </div>

    <div class="setup-field">
      <label class="setup-label" for="admin_password_confirm">Passwort bestätigen</label>
      <input class="setup-input" type="password" id="admin_password_confirm" name="admin_password_confirm"
             placeholder="Passwort wiederholen" required autocomplete="new-password">
    </div>

    <hr class="setup-separator">

    <!-- Site Settings -->
    <div class="setup-h2">Site-Einstellungen <span class="setup-opt-label">(optional)</span></div>

    <div class="setup-field">
      <label class="setup-label" for="site_name">Site-Name</label>
      <input class="setup-input" type="text" id="site_name" name="site_name"
             value="<?= $h((string)($old['site_name'] ?? '')) ?>"
             placeholder="Meine Website">
    </div>

    <div class="setup-field">
      <label class="setup-label" for="canonical_base">Canonical-Base-URL</label>
      <input class="setup-input" type="url" id="canonical_base" name="canonical_base"
             value="<?= $h((string)($old['canonical_base'] ?? '')) ?>"
             placeholder="https://example.com">
      <div class="setup-hint">Ohne abschließenden Schrägstrich. Wird für SEO und Sitemap genutzt.</div>
    </div>

    <hr class="setup-separator">

    <div class="setup-actions">
      <a href="/setup/step2" class="btn btn--ghost btn--sm">← Zurück</a>
      <button type="submit" class="btn btn--primary">Setup abschließen ✓</button>
    </div>
  </form>
</div>
</body>
</html>
