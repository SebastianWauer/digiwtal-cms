<?php
$title = 'Deployments';
$cmsTarget = isset($access['server_path']) ? (string)$access['server_path'] : '/CMS';
$frontendTarget = isset($access['html_path']) ? (string)$access['html_path'] : '/Frontend';
$baseTarget = '/';
if (str_ends_with($cmsTarget, '/CMS')) {
    $baseTarget = substr($cmsTarget, 0, -4);
} elseif (str_ends_with($frontendTarget, '/Frontend')) {
    $baseTarget = substr($frontendTarget, 0, -9);
}
if ($baseTarget === '') {
    $baseTarget = '/';
}
$hasHost = trim((string)($access['host'] ?? '')) !== '';
$hasUsername = trim((string)($access['username'] ?? '')) !== '';
$hasServerPath = trim((string)($access['server_path'] ?? '')) !== '';
$hasHtmlPath = trim((string)($access['html_path'] ?? '')) !== '';
$hasHealthCmsUrl = trim((string)($access['health_cms_url'] ?? '')) !== '';
$hasCanonicalBase = trim((string)($access['canonical_base'] ?? '')) !== '';
$hasFrontendApiBase = $hasHealthCmsUrl || $hasCanonicalBase;

$cmsDeployRequirements = [];
if (!$hasHost) {
    $cmsDeployRequirements[] = 'SFTP-Host fehlt';
}
if (!$hasUsername) {
    $cmsDeployRequirements[] = 'Username fehlt';
}
if (!$hasServerPath) {
    $cmsDeployRequirements[] = 'CMS-Zielpfad fehlt';
}

$frontendDeployRequirements = [];
if (!$hasHost) {
    $frontendDeployRequirements[] = 'SFTP-Host fehlt';
}
if (!$hasUsername) {
    $frontendDeployRequirements[] = 'Username fehlt';
}
if (!$hasHtmlPath) {
    $frontendDeployRequirements[] = 'Frontend-Zielpfad fehlt';
}
if (!$hasFrontendApiBase) {
    $frontendDeployRequirements[] = 'Health CMS URL oder canonical_base fehlt';
}

$combinedDeployRequirements = [];
if (!$hasHost) {
    $combinedDeployRequirements[] = 'SFTP-Host fehlt';
}
if (!$hasUsername) {
    $combinedDeployRequirements[] = 'Username fehlt';
}
if (!$hasServerPath) {
    $combinedDeployRequirements[] = 'CMS-Zielpfad fehlt';
}
if (!$hasHtmlPath) {
    $combinedDeployRequirements[] = 'Frontend-Zielpfad fehlt';
}
if (!$hasFrontendApiBase) {
    $combinedDeployRequirements[] = 'Health CMS URL oder canonical_base fehlt';
}

$hasRunningDeployment = false;
$hasSuccessfulDeployment = false;
foreach ($deployments as $deployment) {
    if ((string)($deployment['status'] ?? '') === 'running') {
        $hasRunningDeployment = true;
    }
    if ((string)($deployment['status'] ?? '') === 'success') {
        $hasSuccessfulDeployment = true;
    }
}
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Deployments</h1>
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

        <div class="deploy-overview-grid">
            <article class="deploy-overview-card">
                <div class="deploy-overview-card__label">Basis-Pfad</div>
                <div class="deploy-overview-card__value"><code><?php echo htmlspecialchars($baseTarget, ENT_QUOTES); ?></code></div>
            </article>
            <article class="deploy-overview-card">
                <div class="deploy-overview-card__label">Transfer</div>
                <div class="deploy-overview-card__value"><code><?php echo htmlspecialchars((string)($access['protocol'] ?? 'ftp'), ENT_QUOTES); ?></code></div>
            </article>
            <article class="deploy-overview-card">
                <div class="deploy-overview-card__label">CMS-Ziel</div>
                <div class="deploy-overview-card__value"><code><?php echo htmlspecialchars($cmsTarget, ENT_QUOTES); ?></code></div>
            </article>
            <article class="deploy-overview-card">
                <div class="deploy-overview-card__label">Frontend-Ziel</div>
                <div class="deploy-overview-card__value"><code><?php echo htmlspecialchars($frontendTarget, ENT_QUOTES); ?></code></div>
            </article>
        </div>

        <div class="deploy-meta-grid">
            <article class="deploy-meta-card deploy-meta-card--success">
                <div class="deploy-meta-card__head">
                    <h2 class="section-title">Lokaler Agent</h2>
                    <div class="agent-status" id="localAgentStatus">Status: nicht geprüft</div>
                </div>
                <p class="deploy-card__copy">Lokaler HTTPS-Agent auf <code>https://127.0.0.1:8765</code> für One-Click-SFTP.</p>
                <code class="code-block">php Verwaltung/agent/generate_cert.php
