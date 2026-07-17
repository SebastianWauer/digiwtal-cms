<?php
declare(strict_types=1);

/** @var array $health */

$php      = is_array($health['php'] ?? null) ? $health['php'] : [];
$opcache  = is_array($health['opcache'] ?? null) ? $health['opcache'] : [];
$apcu     = is_array($health['apcu'] ?? null) ? $health['apcu'] : [];
$timing   = is_array($health['timing'] ?? null) ? $health['timing'] : [];
$responseMs = (float)($health['response_ms'] ?? 0.0);

$badge = static function (bool $ok): string {
    return $ok
        ? '<span class="hc-badge hc-badge--ok">OK</span>'
        : '<span class="hc-badge hc-badge--bad">FAIL</span>';
};

$fmt = static function ($v): string {
    if ($v === null) return '—';
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_scalar($v)) return (string)$v;
    return '—';
};

$opAvailable = (bool)($opcache['available'] ?? false);
$opLoaded    = (bool)($opcache['loaded'] ?? false);
$opEnabled   = (bool)($opcache['enabled'] ?? false);

$apAvailable = (bool)($apcu['available'] ?? false);
$apLoaded    = (bool)($apcu['loaded'] ?? false);
$apEnabled   = (bool)($apcu['enabled'] ?? false);

$timingStats = is_array($timing['stats'] ?? null) ? $timing['stats'] : [];
$timingPer   = is_array($timing['per_path'] ?? null) ? $timing['per_path'] : [];
$timingRec   = is_array($timing['records'] ?? null) ? $timing['records'] : [];

$tN   = (int)($timingStats['n'] ?? 0);
$avg  = (float)($timingStats['avg_ms'] ?? 0.0);
$p50  = (float)($timingStats['p50_ms'] ?? 0.0);
$p95  = (float)($timingStats['p95_ms'] ?? 0.0);
$max  = (float)($timingStats['max_ms'] ?? 0.0);

$avgDbMs = 0.0;
$avgDbQ  = 0;

if ($tN > 0 && $timingRec) {
    $sumDb = 0.0;
    $sumQ = 0;
    $count = 0;
    foreach ($timingRec as $r) {
        $sumDb += (float)($r['db_ms'] ?? 0.0);
        $sumQ  += (int)($r['db_q'] ?? 0);
        $count++;
        if ($count >= 200) break;
    }
    if ($count > 0) {
        $avgDbMs = $sumDb / $count;
        $avgDbQ  = (int)round($sumQ / $count);
    }
}

$problemZone = '—';
$hint = null;
$severity = 'ok';

if ($tN > 0) {
    if ($avg >= 350) $severity = 'bad';
    elseif ($avg >= 200) $severity = 'warn';

    if ($avg > 0 && $avgDbMs <= 20.0) {
        $problemZone = 'PHP / IO';
        $hint = 'Hohe Serverzeit bei niedriger DB-Zeit → meist Session/IO/Autoload/Rendering.';
    } elseif ($avg > 0 && $avgDbMs >= 80.0) {
        $problemZone = 'DB / Queries';
        $hint = 'DB-Zeit ist hoch → Query-Anzahl/Index/Locks prüfen.';
    } else {
        $problemZone = 'Gemischt';
        $hint = 'DB ist beteiligt, aber Non-DB dominiert ebenfalls.';
    }
}

$sevBadge = static function (string $sev): string {
    if ($sev === 'bad') return '<span class="hc-badge hc-badge--bad">KRITISCH</span>';
    if ($sev === 'warn') return '<span class="hc-badge hc-badge--warn">AUFFÄLLIG</span>';
    return '<span class="hc-badge hc-badge--ok">OK</span>';
};

$topRoutes = $timingPer ? array_slice($timingPer, 0, 5) : [];

?>

