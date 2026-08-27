<?php
/** @var array $block */
/** @var array|null $data */
/** @var array $publicSettings */
/** @var CmsApiClient|null $client */

$p = [];
if (isset($data) && is_array($data)) {
    $p = $data;
} elseif (isset($block['data']) && is_array($block['data'])) {
    $p = array_merge($block, $block['data']);
} elseif (is_array($block)) {
    $p = $block;
}

$settings = is_array($publicSettings ?? null) ? $publicSettings : [];

$platformNames = [
    'facebook'  => 'Facebook',
    'instagram' => 'Instagram',
    'youtube'   => 'YouTube',
    'tiktok'    => 'TikTok',
    'x'         => 'X',
];

$platform = trim((string)($p['platform'] ?? ''));
if (!array_key_exists($platform, $platformNames)) {
    return;
}

$url = trim((string)($settings['social_' . $platform] ?? ''));
if ($url === '') {
    return;
}
if (!preg_match('#^https?://#i', $url)) {
    $url = 'https://' . ltrim($url, '/');
}

$style = trim((string)($p['style'] ?? 'embed'));
if (!in_array($style, ['embed', 'card'], true)) {
    $style = 'embed';
}

$label = trim((string)($p['label'] ?? ''));
if ($label === '') {
    $label = $platformNames[$platform];
}

$icons = [
    'facebook'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M14 8.5h-1.5a2 2 0 0 0-2 2V12H14l-.4 2.5h-1.9V21" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'instagram' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>',
    'youtube'   => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5.5" width="18" height="13" rx="4"/><path d="M10.5 9.5l5 2.5-5 2.5z" fill="currentColor" stroke="currentColor" stroke-linejoin="round"/></svg>',
    'tiktok'    => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 4v10.5a3 3 0 1 1-2.2-2.9"/><path d="M13 4a5 5 0 0 0 5 5"/></svg>',
    'x'         => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>',
];
$icon = $icons[$platform] ?? '';

$safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
$safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

// Live-Embeds ohne Drittanbieter-Tool: Facebook (Page Plugin) und X (Timeline-Widget)
// direkt über deren offizielle Widgets, YouTube über die Uploads-Playlist eines
// /channel/UC...-Links. Instagram wird über die vom CMS nativ (Meta Graph API,
// eigene OAuth-Anbindung unter Einstellungen) geladenen Beiträge als eigenes
// Bilder-Raster gerendert (siehe /instagram/media). TikTok bietet kein
// token-freies Profil-Embed, daher immer die Profil-Karte.
$embedProvider = null;
$embedHtml = '';
$embedScript = '';
$instagramItems = [];
$instagramUsername = '';

if ($style === 'embed') {
    if ($platform === 'facebook') {
        $embedProvider = 'facebook';
        $embedHtml = '<div class="fb-page" data-href="' . $safeUrl . '" data-tabs="timeline" data-width="500" data-height="600" data-small-header="false" data-adapt-container-width="true" data-hide-cover="false" data-show-facepile="true">'
            . '<blockquote cite="' . $safeUrl . '" class="fb-xfbml-parse-ignore"><a href="' . $safeUrl . '">' . $safeLabel . '</a></blockquote></div>';
        $embedScript = 'https://connect.facebook.net/de_DE/sdk.js#xfbml=1&version=v19.0';
    } elseif ($platform === 'x') {
        $embedProvider = 'x';
        $embedHtml = '<a class="twitter-timeline" data-height="600" href="' . $safeUrl . '">' . $safeLabel . '</a>';
        $embedScript = 'https://platform.twitter.com/widgets.js';
    } elseif ($platform === 'youtube' && preg_match('#/channel/(UC[A-Za-z0-9_-]{10,})#', $url, $m)) {
        $uploadsPlaylist = 'UU' . substr($m[1], 2);
        $embedProvider = 'youtube';
        $embedSrc = htmlspecialchars('https://www.youtube.com/embed/videoseries?list=' . $uploadsPlaylist, ENT_QUOTES, 'UTF-8');
        $embedHtml = '<div class="social-account-embed__ratio"><iframe src="' . $embedSrc . '" title="' . $safeLabel . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    } elseif ($platform === 'instagram' && isset($client) && $client instanceof CmsApiClient) {
        try {
            $igResult = $client->getInstagramMedia();
            if (!empty($igResult['connected']) && is_array($igResult['items'] ?? null)) {
                $instagramItems = $igResult['items'];
                $instagramUsername = trim((string)($igResult['username'] ?? ''));
            }
        } catch (\Throwable) {
            $instagramItems = [];
        }
    }
}
?>

