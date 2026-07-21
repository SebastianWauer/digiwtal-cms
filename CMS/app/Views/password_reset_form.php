<?php
/** @var ?array $flash */
/** @var string $token */
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CMS – Neues Passwort</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<main class="container">
  <header class="header">
    <h1 class="title">Neues Passwort setzen</h1>
    <p class="subtitle">Token ist 1 Stunde gültig.</p>
  </header>

  <?php if (is_array($flash)): ?>
    <div class="flash flash--<?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars((string)$flash['msg'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/password-reset/<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <?= admin_csrf_field() ?>
    <ul class="list">
      <li class="row">
        <div class="content">
          <label class="chk">
            <span>Neues Passwort</span>
            <input type="password" name="password" required autocomplete="new-password" minlength="8">
          </label>
          <label class="chk">
            <span>Passwort bestätigen</span>
            <input type="password" name="password_confirm" required autocomplete="new-password" minlength="8">
          </label>
          <div class="actions">
            <button type="submit" class="btn">Passwort speichern</button>
            <a href="/login" class="btn btn--ghost">Login</a>
          </div>
        </div>
      </li>
    </ul>
  </form>
</main>
</body>
</html>
