<?php
/** @var array $block */
$headline = htmlspecialchars((string)($block['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
$caption = htmlspecialchars((string)($block['caption'] ?? ''), ENT_QUOTES, 'UTF-8');
$provider = htmlspecialchars((string)($block['provider'] ?? ''), ENT_QUOTES, 'UTF-8');
$videoId = htmlspecialchars((string)($block['video_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$videoUrl = htmlspecialchars((string)($block['video_url'] ?? ''), ENT_QUOTES, 'UTF-8');
$posterUrl = htmlspecialchars((string)($block['poster_url'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="block block-video">
  <?php if (!empty($headline)): ?>
    <h2><?= $headline ?></h2>
  <?php endif; ?>
  <?php if (!empty($caption)): ?>
    <p><?= $caption ?></p>
  <?php endif; ?>
  <?php if ($provider === 'youtube' && !empty($videoId)): ?>
    <iframe src="https://www.youtube-nocookie.com/embed/<?= $videoId ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
  <?php elseif ($provider === 'vimeo' && !empty($videoId)): ?>
    <iframe src="https://player.vimeo.com/video/<?= $videoId ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
  <?php elseif ($provider === 'self' && !empty($videoUrl)): ?>
    <video controls poster="<?= $posterUrl ?>">
      <source src="<?= $videoUrl ?>">
    </video>
  <?php endif; ?>
</section>