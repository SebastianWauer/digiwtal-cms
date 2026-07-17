<?php
declare(strict_types=1);

/** @var array $row */
/** @var ?array $flash */
/** @var array $groups */
/** @var array $selectedSet */
/** @var bool $canEditPerms */
/** @var array $implications */

echo flash_render($flash ?? null);

$id   = (int)($row['id'] ?? 0);
$key  = (string)($row['key'] ?? '');
$name = (string)($row['name'] ?? '');

$roleKey = $key;
$isAdmin = ($roleKey === 'admin');

$groupLabels = [
  'dashboard' => 'Dashboard',
  'pages'     => 'Seiten',
  'users'     => 'Benutzer',
  'roles'     => 'Rollen',
  'system'    => 'System',
  'general'   => 'Allgemein',
];

// Diese Keys dürfen NICHT in der Rollen-Rechte-Auswahl auftauchen
$hiddenPermKeys = [
  'system.migrate.run',          // niemals auswählbar (Admin-only / hard-coded)
  'system.migrate',
  'roles.permissions.edit',  // steckt in roles.edit, kein eigener Punkt mehr
  'users.roles.edit.self',   // soll gelöscht/weg sein (safety)
];

// IMPLIES JSON für UI-Pfadlogik
$implications = isset($implications) && is_array($implications) ? $implications : [];
$impJson = json_encode($implications, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($impJson) || $impJson === '') $impJson = '{}';
?>

<form method="post" action="/roles/save" class="pages-edit-form" id="rolesEditForm">
  <?= admin_csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="pages-edit-layout">

    <section class="pages-edit-left">

      <div class="pages-edit-card">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Rolle</div>
            <div class="pages-edit-card-sub">Technischer Key + Anzeigename.</div>
          </div>
        </div>

        <div class="pages-edit-fields">

          <div class="pages-edit-grid2">
            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Key</div>
              <input class="pages-edit-input" type="text" name="key" value="<?= h($key) ?>" required <?= $isAdmin ? 'readonly' : '' ?>>
              <div class="pages-edit-field-hint">Technisch eindeutig (z.B. <code>editor</code>, <code>support</code>).</div>
            </div>

            <div class="pages-edit-field">
              <div class="pages-edit-field-label">Name</div>
              <input class="pages-edit-input" type="text" name="name" value="<?= h($name) ?>" required>
              <div class="pages-edit-field-hint">Anzeige im Admin.</div>
            </div>
          </div>

        </div>
      </div>

      <div class="pages-edit-card pages-edit-card--spaced">
        <div class="pages-edit-card-head">
          <div>
            <div class="pages-edit-card-title">Berechtigungen</div>
            <div class="pages-edit-card-sub">
              <?php if ($isAdmin): ?>
                Admin hat immer alle Rechte (Anzeige-only).
              <?php else: ?>
                <?= $canEditPerms ? 'Häkchen setzen und speichern. Pfade werden automatisch ergänzt.' : 'Nur Anzeige (keine Berechtigung zum Bearbeiten).' ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php foreach ($groups as $gk => $items): ?>
          <?php
            $title = $groupLabels[$gk] ?? $gk;

            // Filter + Sort
            $items = array_values(array_filter($items, function($p) use ($hiddenPermKeys) {
              $pkey = (string)($p['key'] ?? '');
              if ($pkey === '') return false;
              if (in_array($pkey, $hiddenPermKeys, true)) return false;
              return true;
            }));

            if (!$items) continue;

            usort($items, function($a, $b) {
              $ka = (string)($a['key'] ?? '');
              $kb = (string)($b['key'] ?? '');
              return $ka <=> $kb;
            });
          ?>

          <div class="roles-perm-group-title">
            <?= h($title) ?>
          </div>

          <div class="roles-perm-group">
            <?php foreach ($items as $p): ?>
              <?php
                $pid    = (int)($p['id'] ?? 0);
                $pkey   = (string)($p['key'] ?? '');
                $plabel = (string)($p['label'] ?? '');
                $checked  = isset($selectedSet[$pid]);
                $disabled = (!$canEditPerms) || $isAdmin;
              ?>
              <label class="pages-edit-check roles-perm-item">
                <input
                  type="checkbox"
                  name="perm[]"
                  value="<?= (int)$pid ?>"
                  data-perm-key="<?= h($pkey) ?>"
                  <?= $checked ? 'checked' : '' ?>
                  <?= $disabled ? 'disabled' : '' ?>
                >
                <span class="roles-perm-item__text">
                  <span class="roles-perm-item__label"><?= h($plabel) ?></span>
                  <span class="pages-hint"><code><?= h($pkey) ?></code></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <?php if (!$canEditPerms && !$isAdmin): ?>
          <div class="pages-hint roles-perm-readonly">
            Du darfst die Berechtigungen dieser Rolle nicht ändern.
          </div>
        <?php endif; ?>
      </div>

      <div class="pages-edit-actionsbar">
        <button type="submit" class="btn">Speichern</button>
        <a class="btn btn--ghost" href="/roles">Zurück</a>

        <div class="pages-edit-actionsbar-spacer"></div>

        <?php if ($id > 0 && !$isAdmin): ?>
          <form method="post" action="/roles/delete" class="form-reset">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <button type="submit" class="btn btn--ghost btn--danger btn--sm">Löschen</button>
          </form>
        <?php endif; ?>
      </div>

    </section>

    <aside class="pages-edit-right">
      <div class="pages-edit-card">
        <div class="pages-edit-card-title">Hinweise</div>
        <div class="pages-edit-card-sub">Sicherheitsregeln.</div>
        <div class="pages-edit-fields">
          <div class="pages-hint">
            • Admin-Rolle kann nicht gelöscht werden.<br>
            • Rollen-Löschen ist blockiert, wenn Benutzer zugeordnet sind.<br>
            • System-/Admin-Funktionen sind nicht als Rechte auswählbar.<br>
            • Pfadlogik: “höhere” Rechte aktivieren automatisch die notwendigen Basisrechte.
          </div>
        </div>
      </div>
    </aside>

  </div>
