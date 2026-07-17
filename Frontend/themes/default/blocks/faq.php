<?php
/** @var array $block */
$raw   = (string)($block['items_json'] ?? '[]');
$items = json_decode($raw, true);
if (!is_array($items)) $items = [];
?>
<div class="block block-faq">
  <?php if (!empty($block['headline'])): ?>
    <h2><?= htmlspecialchars((string)$block['headline'], ENT_QUOTES, 'UTF-8') ?></h2>
  <?php endif; ?>

  <?php foreach ($items as $item): ?>
    <?php if (!is_array($item)) continue; ?>
    <details class="faq-item">
      <summary><?= htmlspecialchars((string)($item['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?></summary>
      <div class="faq-answer">
        <?= nl2br(htmlspecialchars((string)($item['a'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
      </div>
    </details>
  <?php endforeach; ?>
</div>
