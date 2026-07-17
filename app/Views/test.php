<?php
/** @var array<string, string> $checks */
?>

<div class="card" style="max-width:600px; margin:2rem auto; padding:2rem; border-radius:8px; background:var(--card-bg, #fff); box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <h2 style="margin-top:0; color:var(--color-success, #2d9a4e);">&#10003; Alles OK &mdash; ich arbeite korrekt!</h2>
  <p style="color:var(--text-muted, #666); margin-bottom:1.5rem;">Diese Seite bestätigt, dass Routing, Controller und View-Rendering einwandfrei funktionieren.</p>

  <table style="width:100%; border-collapse:collapse;">
    <thead>
      <tr>
        <th style="text-align:left; padding:.5rem .75rem; border-bottom:2px solid var(--border, #e0e0e0); font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted, #888);">Check</th>
        <th style="text-align:left; padding:.5rem .75rem; border-bottom:2px solid var(--border, #e0e0e0); font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:var(--text-muted, #888);">Ergebnis</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($checks as $label => $value): ?>
      <tr>
        <td style="padding:.6rem .75rem; border-bottom:1px solid var(--border, #f0f0f0); font-weight:500;"><?= h($label) ?></td>
        <td style="padding:.6rem .75rem; border-bottom:1px solid var(--border, #f0f0f0); font-family:monospace;"><?= h($value) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p style="margin-top:1.5rem; margin-bottom:0;">
    <a href="/" style="color:var(--color-primary, #3b82f6);">&larr; Zurück zum Dashboard</a>
  </p>
</div>
