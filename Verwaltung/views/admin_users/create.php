<?php
$title = 'Admin einladen';
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Admin-Benutzer einladen</h1>
                <p class="page-subtitle">Neuen Zugang direkt anlegen. Kein Mailversand, keine Wartezeiten.</p>
            </div>
        </header>

        <?php if (!empty($_SESSION['flash_errors'])): ?>
            <div class="alert alert--error">
                <?php foreach ($_SESSION['flash_errors'] as $e): ?>
                    <div><?php echo htmlspecialchars((string)$e, ENT_QUOTES); ?></div>
                <?php endforeach; unset($_SESSION['flash_errors']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/admin-users" class="form-stack">
            <?php echo Csrf::field(); ?>
            <div class="field">
                <label for="email">E-Mail *</label>
                <input class="input" type="email" id="email" name="email" required autofocus>
            </div>
            <div class="field">
                <label for="password">Passwort *</label>
                <div class="field__hint">Mindestens 12 Zeichen.</div>
                <input class="input" type="password" id="password" name="password" required minlength="12">
            </div>
            <div class="field">
                <label for="role">Rolle</label>
                <select class="select" id="role" name="role">
                    <option value="operator">Operator – kann alles außer Admin-Verwaltung</option>
                    <option value="superadmin">Superadmin – voller Zugriff</option>
                </select>
            </div>
            <div class="submit-row">
                <button class="btn btn--primary" type="submit">Admin anlegen</button>
                <a class="btn btn--secondary" href="/admin/admin-users">Abbrechen</a>
            </div>
        </form>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