php Verwaltung/agent/server.php</code>
                <div class="field__hint">Einmal im selben Browser <a class="link-action link-action--success" href="https://127.0.0.1:8765/health" target="_blank" rel="noreferrer">https://127.0.0.1:8765/health</a> öffnen und das lokale Zertifikat vertrauen.</div>
            </article>
            <article class="deploy-meta-card deploy-meta-card--warning">
                <div class="deploy-meta-card__head">
                    <h2 class="section-title">Sicherheit</h2>
                </div>
                <p class="deploy-card__copy">Für lokale Agent-Deployments wird das Serverpasswort kurz an deinen Browser und dann an den lokalen Agenten übergeben.</p>
                <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/test-connections" class="form-stack">
                    <?php echo Csrf::field(); ?>
                    <div class="submit-row">
                        <button class="btn btn--secondary btn--sm" type="submit">Server- und DB-Verbindung testen</button>
                    </div>
                    <div class="field__hint">Vorab prüfen, ohne Dateien zu deployen.</div>
                </form>
            </article>
        </div>
    </section>

    <section class="surface">
        <header class="page-header page-header--section">
            <div class="page-header__main">
                <h2 class="section-title">Deploy-Aktionen</h2>
                <p class="page-subtitle">Erstinstallation und Updates kompakt an einem Ort.</p>
            </div>
        </header>
        <?php if (!empty($cmsDeployRequirements) || !empty($frontendDeployRequirements) || !empty($combinedDeployRequirements)): ?>
            <div class="hint-card hint-card--warning deploy-warning-card">
                <strong>Vor dem Deploy fehlen noch Pflichtfelder im Serverzugang.</strong>
                <?php if (!empty($cmsDeployRequirements)): ?>
                    <div>CMS: <?php echo htmlspecialchars(implode(', ', $cmsDeployRequirements), ENT_QUOTES); ?></div>
                <?php endif; ?>
                <?php if (!empty($frontendDeployRequirements)): ?>
                    <div>Frontend: <?php echo htmlspecialchars(implode(', ', $frontendDeployRequirements), ENT_QUOTES); ?></div>
                <?php endif; ?>
                <?php if (!empty($combinedDeployRequirements)): ?>
                    <div>CMS + Frontend: <?php echo htmlspecialchars(implode(', ', $combinedDeployRequirements), ENT_QUOTES); ?></div>
                <?php endif; ?>
                <div class="field__hint">Bitte zuerst den Serverzugang vervollständigen, bevor der lokale Agent angefragt wird.</div>
            </div>
        <?php endif; ?>
        <div class="deploy-grid">
            <?php if (!$hasSuccessfulDeployment): ?>
                <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/install" class="deploy-card deploy-card--install">
                    <?php echo Csrf::field(); ?>
                    <div class="deploy-card__eyebrow">Erstinstallation</div>
                    <h2 class="deploy-card__title">CMS deployen + provisionieren</h2>
                    <p class="deploy-card__copy">Für neue Kunden. Nutzt <code>/CMS</code> und richtet das CMS direkt betriebsbereit ein.</p>
                    <div class="submit-row">
                        <button class="btn btn--warning" type="submit" onclick="return confirm('Erstinstallation jetzt starten? Das deployed das CMS und führt die Provisionierung aus.');">Erstinstallation starten</button>
                    </div>
                </form>
            <?php endif; ?>

            <form method="POST" data-local-agent-form="1" data-validation-errors="<?php echo htmlspecialchars(json_encode($cmsDeployRequirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES); ?>" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload" class="deploy-card">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="cms">
                <div class="deploy-card__eyebrow">Update</div>
                <h2 class="deploy-card__title">CMS deployen</h2>
                <p class="deploy-card__copy"><code>/CMS</code> von deinem Rechner per SFTP hochladen.</p>
                <?php if (!empty($cmsDeployRequirements)): ?>
                    <div class="field__hint">Blockiert bis konfiguriert: <?php echo htmlspecialchars(implode(', ', $cmsDeployRequirements), ENT_QUOTES); ?></div>
                <?php endif; ?>
                <div class="submit-row">
                    <button class="btn btn--success" type="submit" <?php echo !empty($cmsDeployRequirements) ? 'disabled' : ''; ?>>CMS deployen</button>
                </div>
            </form>

            <form method="POST" data-local-agent-form="1" data-validation-errors="<?php echo htmlspecialchars(json_encode($frontendDeployRequirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES); ?>" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload" class="deploy-card">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="frontend">
                <div class="deploy-card__eyebrow">Update</div>
                <h2 class="deploy-card__title">Frontend deployen</h2>
                <p class="deploy-card__copy">Lokalen Frontend-Ordner wählen, komprimieren und per Agent übertragen.</p>
                <?php if (!empty($frontendDeployRequirements)): ?>
                    <div class="field__hint">Blockiert bis konfiguriert: <?php echo htmlspecialchars(implode(', ', $frontendDeployRequirements), ENT_QUOTES); ?></div>
                <?php endif; ?>
                <input class="file-input" type="file" name="frontend_files[]" data-frontend-picker="1" webkitdirectory directory multiple required>
                <div class="submit-row">
                    <button class="btn btn--info" type="submit" <?php echo !empty($frontendDeployRequirements) ? 'disabled' : ''; ?>>Frontend deployen</button>
                </div>
            </form>

            <form method="POST" data-local-agent-form="1" data-validation-errors="<?php echo htmlspecialchars(json_encode($combinedDeployRequirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES); ?>" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/agent-payload" class="deploy-card">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="type" value="combined">
                <div class="deploy-card__eyebrow">Update</div>
                <h2 class="deploy-card__title">CMS + Frontend deployen</h2>
                <p class="deploy-card__copy">CMS aus <code>/CMS</code> plus gewähltes Frontend in einem Schritt.</p>
                <?php if (!empty($combinedDeployRequirements)): ?>
                    <div class="field__hint">Blockiert bis konfiguriert: <?php echo htmlspecialchars(implode(', ', $combinedDeployRequirements), ENT_QUOTES); ?></div>
                <?php endif; ?>
                <input class="file-input" type="file" name="frontend_files[]" data-frontend-picker="1" webkitdirectory directory multiple required>
                <div class="submit-row">
                    <button class="btn btn--warning" type="submit" <?php echo !empty($combinedDeployRequirements) ? 'disabled' : ''; ?>>Kombiniert deployen</button>
                </div>
            </form>
        </div>
    </section>

    <section class="surface">
        <div class="submit-row deploy-tools-row">
            <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/rollback" class="table-inline-form" onsubmit="return confirm('Wirklich auf den letzten Stand zurückrollen?');">
                <?php echo Csrf::field(); ?>
                <button class="btn btn--secondary" type="submit">Rollback</button>
            </form>
            <span class="field__hint">Nur für den letzten bekannten Stand. Hängende Deployments lassen sich direkt in der History stoppen.</span>
        </div>
    </section>

    <section class="surface" id="deploymentHistorySection" data-running="<?php echo $hasRunningDeployment ? '1' : '0'; ?>">
        <h2 class="section-title">Deployment-Historie</h2>
        <?php if ($hasRunningDeployment): ?>
            <div class="hint-card hint-card--info" id="deploymentHistoryAutoRefreshNotice">
                <div>Ein Deployment läuft gerade. Die History kann automatisch alle 5 Sekunden aktualisiert werden, bis der Status auf <code>success</code> oder <code>failed</code> kippt.</div>
                <div class="submit-row">
                    <button class="btn btn--secondary btn--sm" type="button" id="deploymentHistoryAutoRefreshToggle">Auto-Refresh deaktivieren</button>
                </div>
            </div>
        <?php endif; ?>
        <div id="deploymentHistoryContent">
            <?php if (empty($deployments)): ?>
                <p class="empty-state">Noch keine Deployments vorhanden.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Typ</th>
                                <th>Status</th>
                                <th>Version</th>
                                <th>Dauer</th>
                                <th>Gestartet</th>
                                <th>Ausgelöst durch</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deployments as $d): ?>
                        <?php
                        $status = (string)($d['status'] ?? 'pending');
                        $statusClass = match ($status) {
                            'success' => 'healthy',
                            'running' => 'degraded',
                            'failed' => 'down',
                            'rolled_back' => 'timeout',
                            default => 'unknown',
                        };
                        $duration = '—';
                        if (($d['started_at'] ?? null) !== null && ($d['finished_at'] ?? null) !== null) {
                            $start = strtotime((string)$d['started_at']);
                            $finish = strtotime((string)$d['finished_at']);
                            if ($start !== false && $finish !== false) {
                                $duration = ($finish - $start) . 's';
                            }
                        }
                        $version = '—';
                        if ((string)($d['version_from'] ?? '') !== '' && (string)($d['version_to'] ?? '') !== '') {
                            $version = htmlspecialchars((string)$d['version_from'], ENT_QUOTES) . ' → ' . htmlspecialchars((string)$d['version_to'], ENT_QUOTES);
                        }
                        $createdStr = '—';
                        if (($d['created_at'] ?? null) !== null) {
                            $createdTime = strtotime((string)$d['created_at']);
                            if ($createdTime !== false) {
                                $diff = time() - $createdTime;
                                $createdStr = $diff < 3600 ? ('vor ' . ceil($diff / 60) . ' Min') : date('d.m.Y H:i', $createdTime);
                            }
                        }
                        $rollbackBackupPath = trim((string)($d['latest_backup_path'] ?? ''));
                        ?>
                            <tr id="deployment-<?php echo (int)($d['id'] ?? 0); ?>">
                                <td>
                                    <?php if (!empty($d['log'])): ?>
                                        <details class="table-log">
                                            <summary>#<?php echo (int)($d['id'] ?? 0); ?></summary>
                                            <pre class="code-block"><?php echo htmlspecialchars((string)($d['log'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></pre>
                                        </details>
                                    <?php else: ?>
                                        #<?php echo (int)($d['id'] ?? 0); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="mono"><?php echo htmlspecialchars((string)($d['type'] ?? 'cms'), ENT_QUOTES); ?></td>
                                <td><span class="status-pill status-pill--<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES); ?></span></td>
                                <td class="mono"><?php echo $version; ?></td>
                                <td class="mono"><?php echo $duration; ?></td>
                                <td class="text-muted"><?php echo $createdStr; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars((string)($d['triggered_by'] ?? 'manual'), ENT_QUOTES); ?></div>
                                    <?php if ($rollbackBackupPath !== ''): ?>
                                        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/<?php echo (int)($d['id'] ?? 0); ?>/rollback" class="table-inline-form" onsubmit="return confirm('Rollback auf den Stand dieses Deployments starten?');">
                                            <?php echo Csrf::field(); ?>
                                            <button class="btn btn--linkish link-action link-action--warning" type="submit">Rollback auf diesen Stand</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($status === 'running'): ?>
                                        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/deployments/<?php echo (int)($d['id'] ?? 0); ?>/stop" class="table-inline-form" onsubmit="return confirm('Hängendes Deployment wirklich manuell auf failed setzen?');">
                                            <?php echo Csrf::field(); ?>
                                            <button class="btn btn--linkish link-action link-action--danger" type="submit">Als fehlgeschlagen markieren</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($status === 'rolled_back'): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="hint-card hint-card--warning">
                                        <strong>Automatischer Rollback nicht möglich – manueller Eingriff erforderlich.</strong>
                                        <?php if ($rollbackBackupPath !== ''): ?>
                                            <div>Backup-Pfad: <code><?php echo htmlspecialchars($rollbackBackupPath, ENT_QUOTES); ?></code></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
$extraScripts = '<script src="/assets/deploy-frontend-upload.js"></script>';
if ($hasRunningDeployment) {
    $extraScripts .= <<<HTML
<script>
(function () {
    var storageKey = 'deployment-history-auto-refresh';
    var section = document.getElementById('deploymentHistorySection');
    var content = document.getElementById('deploymentHistoryContent');
    var notice = document.getElementById('deploymentHistoryAutoRefreshNotice');
    var toggle = document.getElementById('deploymentHistoryAutoRefreshToggle');
    var intervalId = null;

    function autoRefreshEnabled() {
        return window.localStorage.getItem(storageKey) !== 'off';
    }

    function updateToggleLabel() {
        if (!toggle) {
            return;
        }
        toggle.textContent = autoRefreshEnabled() ? 'Auto-Refresh deaktivieren' : 'Auto-Refresh aktivieren';
    }

    function stopPolling() {
        if (intervalId !== null) {
            window.clearInterval(intervalId);
            intervalId = null;
        }
    }

    function bindToggle() {
        if (!toggle || toggle.dataset.bound === '1') {
            return;
        }
        toggle.dataset.bound = '1';
        toggle.addEventListener('click', function () {
            if (autoRefreshEnabled()) {
                window.localStorage.setItem(storageKey, 'off');
                stopPolling();
            } else {
                window.localStorage.removeItem(storageKey);
                startPolling();
            }
            updateToggleLabel();
        });
    }

    function refreshHistory() {
        fetch(window.location.href, { credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextSection = doc.getElementById('deploymentHistorySection');
                var nextContent = doc.getElementById('deploymentHistoryContent');
                if (!nextSection || !nextContent || !section || !content) {
                    return;
                }

                content.innerHTML = nextContent.innerHTML;
                section.setAttribute('data-running', nextSection.getAttribute('data-running') || '0');

                var stillRunning = nextSection.getAttribute('data-running') === '1';
                if (!stillRunning) {
                    stopPolling();
                    if (notice) {
                        notice.innerHTML = '<div>Kein Deployment läuft mehr. Auto-Refresh wurde gestoppt.</div><div class="submit-row"><button class="btn btn--secondary btn--sm" type="button" id="deploymentHistoryAutoRefreshToggle">Auto-Refresh aktivieren</button></div>';
                        toggle = document.getElementById('deploymentHistoryAutoRefreshToggle');
                        bindToggle();
                        updateToggleLabel();
                    }
                }
            })
            .catch(function () {});
    }

    function startPolling() {
        if (!section || !content || intervalId !== null || !autoRefreshEnabled()) {
            return;
        }
        intervalId = window.setInterval(refreshHistory, 5000);
    }

    updateToggleLabel();
    bindToggle();
    startPolling();
})();
</script>
HTML;
}
require __DIR__ . '/../layout.php';
