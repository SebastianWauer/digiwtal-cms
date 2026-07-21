<?php
/** @var array $block */
$raw   = (string)($block['items_json'] ?? '[]');
$items = json_decode($raw, true);
if (!is_array($items)) $items = [];
$cols = htmlspecialchars((string)($block['cols'] ?? '3'), ENT_QUOTES, 'UTF-8');
?>
<div class="block block-gallery block-gallery-<?= $cols ?>">
  <?php if (!empty($block['headline'])): ?>
    <h2><?= htmlspecialchars((string)$block['headline'], ENT_QUOTES, 'UTF-8') ?></h2>
  <?php endif; ?>
  <div class="gallery-grid">
    <?php foreach ($items as $item): ?>
      <?php if (!is_array($item)) continue; ?>
      <figure>
        <img src="<?= htmlspecialchars((string)($item['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)($item['alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($item['caption'])): ?>
          <figcaption><?= htmlspecialchars((string)($item['caption'] ?? ''), ENT_QUOTES, 'UTF-8') ?></figcaption>
        <?php endif; ?>
      </figure>
    <?php endforeach; ?>
  </div>
</div>