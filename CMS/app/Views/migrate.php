<?php
declare(strict_types=1);

/** @var ?array $flash */
/** @var ?array $result */

$baselineAllowed = false;

try {
    $pdoTmp = function_exists('db') ? db() : null;
    if ($pdoTmp instanceof PDO) {
        $countTmp = (int)$pdoTmp->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
        $baselineAllowed = ($countTmp === 0);
    }
} catch (Throwable) {
    // schema_migrations existiert ggf. noch nicht => Erst-Setup
    $baselineAllowed = true;
}

echo flash_render($flash ?? null);
?>

<div class="migrate-card">

  <form method="post" action="/migrate/run" class="migrate-actions">
    <?= admin_csrf_field() ?>
    <button type="submit" class="btn">Migrationen ausführen</button>
    <a class="btn btn--ghost" href="/">Dashboard</a>
  </form>

  <form method="post" action="/migrate/baseline" class="migrate-actions" style="margin-top:10px;">
    <?= admin_csrf_field() ?>
    <button type="submit" class="btn btn--ghost btn--warn" <?= $baselineAllowed ? '' : 'disabled' ?>>
      Baseline setzen (als angewendet markieren)
    </button>
  </form>

  <p class="hint">
    Zugriff nur mit entsprechender Berechtigung (<code>system.migrate.run</code>).
  </p>
</div>

<?php if (is_array($result)): ?>
  <div class="migrate-card">
    <div class="migrate-title">Ausgabe</div>
    <pre class="migrate-log"><?php
      $lines = $result['log'] ?? [];
      if (is_array($lines)) {
        echo h(implode("\n", array_map('strval', $lines)));
      }
    ?></pre>
    <div class="migrate-foot">
      <?php
        $ran = (int)($result['ran'] ?? 0);
        $ok  = !empty($result['ok']);
        echo $ok ? "OK. Neue Migrationen: {$ran}" : "Fehler. Neue Migrationen: {$ran}";
      ?>
    </div>
  </div>
<?php endif; ?>
