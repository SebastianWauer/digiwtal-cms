<?php
/** @var array $block */
/** @var array $data */

$p = [];
if (isset($data) && is_array($data)) {
    $p = $data;
} elseif (isset($block['data']) && is_array($block['data'])) {
    $p = array_merge($block, $block['data']);
} elseif (is_array($block)) {
    $p = $block;
}

$headline = trim((string)($p['headline'] ?? 'Aktuelle News'));
$items = is_array($p['items'] ?? null) ? $p['items'] : [];
$showTeaser = ((string)($p['show_teaser'] ?? '1') !== '0');
?>
<section class="block block-news">
  <div class="block-news__inner">
    <?php if ($headline !== ''): ?><h2><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h2><?php endif; ?>
    <?php if ($items === []): ?>
      <p class="block-events__empty">Aktuell sind keine News verfuegbar.</p>
    <?php else: ?>
      <div class="block-events__grid">
        <?php foreach ($items as $item): ?>
          <?php
            $title = trim((string)($item['title'] ?? 'News'));
            $teaser = trim((string)($item['teaser'] ?? ''));
            $slug = trim((string)($item['slug'] ?? ''));
            $cat = trim((string)($item['category_name'] ?? ''));
            $img = trim((string)($item['image_url'] ?? ''));
            $publishedAt = trim((string)($item['published_at'] ?? ''));
            $dateLabel = $publishedAt !== '' ? date('d.m.Y', (int)strtotime($publishedAt)) : '';
          ?>
          <article class="block-events__item is-lead">
            <?php if ($img !== ''): ?><img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php endif; ?>
            <div class="block-events__body">
              <?php if ($cat !== ''): ?><div class="block-events__cat"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
              <?php if ($dateLabel !== ''): ?><div class="block-events__past-label"><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
              <h3><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
              <?php if ($showTeaser && $teaser !== ''): ?><p><?= nl2br(htmlspecialchars($teaser, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
              <?php if ($slug !== ''): ?><a class="block-events__open" href="/news/<?= rawurlencode($slug) ?>">Mehr lesen</a><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