<?php if (empty($GLOBALS['__social_account_embed_script_emitted'])): ?>
<?php $GLOBALS['__social_account_embed_script_emitted'] = true; ?>
<script>
(function () {
  if (window.__socialEmbedInit) return;
  window.__socialEmbedInit = true;
  var loadedScripts = {};

  function loadScriptOnce(src, onload) {
    if (!src) { onload(); return; }
    if (loadedScripts[src]) { onload(); return; }
    var s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = function () { loadedScripts[src] = true; onload(); };
    document.head.appendChild(s);
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-social-embed-trigger]');
    if (!btn) return;
    var wrap = btn.closest('.social-account-embed');
    if (!wrap) return;
    var tpl = wrap.querySelector('template');
    if (!tpl) return;
    var scriptSrc = wrap.getAttribute('data-embed-script') || '';
    var provider = wrap.getAttribute('data-embed-provider') || '';

    wrap.innerHTML = '';
    wrap.appendChild(tpl.content.cloneNode(true));

    if (provider === 'facebook' && !document.getElementById('fb-root')) {
      var root = document.createElement('div');
      root.id = 'fb-root';
      document.body.insertBefore(root, document.body.firstChild);
    }

    loadScriptOnce(scriptSrc, function () {
      if (provider === 'facebook' && window.FB && window.FB.XFBML) {
        window.FB.XFBML.parse(wrap);
      } else if (provider === 'x' && window.twttr && window.twttr.widgets) {
        window.twttr.widgets.load(wrap);
      }
    });
  });
})();
</script>
<?php endif; ?>

<?php if ($platform === 'instagram' && $instagramItems !== []): ?>
<section class="block block-social-account block-social-account--grid">
  <div class="social-account-grid">
    <?php foreach (array_slice($instagramItems, 0, 12) as $item): ?>
      <?php
        $itemUrl = trim((string)($item['permalink'] ?? ''));
        $itemImg = trim((string)($item['thumbnail_url'] ?? ''));
        if ($itemImg === '') {
            $itemImg = trim((string)($item['media_url'] ?? ''));
        }
        if ($itemUrl === '' || $itemImg === '') {
            continue;
        }
        $itemCaption = trim((string)($item['caption'] ?? ''));
        $itemAlt = $itemCaption !== '' ? mb_substr($itemCaption, 0, 120) : $label;
      ?>
      <a class="social-account-grid__item" href="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
        <img src="<?= htmlspecialchars($itemImg, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
      </a>
    <?php endforeach; ?>
  </div>
  <a class="social-account-grid__profile-link" href="<?= $safeUrl ?>" target="_blank" rel="noopener noreferrer">
    <span class="social-account-link__icon" aria-hidden="true"><?= $icon ?></span>
    <?= $instagramUsername !== '' ? '@' . htmlspecialchars($instagramUsername, ENT_QUOTES, 'UTF-8') : $safeLabel ?> auf Instagram ansehen
  </a>
</section>
<?php elseif ($embedProvider !== null): ?>
<section class="block block-social-account block-social-account--embed">
  <div class="social-account-embed" data-embed-provider="<?= htmlspecialchars($embedProvider, ENT_QUOTES, 'UTF-8') ?>" data-embed-script="<?= htmlspecialchars($embedScript, ENT_QUOTES, 'UTF-8') ?>">
    <template><?= $embedHtml ?></template>
    <button type="button" class="social-account-embed__trigger" data-social-embed-trigger>
      <span class="social-account-link__icon" aria-hidden="true"><?= $icon ?></span>
      <?= htmlspecialchars($platformNames[$platform] . '-Profil anzeigen', ENT_QUOTES, 'UTF-8') ?>
    </button>
    <p class="social-account-embed__hint">
      Beim Klick wird Inhalt von <?= htmlspecialchars($platformNames[$platform], ENT_QUOTES, 'UTF-8') ?> nachgeladen.
      Es gelten die Datenschutzbestimmungen des Anbieters.
    </p>
  </div>
</section>
<?php else: ?>
<section class="block block-social-account block-social-account--card">
  <a href="<?= $safeUrl ?>"
     class="social-account-link social-account-link--<?= htmlspecialchars($platform, ENT_QUOTES, 'UTF-8') ?>"
     target="_blank" rel="noopener noreferrer"
     aria-label="<?= htmlspecialchars($platformNames[$platform], ENT_QUOTES, 'UTF-8') ?>">
    <span class="social-account-link__icon" aria-hidden="true"><?= $icon ?></span>
    <span class="social-account-link__label"><?= $safeLabel ?></span>
  </a>
</section>
<?php endif; ?>
