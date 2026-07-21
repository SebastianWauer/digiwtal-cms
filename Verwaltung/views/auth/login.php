<?php
$title = 'Login';
$layoutMode = 'auth';
ob_start();
?>
<h1 class="auth-title">Verwaltung - Login</h1>

<?php if (isset($error)): ?>
    <div class="alert alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
<?php endif; ?>

<form method="POST" action="/admin/login" class="form-stack">
    <?php echo Csrf::field(); ?>
    <div class="field">
        <label for="email">E-Mail</label>
        <input class="input" type="email" id="email" name="email" required autofocus>
    </div>
    <div class="field">
        <label for="password">Passwort</label>
        <input class="input" type="password" id="password" name="password" required>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Login</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
