<?php
/** @var array $stats */
/** @var string $lastDate */
?>

<div class="stats">
  <div class="stat">
    <div class="stat__title">Seiten gesamt</div>
    <div class="stat__value"><?= (int)$stats['pageCount'] ?></div>
  </div>

  <div class="stat">
    <div class="stat__title">Veröffentlicht</div>
    <div class="stat__value"><?= (int)$stats['publishedCount'] ?></div>
  </div>

  <div class="stat">
    <div class="stat__title">Benutzer</div>
    <div class="stat__value"><?= (int)$stats['userCount'] ?></div>
  </div>

  <div class="stat">
    <div class="stat__title">Letzte Änderung</div>
    <div class="stat__bigdate"><?= h($lastDate) ?></div>
  </div>
</div>
