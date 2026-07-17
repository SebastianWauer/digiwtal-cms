<?php
/** @var array $block */
$colCount = (int)($block['col_count'] ?? 2);
if ($colCount < 1 || $colCount > 5) $colCount = 2;
$blockTitle = trim((string)($block['title'] ?? ''));

$cols = [];
for ($i = 1; $i <= $colCount; $i++) {
    $title = trim((string)($block["col_{$i}_title"] ?? ''));
    $image = trim((string)($block["col_{$i}_image_url"] ?? ''));
    $text  = trim((string)($block["col_{$i}_text"]  ?? ''));
    $focusStyle = '';
    if (isset($block["col_{$i}_image_url_focus_x"]) || isset($block["col_{$i}_image_url_focus_y"])) {
        $px = focus_to_percent($block["col_{$i}_image_url_focus_x"] ?? null, 50.0);
        $py = focus_to_percent($block["col_{$i}_image_url_focus_y"] ?? null, 50.0);
        $focusStyle = ' style="object-position:' . $px . '% ' . $py . '%"';
    }
    if ($title !== '' || $image !== '' || $text !== '') {
        $cols[] = ['title' => $title, 'image' => $image, 'focus_style' => $focusStyle, 'text' => $text];
    }
}

if ($blockTitle === '' && empty($cols)) return;
?>
<div class="block block-columns block-columns-<?= $colCount ?>">
  <?php if ($blockTitle !== ''): ?>
    <h2><?= htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8') ?></h2>
  <?php endif; ?>
  <div class="block-columns__grid">
  <?php foreach ($cols as $col): ?>
    <div class="block-columns__col">
      <?php if ($col['image'] !== ''): ?>
        <img src="<?= htmlspecialchars($col['image'], ENT_QUOTES, 'UTF-8') ?>" alt=""<?= $col['focus_style'] ?>>
      <?php endif; ?>
      <?php if ($col['title'] !== ''): ?>
        <h3><?= htmlspecialchars($col['title'], ENT_QUOTES, 'UTF-8') ?></h3>
      <?php endif; ?>
      <?php if ($col['text'] !== ''): ?>
        <div><?= nl2br(htmlspecialchars($col['text'], ENT_QUOTES, 'UTF-8')) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div>
