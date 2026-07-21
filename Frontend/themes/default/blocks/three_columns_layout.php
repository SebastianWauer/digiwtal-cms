<?php
/** @var array $block */
/** @var array $data */

$blockTitle = trim((string)($data['title'] ?? $block['title'] ?? ''));
$leftBlocks = is_array($data['left_blocks'] ?? null) ? $data['left_blocks'] : [];
$centerBlocks = is_array($data['center_blocks'] ?? null) ? $data['center_blocks'] : [];
$rightBlocks = is_array($data['right_blocks'] ?? null) ? $data['right_blocks'] : [];

$columns = [
    ['class' => 'is-left', 'blocks' => $leftBlocks],
    ['class' => 'is-center', 'blocks' => $centerBlocks],
    ['class' => 'is-right', 'blocks' => $rightBlocks],
];

$hasAny = false;
foreach ($columns as $column) {
    if ($column['blocks'] !== []) {
        $hasAny = true;
        break;
    }
}

if ($blockTitle === '' && !$hasAny) {
    return;
}
?>
<section class="block block-three-columns">
  <div class="block-three-columns__inner">
    <?php if ($blockTitle !== ''): ?>
      <h2 class="block-three-columns__title"><?= htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <div class="block-three-columns__grid">
      <?php foreach ($columns as $column): ?>
        <div class="block-three-columns__column <?= $column['class'] ?>">
          <?php if ($column['blocks'] !== []): ?>
            <?php render_page_blocks($column['blocks'], compact('contactFormStates', 'currentSlug', 'contactTurnstileSiteKey', 'publicSettings')); ?>
          <?php else: ?>
            <div class="block-three-columns__empty">Keine Inhalte</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
