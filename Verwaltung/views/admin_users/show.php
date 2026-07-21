<?php
$title = 'Admin-Details';
$totpEnabled = !empty($user['totp_secret']);
$hasPendingSetup = !empty($pendingSecret);
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Admin-Benutzer</h1>
                <p class="page-subtitle"><?php echo htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES); ?></p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/admin-users">Zurück</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars((string)$success, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars((string)$error, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="info-grid">
            <div class="info-card">
                <div class="info-card__label">Rolle</div>
                <div class="info-card__value"><?php echo htmlspecialchars((string)($user['role'] ?? 'operator'), ENT_QUOTES); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__label">2FA</div>
                <div class="info-card__value">
                    <span class="status-pill status-pill--<?php echo $totpEnabled ? 'healthy' : 'down'; ?>">
                        <?php echo $totpEnabled ? 'aktiv' : 'nicht eingerichtet'; ?>
                    </span>
                </div>
            </div>
            <div class="info-card">
                <div class="info-card__label">Erstellt</div>
                <div class="info-card__value"><?php echo htmlspecialchars((string)($user['created_at'] ?? '—'), ENT_QUOTES); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__label">Letzter Login</div>
                <div class="info-card__value"><?php echo htmlspecialchars((string)($user['last_login_at'] ?? '—'), ENT_QUOTES); ?></div>
            </div>
        </div>
    </section>

    <section class="surface">
        <header class="page-header page-header--section">
            <div class="page-header__main">
                <h2 class="section-title">Passwort zurücksetzen</h2>
                <p class="page-subtitle">Neues Passwort setzen und direkt für den Login aktivieren.</p>
            </div>
        </header>

        <form method="POST" action="/admin/admin-users/<?php echo (int)($user['id'] ?? 0); ?>/password" class="form-stack">
            <?php echo Csrf::field(); ?>
            <div class="field">
                <label for="password">Neues Passwort</label>
                <div class="field__hint">Mindestens 12 Zeichen.</div>
                <input class="input" id="password" name="password" type="password" minlength="12" required>
            </div>
            <div class="field">
                <label for="password_confirm">Neues Passwort bestätigen</label>
                <input class="input" id="password_confirm" name="password_confirm" type="password" minlength="12" required>
            </div>
            <div class="submit-row">
                <button class="btn btn--primary" type="submit">Passwort speichern</button>
            </div>
        </form>
    </section>

    <section class="surface">
        <header class="page-header page-header--section">
            <div class="page-header__main">
                <h2 class="section-title">TOTP-Setup</h2>
                <p class="page-subtitle">2FA für neue Admins vorbereiten und verifizieren.</p>
            </div>
        </header>

        <?php if (!$hasPendingSetup): ?>
            <div class="hint-card hint-card--info">
                <div><?php echo $totpEnabled ? 'TOTP ist bereits aktiv. Bei Bedarf kannst du ein neues Setup vorbereiten.' : 'Noch kein TOTP eingerichtet. Starte hier die Einrichtung.'; ?></div>
            </div>
            <form method="POST" action="/admin/admin-users/<?php echo (int)($user['id'] ?? 0); ?>/totp/start" class="form-stack">
                <?php echo Csrf::field(); ?>
                <div class="submit-row">
                    <button class="btn btn--primary" type="submit"><?php echo $totpEnabled ? 'TOTP neu einrichten' : 'TOTP einrichten'; ?></button>
                </div>
            </form>
        <?php else: ?>
            <div class="hint-card hint-card--warning">
                <div><strong>Setup ausstehend:</strong> Secret im Authenticator eintragen und den erzeugten 6-stelligen Code bestätigen.</div>
            </div>

            <div class="admin-user-setup">
                <div class="admin-user-setup__preview">
                    <img class="admin-user-setup__image" src="/admin/admin-users/<?php echo (int)($user['id'] ?? 0); ?>/totp/qr" alt="TOTP Setup Grafik">
                </div>
                <div class="admin-user-setup__meta">
                    <div class="field">
                        <label for="totp-secret">Secret</label>
                        <input class="input" id="totp-secret" type="text" value="<?php echo htmlspecialchars((string)$setupSecret, ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="field">
                        <label for="totp-uri">otpauth-URL</label>
                        <textarea class="textarea" id="totp-uri" rows="4" readonly><?php echo htmlspecialchars((string)$otpauthUri, ENT_QUOTES); ?></textarea>
                    </div>
                    <form method="POST" action="/admin/admin-users/<?php echo (int)($user['id'] ?? 0); ?>/totp/verify" class="form-stack">
                        <?php echo Csrf::field(); ?>
                        <div class="field">
                            <label for="code">TOTP-Code bestätigen</label>
                            <input class="input" id="code" name="code" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                        </div>
                        <div class="submit-row">
                            <button class="btn btn--success" type="submit">TOTP aktivieren</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
