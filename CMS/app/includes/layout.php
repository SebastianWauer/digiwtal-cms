<?php
declare(strict_types=1);

require_once __DIR__ . '/components.php';
if (!(defined('ADMIN_HIDE_SIDEBAR') && ADMIN_HIDE_SIDEBAR)) {
    require_once __DIR__ . '/sidebar.php';
}


/**
 * Asset helper: Cache-Busting via filemtime().
 * Erwartet: public/assets/css/<file>
 */
function admin_asset_css(string $file): string
{
    $file = ltrim($file, '/');

    // Sicherheit: nur "admin-*.css" bzw. "*.css" ohne Pfadtricks zulassen
    if (!preg_match('/^[a-z0-9._-]+\.css$/i', $file)) {
        return '/assets/css/' . rawurlencode($file);
    }

    $abs = __DIR__ . '/../../public/assets/css/' . $file;
    $ver = is_file($abs) ? (string)filemtime($abs) : '';

    return '/assets/css/' . rawurlencode($file) . ($ver !== '' ? ('?v=' . $ver) : '');
}

/**
 * Asset helper: Cache-Busting via filemtime().
 * Erwartet: public/assets/js/<file>
 */
function admin_asset_js(string $file): string
{
    $file = ltrim($file, '/');

    // Sicherheit: nur "*.js" ohne Pfadtricks zulassen
    if (!preg_match('/^[a-z0-9._-]+\.js$/i', $file)) {
        return '/assets/js/' . rawurlencode($file);
    }

    $abs = __DIR__ . '/../../public/assets/js/' . $file;
    $ver = is_file($abs) ? (string)filemtime($abs) : '';

    return '/assets/js/' . rawurlencode($file) . ($ver !== '' ? ('?v=' . $ver) : '');
}

function admin_layout_begin(array $p): void
{
    $title    = (string)($p['title'] ?? 'CMS');
    $theme    = (string)($p['theme'] ?? 'dark');
    $active   = (string)($p['active'] ?? 'dashboard');
    $user     = is_array($p['user'] ?? null) ? $p['user'] : [];
    $next     = (string)($p['next'] ?? '/');
    $headline = (string)($p['headline'] ?? $title);
    $subtitle = (string)($p['subtitle'] ?? '');

    $sidebarCollapsed = false;
    if (!empty($user['id'])) {
        $sidebarCollapsed = (\admin_get_pref((int)$user['id'], 'ui.sidebar_collapsed', '0') === '1');
    }

    // optional: lädt /assets/css/admin-<pageCss>.css
    $pageCss  = (string)($p['pageCss'] ?? '');
    if ($pageCss !== '' && !preg_match('/^[a-z0-9_-]+$/', $pageCss)) {
        $pageCss = '';
    }

    $theme = ($theme === 'light') ? 'light' : 'dark';

    ?>
    <!doctype html>
    <html lang="de" data-theme="<?= h($theme) ?>">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= h($title) ?></title>
      <?php if ($fav = site_favicon_url()): ?>
        <link rel="icon" href="<?= h($fav) ?>">
      <?php endif; ?>

      <meta name="csrf-token" content="<?= h(admin_csrf_token()) ?>">
      <meta name="prefs-endpoint" content="<?= h(\App\Core\Paths::PREFS) ?>">

      <link rel="stylesheet" href="<?= h(admin_asset_css('admin-layout.css')) ?>">
      <link rel="stylesheet" href="<?= h(admin_asset_css('admin-sidebar.css')) ?>">
      <link rel="stylesheet" href="<?= h(admin_asset_css('admin-components.css')) ?>">
      <link rel="stylesheet" href="<?= h(admin_asset_css('admin-media-picker.css')) ?>">
      <?php if ($pageCss !== ''): ?>
        <link rel="stylesheet" href="<?= h(admin_asset_css('admin-' . $pageCss . '.css')) ?>">
      <?php endif; ?>
    </head>
    <body>
      <?php if (defined('ADMIN_HIDE_SIDEBAR') && ADMIN_HIDE_SIDEBAR): ?>
        <style>
          .admin-shell{
            grid-template-columns: 1fr !important;
          }
        </style>
      <?php endif; ?>

    <div class="admin-shell<?= $sidebarCollapsed ? ' is-sidebar-collapsed' : '' ?>">
      <button type="button" class="mobile-nav-backdrop" data-mobile-nav-close="1" aria-label="Navigation schließen"></button>
      <?php if (!(defined('ADMIN_HIDE_SIDEBAR') && ADMIN_HIDE_SIDEBAR)) {
          sidebar_render([
              'active' => $active,
              'user'   => $user,
              'next'   => $next,
              'theme'  => $theme,
          ]);
      } ?>
      <main class="main">
        <section class="panel">
          <div class="panel-head">
            <?php if (!(defined('ADMIN_HIDE_SIDEBAR') && ADMIN_HIDE_SIDEBAR)): ?>
              <button type="button" class="mobile-nav-toggle" aria-label="Navigation öffnen" title="Navigation öffnen">
                <span aria-hidden="true">☰</span>
              </button>
            <?php endif; ?>
            <h1 class="h1"><?= h($headline) ?></h1>
            <?php if ($subtitle !== ''): ?>
              <div class="welcome"><?= h($subtitle) ?></div>
            <?php endif; ?>
          </div>
          <div class="panel-body">
    <?php
}

