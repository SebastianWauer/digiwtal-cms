<?php
/** @var ?array $flash */
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS – Passwort zurücksetzen</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<main class="container">
  <header class="header">
    <h1 class="title">Passwort zurücksetzen</h1>
    <p class="subtitle">Bitte E-Mail-Adresse eingeben.</p>
  </header>

  <?php if (is_array($flash)): ?>
    <div class="flash flash--<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars((string)$flash['msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/password-reset">
    <?= admin_csrf_field() ?>
    <ul class="list">
      <li class="row">
        <div class="content">
          <label class="chk">
            <span>E-Mail</span>
            <input type="email" name="email" required autocomplete="email">
          </label>
          <div class="actions">
            <button type="submit" class="btn">Reset-Link senden</button>
            <a href="/login" class="btn btn--ghost">Zurück zum Login</a>
          </div>
        </div>
      </li>
    </ul>
  </form>
</main>
</body>
</html>
