<?php
/** @var array $block */
$e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$hero = $block;
if (isset($block['data']) && is_array($block['data'])) {
    $hero = array_merge($hero, $block['data']);
}
if (isset($block['payload']) && is_array($block['payload'])) {
    $hero = array_merge($hero, $block['payload']);
}
$bgStyle = '';
$styleParts = [];
$overlayRaw = 0.0;
if (isset($hero['overlay_opacity']) && is_numeric((string)$hero['overlay_opacity'])) {
    $overlayRaw = (float)$hero['overlay_opacity'];
} elseif (isset($hero['overlay']) && is_numeric((string)$hero['overlay'])) {
    $overlayRaw = (float)$hero['overlay'];
}
if ($overlayRaw < 0.0) $overlayRaw = 0.0;
if ($overlayRaw > 100.0) $overlayRaw = 100.0;
$overlayAlpha = $overlayRaw / 100.0;
$styleParts[] = '--hero-overlay-opacity:' . rtrim(rtrim(number_format($overlayAlpha, 2, '.', ''), '0'), '.');

$heightVh = 55;
if (isset($hero['height_vh']) && is_numeric((string)$hero['height_vh'])) {
    $heightVh = (int)round((float)$hero['height_vh']);
} elseif (isset($hero['height']) && is_numeric((string)$hero['height'])) {
    $heightVh = (int)round((float)$hero['height']);
}
if ($heightVh < 25) $heightVh = 25;
if ($heightVh > 100) $heightVh = 100;
$styleParts[] = '--hero-min-height:' . $heightVh . 'vh';

if (!empty($hero['image_url'])) {
    $styleParts[] = 'background-image:url(' . $e((string)$hero['image_url']) . ')';
    $styleParts[] = 'background-size:cover !important';
    $styleParts[] = 'background-repeat:no-repeat !important';
    $styleParts[] = 'background-position:50% 50% !important';

    if (isset($hero['image_url_focus_x']) || isset($hero['image_url_focus_y'])) {
        $px = focus_to_percent($hero['image_url_focus_x'] ?? null, 50.0);
        $py = focus_to_percent($hero['image_url_focus_y'] ?? null, 50.0);
        $styleParts[] = 'background-position:' . $px . '% ' . $py . '% !important';
    }
}
$bgStyle = ' style="' . implode(';', $styleParts) . '"';
?>
<section class="block block-hero"<?= $bgStyle ?>>
  <?php if (!empty($hero['headline'])): ?>
    <h1><?= $e((string)$hero['headline']) ?></h1>
  <?php endif; ?>
  <?php if (!empty($hero['subtitle'])): ?>
    <p><?= $e((string)$hero['subtitle']) ?></p>
  <?php endif; ?>
  <?php if (!empty($hero['button_url']) && !empty($hero['button_text'])): ?>
    <a href="<?= $e((string)$hero['button_url']) ?>" class="hero-btn">
      <?= $e((string)$hero['button_text']) ?>
    </a>
  <?php endif; ?>
</section>