function admin_layout_end(): void
{
    ?>
          </div>
        </section>
      </main>
    </div>

<script>
(function(){
  var shell = document.querySelector('.admin-shell');
  if (!shell) return;

  var btn = document.querySelector('.sidebar-toggle');
  if (!btn) return;

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? (m.getAttribute('content') || '') : '';
  }

  function prefsEndpoint() {
    var m = document.querySelector('meta[name="prefs-endpoint"]');
    return m ? (m.getAttribute('content') || '/prefs') : '/prefs';
  }

  function saveCollapsed(isCollapsed) {
    var token = csrfToken();
    if (!token) return;

    var body = new URLSearchParams();
    body.set('pref_key', 'ui.sidebar_collapsed');
    body.set('pref_value', isCollapsed ? '1' : '0');
    body.set('format', 'json');

    fetch(prefsEndpoint(), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': token
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).catch(function(){});
  }

  btn.addEventListener('click', function(){
    shell.classList.toggle('is-sidebar-collapsed');
    saveCollapsed(shell.classList.contains('is-sidebar-collapsed'));
  });
})();
</script>
<script>
(function(){
  var shell = document.querySelector('.admin-shell');
  if (!shell) return;

  var toggle = document.querySelector('.mobile-nav-toggle');
  var close = document.querySelector('[data-mobile-nav-close="1"]');
  var sidebar = document.querySelector('.sidebar');
  if (!toggle || !sidebar) return;

  function isMobile() {
    return window.matchMedia('(max-width: 820px)').matches;
  }

  function setOpen(open) {
    if (!isMobile()) {
      shell.classList.remove('is-mobile-nav-open');
      document.body.classList.remove('is-mobile-nav-open');
      return;
    }
    shell.classList.toggle('is-mobile-nav-open', open);
    document.body.classList.toggle('is-mobile-nav-open', open);
  }

  toggle.addEventListener('click', function(){
    setOpen(!shell.classList.contains('is-mobile-nav-open'));
  });

  if (close) {
    close.addEventListener('click', function(){
      setOpen(false);
    });
  }

  sidebar.addEventListener('click', function(ev){
    var navLink = ev.target.closest('.nav__item');
    if (navLink) setOpen(false);
  });

  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape') setOpen(false);
  });

  window.addEventListener('resize', function(){
    if (!isMobile()) {
      setOpen(false);
    }
  });
})();
</script>
<script>
(function(){
  function getCsrf(){
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function applyTheme(next){
    document.documentElement.setAttribute('data-theme', next);

    var logo = document.getElementById('cmsBrandLogo');
    if (logo) {
      var light = logo.getAttribute('data-logo-light');
      var dark  = logo.getAttribute('data-logo-dark');
      if (next === 'light' && light) logo.setAttribute('src', light);
      if (next === 'dark' && dark)  logo.setAttribute('src', dark);
    }
  }

  document.addEventListener('submit', function(ev){
    var form = ev.target;
    if (!form || !(form instanceof HTMLFormElement)) return;

    // Theme-Form erkennen (bestehendes Muster: action "/theme")
    var action = form.getAttribute('action') || '';
    if (action !== '/theme') return;

    ev.preventDefault();

    var fd = new FormData(form);
    var next = String(fd.get('theme') || '');
    if (next !== 'light' && next !== 'dark') return;


    fetch(action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': getCsrf() }
    }).then(function(){
      applyTheme(next);
    }).catch(function(){
      // wenn Request fehlschlägt: nichts ändern (keine inkonsistente UI)
    });
  }, true);
})();
</script>
<div class="mp-modal" id="mpModal" hidden>
  <div class="mp-modal__backdrop" data-mp-close="1"></div>

  <div class="mp-modal__panel" role="dialog" aria-modal="true" aria-label="Media Picker">
    <div class="mp-modal__head">
      <div class="mp-modal__title" id="mpModalTitle">Medien wählen</div>
      <button type="button" class="btn btn--ghost btn--sm" data-mp-close="1">Schließen</button>
    </div>

    <iframe class="mp-modal__frame" id="mpModalFrame" src="about:blank"></iframe>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('mpModal');
  const frame = document.getElementById('mpModalFrame');
  const title = document.getElementById('mpModalTitle');
  if (!modal || !frame || !title) return;

  let currentInputName = null;

  function openPicker(inputName, label){
    currentInputName = inputName;
    title.textContent = label ? ('Medien wählen: ' + label) : 'Medien wählen';
    frame.src = '/media?picker=1';
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closePicker(){
    modal.hidden = true;
    frame.src = 'about:blank';
    currentInputName = null;
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.mp__open');
    if (btn){
      const inputName = btn.getAttribute('data-mp-input') || '';
      const lbl = btn.getAttribute('data-mp-title') || '';
      if (!inputName) return;
      openPicker(inputName, lbl);
      return;
    }

    if (e.target && e.target.getAttribute && e.target.getAttribute('data-mp-close') === '1'){
      closePicker();
      return;
    }
  }, true);


  window.addEventListener('message', function(ev){
    const data = ev && ev.data ? ev.data : null;
    if (!data || data.type !== 'media-picked') return;

    const id = parseInt(data.id, 10) || 0;
    if (!currentInputName || id <= 0) return;

    const input = document.querySelector('input[name="' + CSS.escape(currentInputName) + '"]');
    if (!input) return;

    input.value = String(id);

    // Preview aktualisieren (nimmt das nächste img innerhalb der Picker-Komponente)
    const root = input.closest('.ss-asset') || input.closest('.mp');
    if (root){
      const img = root.querySelector('img');
      if (img) img.src = '/media/thumb?id=' + id;
      else {
        const prev = root.querySelector('.mp__preview');
        if (prev){
          const ni = document.createElement('img');
          ni.alt = '';
          ni.src = '/media/thumb?id=' + id;
          prev.innerHTML = '';
          prev.appendChild(ni);
        }
      }
    }

    closePicker();
  });
})();
</script>

    </body>
    </html>
    <?php
}