<div class="hc-grid">

  <!-- PERFORMANCE -->
  <section class="card hc-span-2">
    <h3>Performance</h3>

    <div>
      <form method="post" action="/system/health/reset">
        <?= admin_csrf_field() ?>
        <button type="submit" class="btn btn--ghost btn--danger">Messwerte zurücksetzen</button>
        <a class="btn btn--ghost" href="/system/health/api" target="_blank" rel="noopener">Health-API (JSON)</a>
      </form>
    </div>

    <?php if ($tN <= 0): ?>
      <div class="hc-hr"></div>
      <p class="hc-muted">Noch keine Messdaten. Öffne ein paar Seiten und lade dann diese Health-Seite neu.</p>
    <?php else: ?>
      <div class="hc-summary">
        <div class="hc-tile"><div class="hc-tile__label">Avg</div><div class="hc-tile__value"><?= h((string)$avg) ?> ms</div></div>
        <div class="hc-tile"><div class="hc-tile__label">P95</div><div class="hc-tile__value"><?= h((string)$p95) ?> ms</div></div>
        <div class="hc-tile"><div class="hc-tile__label">DB Anteil</div><div class="hc-tile__value"><?= h((string)round($avgDbMs, 2)) ?> ms</div></div>
        <div class="hc-tile"><div class="hc-tile__label">Avg DB q</div><div class="hc-tile__value"><?= h((string)$avgDbQ) ?></div></div>
        <div class="hc-tile"><div class="hc-tile__label">Problemzone</div><div class="hc-tile__value"><?= h($problemZone) ?></div></div>
        <div class="hc-tile"><div class="hc-tile__label">Status</div><div class="hc-tile__value"><?= $sevBadge($severity) ?></div></div>
      </div>

      <?php if ($hint): ?>
        <div class="hc-hr"></div>
        <div class="hc-muted"><?= h($hint) ?></div>
      <?php endif; ?>

      <?php if ($topRoutes): ?>
        <div class="hc-hr"></div>
        <div class="hc-table">
          <div class="hc-tr hc-th"><div>Langsamste Seiten (Ø)</div><div>n</div><div>Avg (ms)</div><div>Max (ms)</div></div>
          <?php foreach ($topRoutes as $r): ?>
            <div class="hc-tr">
              <div><?= h((string)($r['path'] ?? '')) ?></div>
              <div><?= h((string)($r['n'] ?? '')) ?></div>
              <div><?= h((string)($r['avg_ms'] ?? '')) ?></div>
              <div><?= h((string)($r['max_ms'] ?? '')) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="hc-hr"></div>
      <div class="hc-kv hc-kv--dense">
        <div>P50</div><div><?= h((string)$p50) ?> ms</div>
        <div>Max</div><div><?= h((string)$max) ?> ms</div>
        <div>Health Response</div><div><?= h((string)$responseMs) ?> ms</div>
        <div>Samples</div><div><?= h((string)$tN) ?></div>
      </div>
    <?php endif; ?>
  </section>

  <!-- RUNTIME (breit + keine 6-Spalten-Quetsche) -->
  <section class="card hc-span-2">
    <h3>Runtime</h3>

    <div class="hc-summary hc-summary--runtime">
      <div class="hc-tile">
        <div class="hc-tile__label">PHP Version</div>
        <div class="hc-tile__value"><?= h($fmt($php['php_version'] ?? null)) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">SAPI</div>
        <div class="hc-tile__value"><?= h($fmt($php['sapi'] ?? null)) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">memory_limit</div>
        <div class="hc-tile__value"><?= h($fmt($php['memory_limit'] ?? null)) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">max_execution_time</div>
        <div class="hc-tile__value"><?= h($fmt($php['max_execution_time'] ?? null)) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">OPcache Loaded</div>
        <div class="hc-tile__value"><?= $badge($opLoaded) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">OPcache Function</div>
        <div class="hc-tile__value"><?= $badge($opAvailable) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">OPcache Enabled</div>
        <div class="hc-tile__value"><?= $badge($opAvailable && $opEnabled) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">opcache.enable</div>
        <div class="hc-tile__value"><?= h($fmt($opcache['ini_enable'] ?? null)) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">APCu Loaded</div>
        <div class="hc-tile__value"><?= $badge($apLoaded) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">APCu Function</div>
        <div class="hc-tile__value"><?= $badge($apAvailable) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">APCu Enabled</div>
        <div class="hc-tile__value"><?= $badge($apAvailable && $apEnabled) ?></div>
      </div>

      <div class="hc-tile">
        <div class="hc-tile__label">apc.enabled</div>
        <div class="hc-tile__value"><?= h($fmt($apcu['ini_enabled'] ?? null)) ?></div>
      </div>
    </div>

    <div class="hc-hr"></div>
    <div class="hc-muted">
      Runtime ist bewusst kompakt: 4 Spalten auf Desktop, 2 auf Tablet, 1 auf Mobile – ohne Horizontal-Scroll.
    </div>
  </section>

  <!-- DETAILS -->
  <section class="card hc-span-2">
    <details>
      <summary><strong>Latest Requests</strong> <span class="hc-muted">(Details)</span></summary>

      <?php if (!$timingRec): ?>
        <p class="hc-muted">Keine Request-Details verfügbar.</p>
      <?php else: ?>
        <div class="hc-hr"></div>
        <div class="hc-table">
          <div class="hc-tr hc-th">
            <div>At (UTC)</div><div>Method</div><div>Path</div><div>Status</div><div>Total (ms)</div><div>DB (ms)</div><div>DB q</div><div>Peak mem</div>
          </div>
          <?php foreach (array_slice($timingRec, 0, 25) as $r): ?>
            <div class="hc-tr">
              <div><?= h((string)($r['at'] ?? '')) ?></div>
              <div><?= h((string)($r['method'] ?? '')) ?></div>
              <div><?= h((string)($r['path'] ?? '')) ?></div>
              <div><?= h((string)($r['status'] ?? '')) ?></div>
              <div><?= h((string)($r['total_ms'] ?? '')) ?></div>
              <div><?= h((string)($r['db_ms'] ?? '')) ?></div>
              <div><?= h((string)($r['db_q'] ?? '')) ?></div>
              <div><?= h((string)($r['peak_mem'] ?? '')) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </details>
  </section>

</div>
