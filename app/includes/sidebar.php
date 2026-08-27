<?php
declare(strict_types=1);

require_once __DIR__ . '/components.php';
require_once __DIR__ . '/../Core/Paths.php';

use App\Core\Paths;

function sidebar_render(array $params): void
{
    $active = (string)($params['active'] ?? 'dashboard');
    $user   = is_array($params['user'] ?? null) ? $params['user'] : [];
    $next = $_SERVER['REQUEST_URI'] ?? '/';  // Aktuelle Seite als fallback verwenden
    $next = Paths::safeInternal($next, Paths::DASHBOARD);  // Verhindert unsichere URLs
    $theme = $params['theme'] ?? 'dark';

    $username = (string)($user['username'] ?? '');
    $role     = admin_role_label($user);
    $version  = admin_version();

    $isActive = static fn(string $key): string => $active === $key ? 'is-active' : '';

    $can = static function (string $perm): bool {
        return function_exists('admin_can') && admin_can($perm);
    };

    // Admin-only (nicht delegierbar)
    $isAdmin = false;
    try {
        $uid = (int)($user['id'] ?? 0);
        if ($uid > 0 && function_exists('admin_pdo')) {
            $pdo = admin_pdo();
            $st = $pdo->prepare("
                SELECT 1
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = :uid
                  AND r.is_deleted = 0
                  AND r.`key` = 'admin'
                LIMIT 1
            ");
            $st->execute([':uid' => $uid]);
            $isAdmin = (bool)$st->fetchColumn();
        }
    } catch (\Throwable) {
        $isAdmin = false;
    }

    // System Health NUR für den echten SystemUser "admin"
    $isSystemUser = $isAdmin && ($username === 'admin');

    // Systemblock sichtbar?
    $showSystemBlock = ($isSystemUser || $isAdmin);

    ?>
    <aside class="sidebar">
      <button type="button" class="sidebar-toggle" aria-label="Sidebar ein-/ausklappen" title="Sidebar ein-/ausklappen">
        <span class="sidebar-toggle__chev" aria-hidden="true">‹</span>
      </button>
      <div class="brand">
        <div class="brand__logo">
          <?php
            $logoLight = site_cms_logo_url('light');
            $logoDark  = site_cms_logo_url('dark');
            $logoNow   = ($theme === 'light') ? $logoLight : $logoDark;
          ?>

          <?php if ($logoNow): ?>
            <img
              src="<?= h($logoNow) ?>"
              data-logo-light="<?= h($logoLight ?? $logoNow) ?>"
              data-logo-dark="<?= h($logoDark ?? $logoNow) ?>"
              alt="CMS Logo"
              class="brand__logo-img"
              id="cmsBrandLogo"
            >
          <?php else: ?>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 2c3.4 0 6.2 2.8 6.2 6.2S15.4 14.4 12 14.4 5.8 11.6 5.8 8.2 8.6 2 12 2Z" stroke="currentColor" stroke-width="1.6"/>
              <path d="M9 21c.8-3.2 1.8-4.8 3-4.8s2.2 1.6 3 4.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          <?php endif; ?>
        </div>
      </div>

      <!-- Hauptnavigation -->
      <nav class="nav">
        <?php if ($can('dashboard.view')): ?>
          <a class="nav__item <?= $isActive('dashboard') ?>" href="<?= h(cms_base_path() . Paths::DASHBOARD) ?>">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.8"/>
                <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.8"/>
                <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.8"/>
                <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.8"/>
              </svg>
            </span><span>Dashboard</span>
          </a>
        <?php endif; ?>

        <?php if ($can('pages.view')): ?>
          <a class="nav__item <?= $isActive('pages') ?>" href="<?= cms_base_path() ?>/pages">
            <span class="nav__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 3h9l3 3v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                <path d="M9 11h6M9 15h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>
              </svg>
            </span><span>Seiten</span>
          </a>
        <?php endif; ?>

        <?php if ($can('media.view')): ?>
          <a class="nav__item <?= $isActive('media') ?>" href="<?= cms_base_path() ?>/media">
            <span class="nav__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M4 7a2 2 0 0 1 2-2h8l4 4v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path>
                  <path d="M8.5 12a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" fill="none" stroke="currentColor" stroke-width="1.8"></path>
                  <path d="M6.5 18l4-3 2.5 2 3.5-3 2 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
            </span><span>Medien</span>
          </a>
        <?php endif; ?>

        <?php if ($can('events.view')): ?>
          <a class="nav__item <?= $isActive('events') ?>" href="<?= cms_base_path() ?>/events">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
                <path d="M3 10h18" stroke="currentColor" stroke-width="1.8"/>
                <path d="M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span><span>Events</span>
          </a>
        <?php endif; ?>

        <?php if ($can('news.view')): ?>
          <a class="nav__item <?= $isActive('news') ?>" href="<?= cms_base_path() ?>/news">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span><span>News</span>
          </a>
        <?php endif; ?>

        <?php if ($can('forms.view')): ?>
          <a class="nav__item <?= $isActive('forms') ?>" href="<?= cms_base_path() ?>/forms/submissions">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/>
                <path d="M7 8h10M7 12h10M7 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span><span>Formulare</span>
          </a>
        <?php endif; ?>

        <?php if ($can('users.view')): ?>
          <a class="nav__item <?= $isActive('users') ?>" href="<?= cms_base_path() ?>/users">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/>
                <path d="M5 20c1.6-3.6 4.2-5.2 7-5.2s5.4 1.6 7 5.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span><span>Benutzer</span>
          </a>
        <?php endif; ?>

        <?php if ($can('roles.view')): ?>
          <a class="nav__item <?= $isActive('roles') ?>" href="<?= cms_base_path() ?>/roles">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M12 3l7 4v5c0 5-3.5 8.5-7 9-3.5-.5-7-4-7-9V7l7-4Z"
                      stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M9 12h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span><span>Rollen</span>
          </a>
        <?php endif; ?>

        <?php if ($can('settings.view')): ?>
          <a class="nav__item <?= $isActive('settings') ?>" href="<?= cms_base_path() ?>/settings">
            <span class="nav__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Z"
                      stroke="currentColor" stroke-width="1.8"/>
                <path d="M19 12a7 7 0 0 0-.1-1.2l2-1.2-2-3.4-2.2.9a7.9 7.9 0 0 0-2.1-1.2L14.2 3h-4l-.4 2.7c-.75.3-1.45.7-2.1 1.2l-2.2-.9-2 3.4 2 1.2A7 7 0 0 0 5 12c0 .4.03.8.1 1.2l-2 1.2 2 3.4 2.2-.9c.65.5 1.35.9 2.1 1.2l.4 2.7h4l.4-2.7c.75-.3 1.45-.7 2.1-1.2l2.2.9 2-3.4-2-1.2c.07-.4.1-.8.1-1.2Z"
                      stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
              </svg>
            </span><span>Einstellungen</span>
          </a>
        <?php endif; ?>

        <a class="nav__item <?= $isActive('help') ?>" href="<?= cms_base_path() ?>/hilfe">
          <span class="nav__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
              <path d="M9.6 9.3a2.5 2.5 0 1 1 3.3 2.4c-.6.2-.9.7-.9 1.3v.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M12 16.8h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
          </span><span>Hilfe</span>
        </a>

        <a class="nav__item <?= $isActive('changelog') ?>" href="<?= cms_base_path() ?>/changelog">
          <span class="nav__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.8"/>
              <path d="M7 8h10M7 12h10M7 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span><span>Changelog</span>
        </a>
      </nav>

      <div class="sidebar__spacer"></div>

      <?php if ($showSystemBlock): ?>
        <nav class="nav nav--system">
          <?php if ($isSystemUser): ?>
            <a class="nav__item <?= $isActive('health') ?>" href="<?= cms_base_path() ?>/system/health">
              <span class="nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M20.8 5.9a4.9 4.9 0 0 0-6.9 0L12 7.8l-1.9-1.9a4.9 4.9 0 0 0-6.9 6.9l1.9 1.9L12 21l6.9-6.3 1.9-1.9a4.9 4.9 0 0 0 0-6.9Z"
                        stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                  <path d="M6.8 12h2.4l1.2-2.4 2.4 5.2 1.4-2.8h3.6"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span><span>System Health</span>
            </a>
            <a class="nav__item <?= $isActive('backup') ?>" href="<?= cms_base_path() ?>/backup">
              <span class="nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M12 3v10M8 9l4 4 4-4"
                        stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M4 17v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </span><span>Backup</span>
            </a>
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <a class="nav__item <?= $isActive('migrate') ?>" href="<?= cms_base_path() ?>/migrate">
              <span class="nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M12 3v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M8 7l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M5 21h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </span><span>Migrationen</span>
            </a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

      <div class="sidebar-footer">
        <div class="userbox userbox--row">
          <div class="userbox__info">
            <div class="userbox__name"><?= h($username) ?></div>
            <div class="userbox__role"><?= h($role) ?></div>
          </div>

          <form method="post" action="<?= h(cms_base_path() . Paths::LOGOUT) ?>" class="form-reset userbox__logout">
            <?= function_exists('admin_csrf_field') ? admin_csrf_field() : '' ?>
            <button type="submit" class="sidebtn sidebtn--sm">Logout</button>
          </form>
        </div>

        <div class="sidebar-actions">
          <!-- Theme Slider: technisch zwei Posts, optisch ein Slider -->
          <div class="theme-slider" role="group" aria-label="Theme">
            <span class="theme-slider__thumb" aria-hidden="true"></span>

            <form method="post" action="<?= h(cms_base_path() . Paths::THEME) ?>" class="form-reset theme-slider__form">
              <?= function_exists('admin_csrf_field') ? admin_csrf_field() : '' ?>
              <input type="hidden" name="next" value="<?= h($next) ?>">
              <input type="hidden" name="theme" value="dark">
              <button type="submit" class="theme-slider__btn" title="Dark Mode" aria-label="Dark Mode">🌙</button>
            </form>

            <form method="post" action="<?= h(cms_base_path() . Paths::THEME) ?>" class="form-reset theme-slider__form">
              <?= function_exists('admin_csrf_field') ? admin_csrf_field() : '' ?>
              <input type="hidden" name="next" value="<?= h($next) ?>">
              <input type="hidden" name="theme" value="light">
              <button type="submit" class="theme-slider__btn" title="Light Mode" aria-label="Light Mode">☀️</button>
            </form>
          </div>

          <div class="sidebar-meta">
            <div>© <?= date('Y') ?> DIGIWTAL – CMS</div>
            <div>Version <?= h($version) ?></div>
          </div>
        </div>
      </div>
    </aside>
    <?php
}
