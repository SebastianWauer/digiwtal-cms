<?php
$title = '2FA';
$layoutMode = 'auth';
ob_start();
?>
<h1 class="auth-title">Zwei-Faktor-Login</h1>
<p class="auth-copy">Bestätige den Login mit deinem aktuellen 6-stelligen TOTP-Code.</p>

<?php if (isset($error)): ?>
    <div class="alert alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
<?php endif; ?>

<form method="POST" action="/admin/verify-2fa" class="form-stack">
    <?php echo Csrf::field(); ?>
    <div class="field">
        <label for="code">Code</label>
        <input class="input mono" type="text" id="code" name="code" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="off">
    </div>
    <button class="btn btn--primary btn--block" type="submit">Verifizieren</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
