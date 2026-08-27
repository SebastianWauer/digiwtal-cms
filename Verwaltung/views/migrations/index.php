<?php
$title = 'Datenbank';
$activeNav = 'migrations';
ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Datenbank</h1>
                <p class="page-subtitle">Migrationen der Verwaltung anzeigen und anwenden.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--secondary btn--sm" href="/admin/dashboard">Dashboard</a>
            </div>
        </header>
    </section>

    <?php foreach ($errors as $fehler): ?>
        <section class="surface">
            <div class="alert alert--error"><?php echo htmlspecialchars((string)$fehler, ENT_QUOTES); ?></div>
        </section>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <section class="surface">
            <div class="alert alert--success"><?php echo htmlspecialchars((string)$success, ENT_QUOTES); ?></div>
            <?php if ($lauf !== []): ?>
                <ul class="inline-list mono">
                    <?php foreach ($lauf as $name): ?>
                        <li><?php echo htmlspecialchars((string)$name, ENT_QUOTES); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($dbFehler !== null): ?>
        <section class="surface">
            <div class="alert alert--error">
                Die Migrationstabelle ist nicht lesbar: <?php echo htmlspecialchars((string)$dbFehler, ENT_QUOTES); ?>
            </div>
            <p class="section-copy">Pruefe <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> und
               <code>DB_PASS</code> in der <code>.env</code> der Verwaltung.</p>
        </section>
    <?php else: ?>

    <section class="surface">
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card__label">Angewendet</div>
                <div class="info-card__value"><?php echo count($applied); ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__label">Offen</div>
                <div class="info-card__value"><?php echo (int)$offen; ?></div>
            </div>
            <div class="info-card">
                <div class="info-card__label">Davon bereits vorhanden</div>
                <div class="info-card__value"><?php echo (int)$bestand; ?></div>
            </div>
        </div>
    </section>

    <?php if ($erstlauf): ?>
        <section class="surface">
            <div class="hint-card hint-card--warning">
                <h2 class="section-title">Erst den Bestand markieren</h2>
                <p class="section-copy">
                    Diese Datenbank kennt noch keinen Migrationsstand: Es ist nichts markiert, obwohl die
                    Tabellen laengst existieren - sie wurden frueher von Hand eingespielt. Wuerde jetzt
                    <em>Anwenden</em> laufen, scheiterte gleich die erste Datei daran, dass ihre Tabelle
                    schon da ist.
                </p>
                <p class="section-copy">
                    Unten sind <?php echo (int)$bestand; ?> von <?php echo (int)$offen; ?> Dateien vorausgewaehlt:
                    genau die, deren Tabellen bereits stehen. Was wirklich neu ist, bleibt bewusst ohne Haken -
                    sonst wuerde es als angewendet gelten und nie laufen.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <section class="surface">
        <h2 class="section-title">Offene Migrationen</h2>
        <?php if ($analyse === []): ?>
            <p class="empty-state">Keine offenen Migrationen. Die Datenbank ist auf Stand.</p>
        <?php else: ?>
            <form method="post" action="/admin/migrations/baseline">
                <?php echo Csrf::field(); ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bestand</th>
                                <th>Datei</th>
                                <th>Tabellen</th>
                                <th>Anfang der Datei</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($analyse as $eintrag): ?>
                            <tr>
                                <td>
                                    <label class="checkbox-line">
                                        <input type="checkbox" name="dateien[]"
                                               value="<?php echo htmlspecialchars((string)$eintrag['datei'], ENT_QUOTES); ?>"
                                               <?php echo $eintrag['bestand'] ? 'checked' : ''; ?>>
                                    </label>
                                </td>
                                <td class="mono"><?php echo htmlspecialchars((string)$eintrag['datei'], ENT_QUOTES); ?></td>
                                <td>
                                    <span class="mono"><?php echo htmlspecialchars(implode(', ', $eintrag['tabellen']) ?: '-', ENT_QUOTES); ?></span><br>
                                    <?php if ($eintrag['bestand']): ?>
                                        <span class="status-pill status-pill--healthy">vorhanden</span>
                                    <?php else: ?>
                                        <span class="status-pill status-pill--unknown">neu</span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="code-block"><?php echo htmlspecialchars((string)$eintrag['vorschau'], ENT_QUOTES); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="submit-row">
                    <button class="btn <?php echo $erstlauf ? 'btn--warning' : 'btn--secondary'; ?> btn--sm" type="submit">
                        Angehakte als angewendet markieren (nichts ausfuehren)
                    </button>
                </div>
            </form>

            <?php if ($erstlauf): ?>
                <p class="section-copy text-muted">
                    <em>Anwenden</em> erscheint, sobald der Bestand markiert ist.
                </p>
            <?php else: ?>
                <form method="post" action="/admin/migrations/apply" class="submit-row">
                    <?php echo Csrf::field(); ?>
                    <button class="btn btn--primary" type="submit">
                        <?php echo (int)$offen; ?> Migration(en) anwenden
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="surface">
        <h2 class="section-title">Bereits angewendet</h2>
        <?php if ($applied === []): ?>
            <p class="empty-state">Noch nichts markiert.</p>
        <?php else: ?>
            <details>
                <summary class="section-copy"><?php echo count($applied); ?> Datei(en) anzeigen</summary>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Datei</th><th>Angewendet am</th></tr></thead>
                        <tbody>
                        <?php foreach ($applied as $name => $zeitpunkt): ?>
                            <tr>
                                <td class="mono"><?php echo htmlspecialchars((string)$name, ENT_QUOTES); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars((string)$zeitpunkt, ENT_QUOTES); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </section>

    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
