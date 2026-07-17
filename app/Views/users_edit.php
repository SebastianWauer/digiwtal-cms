<?php
declare(strict_types=1);

/** @var array $row */
/** @var array $allRoles */
/** @var int[] $selectedRoleIds */
/** @var ?array $flash */
/** @var bool $canDelete */
/** @var bool $canEditRoles */
/** @var bool $isSelf */
/** @var bool $canResetOtherPw */

echo flash_render($flash ?? null);

$id       = (int)($row['id'] ?? 0);
$username = (string)($row['username'] ?? '');
$name     = (string)($row['name'] ?? '');
$email    = (string)($row['email'] ?? '');
$enabled  = !empty($row['enabled']);
$deleted  = !empty($row['is_deleted']);

$canDelete       = isset($canDelete) ? (bool)$canDelete : false;
$canEditRoles    = isset($canEditRoles) ? (bool)$canEditRoles : false;
$isSelf          = isset($isSelf) ? (bool)$isSelf : false;
$canResetOtherPw = isset($canResetOtherPw) ? (bool)$canResetOtherPw : false;

// Passwortfeld:
$showPasswordField = ($id <= 0) || $isSelf || $canResetOtherPw;

$statusBadgeClass = $enabled ? 'pages-badge--live' : 'pages-badge--draft';
$statusLabel      = $enabled ? 'Aktiv' : 'Gesperrt';

$csrfField = function_exists('admin_csrf_field') ? admin_csrf_field() : '';
?>

<form method="post" action="/users/save" class="pages-edit-form">
  <?= $csrfField ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="pages-edit-layout">

    <section class="pages-edit-left">

      <div class="pages-edit-card">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Benutzer</div>
            <div class="pages-edit-card-sub">Profil & Login-Daten.</div>
          </div>

          <div>
            <span class="pages-badge <?= $statusBadgeClass ?>"><?= h($statusLabel) ?></span>
          </div>
        </div>

        <div class="pages-edit-fields">

          <div class="pages-edit-field">
            <div class="pages-edit-field-label">Name</div>
            <input class="pages-edit-input" type="text" name="name" value="<?= h($name) ?>" placeholder="z.B. Max Mustermann">
          </div>

          <div class="pages-edit-grid2">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Benutzername</div>
              <input class="pages-edit-input" type="text" name="username" value="<?= h($username) ?>" required>
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">E-Mail</div>
              <input class="pages-edit-input" type="email" name="email" value="<?= h($email) ?>" placeholder="name@domain.de">
            </div>
          </div>

          <?php if ($showPasswordField): ?>
            <div class="pages-edit-field">
              <div class="pages-edit-field-label"><?= $id > 0 ? 'Neues Passwort' : 'Passwort' ?></div>
              <input class="pages-edit-input" type="password" name="new_password" autocomplete="new-password">
              <div class="pages-edit-field-hint">
                <?= $id > 0 ? 'Leer lassen, um das Passwort nicht zu ändern.' : 'Mindestens 8 Zeichen.' ?>
              </div>
            </div>
          <?php else: ?>
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Passwort</div>
              <div class="pages-hint">Du darfst das Passwort anderer Benutzer nicht ändern.</div>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <div class="pages-edit-actionsbar">
        <button type="submit" class="btn">Speichern</button>
        <a class="btn btn--ghost" href="/users">Zurück</a>

        <div class="pages-edit-actionsbar-spacer"></div>

        <?php if ($id > 0 && !$deleted): ?>
          <?php if ($canDelete): ?>
            <button
              type="submit"
              class="btn btn--ghost btn--danger btn--sm"
              formmethod="post"
              formaction="/users/delete"
              formnovalidate
            >Löschen</button>
          <?php else: ?>
            <span class="pages-hint">Dieser Benutzer kann nicht gelöscht werden.</span>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($id > 0 && $deleted): ?>
          <?php if (function_exists('admin_can') && admin_can('users.delete')): ?>
            <button
              type="submit"
              class="btn btn--ghost btn--warn btn--sm"
              formmethod="post"
              formaction="/users/restore"
              formnovalidate
            >Wiederherstellen</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    </section>

    <aside class="pages-edit-right">

      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Status</div>
        <div class="pages-edit-card-sub">Steuert, ob sich der Benutzer anmelden darf.</div>

        <div class="pages-edit-fields">
          <label class="pages-edit-check">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
            <span>Benutzer ist aktiv</span>
          </label>
          <div class="pages-edit-field-hint">Gesperrt = keine Anmeldung möglich.</div>
        </div>
      </div>

      <?php if ($canEditRoles): ?>
        <div class="pages-edit-card">
          <div class="pages-edit-card-title">Rollen</div>
          <div class="pages-edit-card-sub">Berechtigungen dieses Benutzers.</div>

          <div class="pages-edit-fields">
            <?php foreach ($allRoles as $r): ?>
              <?php
                $rid = (int)($r['id'] ?? 0);
                $rname = (string)($r['name'] ?? '');
                $checked = in_array($rid, $selectedRoleIds, true);
              ?>
              <label class="pages-edit-check">
                <input type="checkbox" name="roles[]" value="<?= (int)$rid ?>" <?= $checked ? 'checked' : '' ?>>
                <span><?= h($rname) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="pages-edit-card">
          <div class="pages-edit-card-title">Rollen</div>
          <div class="pages-edit-card-sub">Berechtigungen dieses Benutzers.</div>

          <div class="pages-edit-fields">
            <?php if ($selectedRoleIds): ?>
              <div class="pages-hint" style="margin-bottom:8px;">Zugeordnete Rollen:</div>
              <?php foreach ($allRoles as $r): ?>
                <?php
                  $rid = (int)($r['id'] ?? 0);
                  $rname = (string)($r['name'] ?? '');
                  $checked = in_array($rid, $selectedRoleIds, true);
                ?>
                <?php if ($checked): ?>
                  <div class="pages-hint">• <?= h($rname) ?></div>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="pages-hint">Keine Rollen zugeordnet.</div>
            <?php endif; ?>

            <div class="pages-hint" style="margin-top:10px;">
              Du darfst die Rollen dieses Benutzers nicht ändern.
            </div>
          </div>
        </div>
      <?php endif; ?>

    </aside>

  </div>
</form>
