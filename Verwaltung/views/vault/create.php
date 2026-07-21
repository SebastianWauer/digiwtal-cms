<?php
$title = 'Geheimnis erstellen';
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Neue Zugangsdaten</h1>
                <p class="page-subtitle">Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong></p>
            </div>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/customers/<?php echo (int)($customerId ?? 0); ?>/vault" class="form-stack">
            <?php echo Csrf::field(); ?>
            <div class="field">
                <label for="label">Label</label>
                <input class="input" type="text" id="label" name="label" maxlength="100" value="<?php echo htmlspecialchars((string)($old['label'] ?? ''), ENT_QUOTES); ?>" placeholder='z.B. "IONOS Webspace"'>
            </div>
            <div class="field">
                <label for="host">Host</label>
                <input class="input" type="text" id="host" name="host" maxlength="255" value="<?php echo htmlspecialchars((string)($old['host'] ?? ''), ENT_QUOTES); ?>" placeholder="z.B. ftp.example.com">
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input class="input" type="text" id="username" name="username" maxlength="255" value="<?php echo htmlspecialchars((string)($old['username'] ?? ''), ENT_QUOTES); ?>">
            </div>
            <div class="field">
                <label for="secret">Secret / Passwort *</label>
                <input class="input" type="password" id="secret" name="secret" required autofocus>
            </div>
            <div class="submit-row">
                <button class="btn btn--primary" type="submit">Speichern</button>
                <a class="btn btn--secondary" href="/admin/customers/<?php echo (int)($customerId ?? 0); ?>/vault">Zurück</a>
            </div>
        </form>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
