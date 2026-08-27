<?php
$title = 'Hilfe';
$activeNav = 'support';

/** Statusnamen und Farben an einer Stelle, damit Liste und Detail nicht auseinanderlaufen. */
$statusText = [
    'neu'        => 'Neu',
    'in_arbeit'  => 'In Arbeit',
    'beantwortet'=> 'Beantwortet',
    'erledigt'   => 'Erledigt',
];
$statusFarbe = [
    'neu'        => 'status-pill--degraded',
    'in_arbeit'  => 'status-pill--unknown',
    'beantwortet'=> 'status-pill--healthy',
    'erledigt'   => 'status-pill--na',
];
$artText = ['problem' => 'Problem', 'vorschlag' => 'Vorschlag', 'frage' => 'Frage'];

$aktiverStatus = (string)($_GET['status'] ?? '');
$aktiverKunde  = (int)($_GET['kunde'] ?? 0);

$filterUrl = static function (string $status, int $kunde): string {
    $teile = [];
    if ($status !== '') {
        $teile[] = 'status=' . rawurlencode($status);
    }
    if ($kunde > 0) {
        $teile[] = 'kunde=' . $kunde;
    }
    return '/admin/support' . ($teile === [] ? '' : ('?' . implode('&', $teile)));
};

ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Hilfe</h1>
                <p class="page-subtitle">Meldungen aus den Kunden-CMS - Probleme, Vorschläge und Fragen an einer Stelle.</p>
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
        </section>
    <?php endif; ?>

    <?php if ($zugangsdaten !== null): ?>
        <section class="surface">
            <div class="hint-card hint-card--warning">
                <h2 class="section-title">Zugangsdaten für „<?php echo htmlspecialchars((string)$zugangsdaten['kunde'], ENT_QUOTES); ?>"</h2>
                <p class="section-copy">
                    Diese zwei Zeilen ans Ende der <code>.env</code> des CMS dieser Instanz setzen. Danach ist
                    „Hilfe" dort einsatzbereit. Das Token wird nur jetzt angezeigt.
                </p>
                <code class="code-block">SUPPORT_URL=<?php echo htmlspecialchars((string)$zugangsdaten['url'], ENT_QUOTES); ?>

SUPPORT_TOKEN=<?php echo htmlspecialchars((string)$zugangsdaten['token'], ENT_QUOTES); ?></code>
                <p class="section-copy text-muted">
                    Instanzen, die über <em>Rollout</em> ausgerollt werden, bekommen beides automatisch —
                    hier steht es für alles, was von Hand gepflegt wird.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <section class="surface">
        <div class="submit-row">
            <a class="btn btn--sm <?php echo $aktiverStatus === '' ? 'btn--primary' : 'btn--ghost'; ?>"
               href="<?php echo htmlspecialchars($filterUrl('', $aktiverKunde), ENT_QUOTES); ?>">
                Alle (<?php echo array_sum($zaehler); ?>)
            </a>
            <?php foreach ($statusText as $schluessel => $beschriftung): ?>
                <a class="btn btn--sm <?php echo $aktiverStatus === $schluessel ? 'btn--primary' : 'btn--ghost'; ?>"
                   href="<?php echo htmlspecialchars($filterUrl($schluessel, $aktiverKunde), ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($beschriftung, ENT_QUOTES); ?> (<?php echo (int)($zaehler[$schluessel] ?? 0); ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (count($kunden) > 1): ?>
            <div class="submit-row" style="margin-top:.75rem;">
                <a class="btn btn--sm <?php echo $aktiverKunde === 0 ? 'btn--primary' : 'btn--ghost'; ?>"
                   href="<?php echo htmlspecialchars($filterUrl($aktiverStatus, 0), ENT_QUOTES); ?>">Alle Kunden</a>
                <?php foreach ($kunden as $kunde): ?>
                    <a class="btn btn--sm <?php echo $aktiverKunde === (int)$kunde['id'] ? 'btn--primary' : 'btn--ghost'; ?>"
                       href="<?php echo htmlspecialchars($filterUrl($aktiverStatus, (int)$kunde['id']), ENT_QUOTES); ?>">
                        <?php echo htmlspecialchars((string)$kunde['name'], ENT_QUOTES); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="surface">
        <?php if ($eintraege === []): ?>
            <p class="empty-state">Keine Meldungen. Das ist entweder ein gutes Zeichen oder die Hilfe-Funktion ist bei den Kunden noch nicht angekommen.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Eingegangen</th>
                            <th>Kunde</th>
                            <th>Art</th>
                            <th>Betreff</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($eintraege as $eintrag):
                        $status = (string)($eintrag['status'] ?? 'neu');
                    ?>
                        <tr>
                            <td class="text-muted"><?php echo htmlspecialchars((string)($eintrag['created_at'] ?? ''), ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string)($eintrag['customer_name'] ?? '—'), ENT_QUOTES); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($artText[(string)($eintrag['kind'] ?? '')] ?? '—', ENT_QUOTES); ?></td>
                            <td><?php echo htmlspecialchars((string)($eintrag['subject'] ?? ''), ENT_QUOTES); ?></td>
                            <td>
                                <span class="status-pill <?php echo $statusFarbe[$status] ?? 'status-pill--unknown'; ?>">
                                    <?php echo htmlspecialchars($statusText[$status] ?? $status, ENT_QUOTES); ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn--secondary btn--sm" href="/admin/support/<?php echo (int)$eintrag['id']; ?>">Öffnen</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <section class="surface">
        <h2 class="section-title">Instanz verbinden</h2>
        <p class="section-copy text-muted">
            Eine Instanz meldet sich mit einem eigenen Token. Beim Rollout wandert es von selbst in die
            <code>.env</code>. Für Installationen, die nicht über die Pipeline laufen — die eigene zum
            Beispiel — stehen die zwei Zeilen hier zum Abschreiben.
        </p>
        <?php if ($verwaltungUrl === ''): ?>
            <div class="alert alert--error">
                Eigene Adresse unbekannt. <code>ADMIN_HOST</code> in der <code>.env</code> der Verwaltung setzen,
                sonst weiß eine Instanz nicht, wohin sie melden soll.
            </div>
        <?php elseif ($kunden === []): ?>
            <p class="empty-state">Noch keine Kunden angelegt.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Kunde</th><th>Meldet an</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($kunden as $kunde): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$kunde['name'], ENT_QUOTES); ?></td>
                            <td class="mono text-muted"><?php echo htmlspecialchars($verwaltungUrl, ENT_QUOTES); ?></td>
                            <td>
                                <form method="post" action="/admin/support/zugang/<?php echo (int)$kunde['id']; ?>">
                                    <?php echo Csrf::field(); ?>
                                    <button class="btn btn--secondary btn--sm" type="submit">Zugangsdaten anzeigen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
