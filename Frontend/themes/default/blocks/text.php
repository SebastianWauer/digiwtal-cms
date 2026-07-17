<?php
/** @var array $block */
/** @var array|null $data */
$payload = [];
if (isset($data) && is_array($data)) {
    $payload = $data;
} elseif (isset($block['data']) && is_array($block['data'])) {
    $payload = array_merge($block, $block['data']);
} elseif (is_array($block)) {
    $payload = $block;
}

$title = htmlspecialchars((string)($payload['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$subtitle = htmlspecialchars((string)($payload['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
$introRaw = trim((string)($payload['intro'] ?? ''));
$introPlain = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($introRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
$introEsc = htmlspecialchars($introPlain, ENT_QUOTES, 'UTF-8');
$allowedTags = '<p><br><strong><em><u><s><ul><ol><li><h2><h3><h4><blockquote><a>';
$textRaw = (string)($payload['text'] ?? '');
if (preg_match('/<[^>]+>/', $textRaw) === 1) {
    $normalized = $textRaw;

    // Word-like editor content often uses <div> per line.
    // Render these as line breaks (not paragraph blocks), so one Enter = one visible break.
    if (preg_match('#<div\b#i', $normalized) === 1 && preg_match('#<p\b#i', $normalized) !== 1) {
        $normalized = preg_replace('#<div\b[^>]*>#i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#</div>#i', "<br>\n", $normalized) ?? $normalized;
        $normalized = preg_replace('#(<br\s*/?>\s*){3,}#i', "<br><br>\n", $normalized) ?? $normalized;
        $normalized = preg_replace('#^(?:\s*<br\s*/?>)+#i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#(?:<br\s*/?>\s*)+$#i', '', $normalized) ?? $normalized;
    }

    $text = strip_tags($normalized, $allowedTags);
} else {
    $text = nl2br(htmlspecialchars($textRaw, ENT_QUOTES, 'UTF-8'));
}
$rawImageUrl = trim((string)($payload['image_url'] ?? ''));
$imageUrl = preg_match('#^(https?://|/)#i', $rawImageUrl) ? $rawImageUrl : '';
$imageSize = strtolower(trim((string)($payload['image_size'] ?? 'm')));
$imagePos = strtolower(trim((string)($payload['image_position'] ?? 'right')));
$imageCaption = htmlspecialchars((string)($payload['image_caption'] ?? ''), ENT_QUOTES, 'UTF-8');
$imageCredit = htmlspecialchars((string)($payload['image_credit'] ?? ''), ENT_QUOTES, 'UTF-8');
$imageFocusStyle = '';
if (isset($payload['image_url_focus_x']) || isset($payload['image_url_focus_y'])) {
    $px = focus_to_percent($payload['image_url_focus_x'] ?? null, 50.0);
    $py = focus_to_percent($payload['image_url_focus_y'] ?? null, 50.0);
    $imageFocusStyle = ' style="object-position:' . $px . '% ' . $py . '%"';
}

if (!in_array($imageSize, ['s', 'm', 'l', 'xl', 'full'], true)) {
    $imageSize = 'm';
}
if (!in_array($imagePos, ['left', 'right', 'top', 'bottom'], true)) {
    $imagePos = 'right';
}
$hasImage = $imageUrl !== '';
?>
<section class="block block-text block-text--image-<?= htmlspecialchars($imagePos, ENT_QUOTES, 'UTF-8') ?> <?= $hasImage ? 'block-text--has-image' : 'block-text--no-image' ?>">
  <div class="block-text__inner block-text__inner--<?= htmlspecialchars($imageSize, ENT_QUOTES, 'UTF-8') ?>">
    <div class="block-text__copy">
  <?php if ($title !== ''): ?>
    <h2><?= $title ?></h2>
  <?php endif; ?>
  <?php if ($subtitle !== ''): ?>
    <p class="block-text__subtitle"><?= $subtitle ?></p>
  <?php endif; ?>
  <?php if ($introEsc !== ''): ?>
    <p class="block-text__intro"><?= $introEsc ?></p>
  <?php endif; ?>
  <div class="block-text__content"><?= $text ?></div>
    </div>

  <?php if ($imageUrl !== ''): ?>
    <figure class="block-text__media">
      <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $imageCaption ?>"<?= $imageFocusStyle ?>>
      <?php if ($imageCaption !== '' || $imageCredit !== ''): ?>
        <figcaption>
          <?= $imageCaption ?>
          <?php if ($imageCaption !== '' && $imageCredit !== ''): ?> · <?php endif; ?>
          <?php if ($imageCredit !== ''): ?><span class="block-text__credit"><?= $imageCredit ?></span><?php endif; ?>
        </figcaption>
      <?php endif; ?>
    </figure>
  <?php endif; ?>
  </div>
</section>
