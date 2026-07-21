<?php
$title = 'Serverzugang';
$storedServerPath = (string)($access['server_path'] ?? '');
$storedHtmlPath = (string)($access['html_path'] ?? '');
$derivedBasePath = '/';
if ($storedServerPath !== '' && str_ends_with($storedServerPath, '/CMS')) {
    $derivedBasePath = substr($storedServerPath, 0, -4);
} elseif ($storedHtmlPath !== '' && str_ends_with($storedHtmlPath, '/Frontend')) {
    $derivedBasePath = substr($storedHtmlPath, 0, -9);
}
if ($derivedBasePath === '') {
    $derivedBasePath = '/';
}
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Serverzugang</h1>
                <p class="page-subtitle">Kunde: <strong><?php echo htmlspecialchars((string)($customer['name'] ?? ''), ENT_QUOTES); ?></strong></p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/customers">Kunden</a>
            </div>
        </header>

        <?php if (isset($success)): ?>
            <div class="alert alert--success"><?php echo htmlspecialchars($success, ENT_QUOTES); ?></div>
        <?php endif; ?>
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err, ENT_QUOTES); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="hint-card hint-card--info">
            Tokens und Passwörter werden verschlüsselt gespeichert. Leere Passwortfelder lassen bestehende Werte unverändert.
        </div>

        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/access" class="form-stack">
            <?php echo Csrf::field(); ?>

            <div class="field">
                <label for="label">Label</label>
                <input class="input" type="text" id="label" name="label" maxlength="100" value="<?php echo htmlspecialchars((string)($old['label'] ?? $access['label'] ?? ''), ENT_QUOTES); ?>">
            </div>

            <div class="field">
                <label for="host">SFTP-Host *</label>
                <input class="input" type="text" id="host" name="host" placeholder="z.B. access-123456.webspace-host.com" value="<?php echo htmlspecialchars((string)($old['host'] ?? $access['host'] ?? ''), ENT_QUOTES); ?>" required>
                <div class="field__hint">Nur für Datei-Upload per SFTP/FTP. Nicht die öffentliche CMS- oder Frontend-Domain eintragen.</div>
            </div>

            <div class="form-grid form-grid--label-inline">
                <div class="field">
                    <label for="port">Port</label>
                    <input class="input" type="number" id="port" name="port" min="1" max="65535" value="<?php echo (int)($old['port'] ?? $access['port'] ?? 21); ?>">
                </div>
                <div class="field">
                    <label for="protocol">Protokoll</label>
                    <select class="select" id="protocol" name="protocol">
                        <?php
                        $currentProtocol = (string)($old['protocol'] ?? $access['protocol'] ?? 'ftp');
                        foreach (['ftp', 'sftp', 'ssh'] as $proto): ?>
                            <option value="<?php echo $proto; ?>" <?php echo $proto === $currentProtocol ? 'selected' : ''; ?>><?php echo strtoupper($proto); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field__hint">FTP läuft direkt in der Web-App. Für SFTP/SSH nutzt der aktuelle Deploy-Flow den lokalen Agenten.</div>
                </div>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input class="input" type="text" id="username" name="username" value="<?php echo htmlspecialchars((string)($old['username'] ?? $access['username'] ?? ''), ENT_QUOTES); ?>">
            </div>

            <div class="field">
                <label for="base_path">Basis-Pfad</label>
                <input class="input" type="text" id="base_path" name="base_path" value="<?php echo htmlspecialchars((string)($old['basePath'] ?? $derivedBasePath), ENT_QUOTES); ?>">
                <div class="field__hint">
                    Daraus werden automatisch
                    <code><?php echo htmlspecialchars((string)(($old['basePath'] ?? $derivedBasePath) === '/' ? '/CMS' : rtrim((string)($old['basePath'] ?? $derivedBasePath), '/') . '/CMS'), ENT_QUOTES); ?></code>
                    und
                    <code><?php echo htmlspecialchars((string)(($old['basePath'] ?? $derivedBasePath) === '/' ? '/Frontend' : rtrim((string)($old['basePath'] ?? $derivedBasePath), '/') . '/Frontend'), ENT_QUOTES); ?></code>.
                </div>
            </div>

            <hr class="divider">

            <div class="field">
                <label for="health_token">Health Token</label>
                <input class="input" type="password" id="health_token" name="health_token" placeholder="Leer lassen = nicht ändern" autocomplete="off">
            </div>

            <div class="field">
                <label for="health_cms_url">Health CMS URL</label>
                <input class="input" type="url" id="health_cms_url" name="health_cms_url" placeholder="https://cms.example.com" value="<?php echo htmlspecialchars((string)($old['healthCmsUrl'] ?? $access['health_cms_url'] ?? ''), ENT_QUOTES); ?>">
                <div class="field__hint">Öffentliche CMS-URL. Der Health-Check ruft dort <code>/api/health?token=...</code> auf.</div>
            </div>

            <div class="field">
                <label for="health_frontend_url">Health Frontend URL</label>
                <input class="input" type="url" id="health_frontend_url" name="health_frontend_url" placeholder="https://www.example.com" value="<?php echo htmlspecialchars((string)($old['healthFrontendUrl'] ?? $access['health_frontend_url'] ?? ''), ENT_QUOTES); ?>">
                <div class="field__hint">Öffentliche Frontend-URL. Wird zusätzlich per HTTP geprüft.</div>
            </div>

            <div class="field">
                <label for="deploy_token">Deploy Token</label>
                <input class="input" type="password" id="deploy_token" name="deploy_token" placeholder="Leer lassen = nicht ändern" autocomplete="off">
            </div>

            <div class="field">
                <label for="password">Server-Passwort</label>
                <input class="input" type="password" id="password" name="password" placeholder="Leer lassen = nicht ändern" autocomplete="off">
            </div>

            <hr class="divider">

            <div>
                <h2 class="section-title">CMS-Provisioning</h2>
                <p class="section-copy">Diese Daten werden für den Erstdeploy verwendet: <code>.env</code> schreiben und das CMS direkt installierbar machen.</p>
            </div>

            <div class="form-grid form-grid--label-inline">
                <div class="field">
                    <label for="db_host">DB-Host</label>
                    <input class="input" type="text" id="db_host" name="db_host" placeholder="z.B. localhost" value="<?php echo htmlspecialchars((string)($old['dbHost'] ?? $access['db_host'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="field">
                    <label for="db_port">DB-Port</label>
                    <input class="input" type="number" id="db_port" name="db_port" min="1" max="65535" value="<?php echo (int)($old['dbPort'] ?? $access['db_port'] ?? 3306); ?>">
                </div>
            </div>

            <div class="form-grid form-grid--2">
                <div class="field">
                    <label for="db_name">DB-Name</label>
                    <input class="input" type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars((string)($old['dbName'] ?? $access['db_name'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="field">
                    <label for="db_user">DB-Benutzer</label>
                    <input class="input" type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars((string)($old['dbUser'] ?? $access['db_user'] ?? ''), ENT_QUOTES); ?>">
                </div>
            </div>

            <div class="field">
                <label for="db_password">DB-Passwort</label>
                <input class="input" type="password" id="db_password" name="db_password" placeholder="Leer lassen = nicht ändern" autocomplete="off">
            </div>

            <div class="field">
                <label for="cms_admin_email">Initiale CMS-Admin-E-Mail</label>
                <input class="input" type="email" id="cms_admin_email" name="cms_admin_email" value="<?php echo htmlspecialchars((string)($old['cmsAdminEmail'] ?? $access['cms_admin_email'] ?? ''), ENT_QUOTES); ?>">
            </div>

            <div class="field">
                <label for="cms_admin_password">Initiales CMS-Admin-Passwort</label>
                <input class="input" type="password" id="cms_admin_password" name="cms_admin_password" placeholder="Leer lassen = nicht ändern" autocomplete="off">
            </div>

            <div class="field">
                <label for="site_name">Site-Name</label>
                <input class="input" type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars((string)($old['siteName'] ?? $access['site_name'] ?? ($customer['name'] ?? '')), ENT_QUOTES); ?>">
            </div>

            <div class="field">
                <label for="canonical_base">Canonical-Base-URL</label>
                <input class="input" type="url" id="canonical_base" name="canonical_base" placeholder="https://example.com" value="<?php echo htmlspecialchars((string)($old['canonicalBase'] ?? $access['canonical_base'] ?? ''), ENT_QUOTES); ?>">
            </div>

            <div class="submit-row">
                <button class="btn btn--primary" type="submit">Speichern</button>
            </div>
        </form>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
