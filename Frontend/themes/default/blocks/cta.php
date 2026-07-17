<?php
/** @var array $block */
$style = htmlspecialchars((string)($block['style'] ?? 'primary'), ENT_QUOTES, 'UTF-8');
?>
<section class="block block-cta cta-<?= $style ?>">
  <?php if (!empty($block['headline'])): ?>
    <h2><?= htmlspecialchars((string)$block['headline'], ENT_QUOTES, 'UTF-8') ?></h2>
  <?php endif; ?>
  <?php if (!empty($block['text'])): ?>
    <p><?= nl2br(htmlspecialchars((string)$block['text'], ENT_QUOTES, 'UTF-8')) ?></p>
  <?php endif; ?>
  <?php if (!empty($block['button_url']) && !empty($block['button_text'])): ?>
    <a href="<?= htmlspecialchars((string)$block['button_url'], ENT_QUOTES, 'UTF-8') ?>"
       class="cta-btn">
      <?= htmlspecialchars((string)$block['button_text'], ENT_QUOTES, 'UTF-8') ?>
    </a>
  <?php endif; ?>
</section>
