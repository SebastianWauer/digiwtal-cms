<?php
$imgUrl = htmlspecialchars((string)($block['url'] ?? ''), ENT_QUOTES, 'UTF-8');
$imgAlt = htmlspecialchars((string)($block['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
$focusStyle = '';
if (isset($block['url_focus_x']) || isset($block['url_focus_y'])) {
  $px = focus_to_percent($block['url_focus_x'] ?? null, 50.0);
  $py = focus_to_percent($block['url_focus_y'] ?? null, 50.0);
  $focusStyle = ' style="object-position:' . $px . '% ' . $py . '%"';
}
?>
<figure class="block block-image">
  <img src="<?= $imgUrl ?>"
       alt="<?= $imgAlt ?>"<?= $focusStyle ?>>
  <?php if (!empty($block['caption'])): ?>
    <figcaption><?= htmlspecialchars((string)$block['caption'], ENT_QUOTES, 'UTF-8') ?></figcaption>
  <?php endif; ?>
</figure>