</form>

<?php if ($canEditPerms && !$isAdmin): ?>
<script>
(() => {
  const IMPLIES = <?= $impJson ?>;

  const boxes = Array.from(document.querySelectorAll('input[type="checkbox"][name="perm[]"][data-perm-key]'));
  const byKey = new Map();
  boxes.forEach(b => byKey.set(b.getAttribute('data-perm-key'), b));

  // reverse: parent -> children
  const childrenOf = {};
  Object.keys(IMPLIES).forEach(child => {
    (IMPLIES[child] || []).forEach(parent => {
      childrenOf[parent] ??= [];
      childrenOf[parent].push(child);
    });
  });

  function checkKey(k) {
    const b = byKey.get(k);
    if (b && !b.checked) b.checked = true;
  }

  function uncheckKey(k) {
    const b = byKey.get(k);
    if (b && b.checked) b.checked = false;
  }

  function expandParents(startKey) {
    const seen = new Set();
    const stack = [startKey];
    while (stack.length) {
      const k = stack.pop();
      if (seen.has(k)) continue;
      seen.add(k);

      const parents = IMPLIES[k] || [];
      parents.forEach(p => {
        checkKey(p);
        stack.push(p);
      });
    }
  }

  function removeChildren(startKey) {
    const seen = new Set();
    const stack = [startKey];
    while (stack.length) {
      const k = stack.pop();
      const children = childrenOf[k] || [];
      children.forEach(ch => {
        if (seen.has(ch)) return;
        seen.add(ch);
        uncheckKey(ch);
        stack.push(ch);
      });
    }
  }

  // Normalize on load: für alle checked -> parents an
  boxes.forEach(b => {
    if (b.checked) expandParents(b.getAttribute('data-perm-key'));
  });

  boxes.forEach(b => {
    b.addEventListener('change', () => {
      const k = b.getAttribute('data-perm-key');
      if (!k) return;

      if (b.checked) {
        expandParents(k);      // child => parents
      } else {
        removeChildren(k);     // parent off => children off
      }
    });
  });
})();
</script>
<?php endif; ?>
