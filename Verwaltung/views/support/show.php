<?php
$title = 'Meldung #' . (int)$ticket['id'];
$activeNav = 'support';

$statusText = [
    'neu'         => 'Neu',
    'in_arbeit'   => 'In Arbeit',
    'beantwortet' => 'Beantwortet',
    'erledigt'    => 'Erledigt',
];
$statusFarbe = [
    'neu'         => 'status-pill--degraded',
    'in_arbeit'   => 'status-pill--unknown',
    'beantwortet' => 'status-pill--healthy',
    'erledigt'    => 'status-pill--na',
];
$artText = ['problem' => 'Problem', 'vorschlag' => 'Vorschlag', 'frage' => 'Frage'];
$status  = (string)($ticket['status'] ?? 'neu');

ob_start();
?>
<div class="view-stack">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title"><?php echo htmlspecialchars((string)$ticket['subject'], ENT_QUOTES); ?></h1>
                <p class="page-subtitle">
                    <?php echo htmlspecialchars((string)($ticket['customer_name'] ?? '—'), ENT_QUOTES); ?> ·
                    <?php echo htmlspecialchars($artText[(string)$ticket['kind']] ?? '—', ENT_QUOTES); ?> ·
                    <?php echo htmlspecialchars((string)$ticket['created_at'], ENT_QUOTES); ?>
                </p>
            </div>
            <div class="page-actions">
                <span class="status-pill <?php echo $statusFarbe[$status] ?? 'status-pill--unknown'; ?>">
                    <?php echo htmlspecialchars($statusText[$status] ?? $status, ENT_QUOTES); ?>
                </span>
                <a class="btn btn--secondary btn--sm" href="/admin/support">Posteingang</a>
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

    <section class="surface">
        <h2 class="section-title">Meldung</h2>
        <p class="section-copy" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string)$ticket['body'], ENT_QUOTES); ?></p>
        <?php if (trim((string)($ticket['reporter_name'] ?? '')) !== '' || trim((string)($ticket['reporter_email'] ?? '')) !== ''): ?>
            <p class="section-copy text-muted">
                Gemeldet von <?php echo htmlspecialchars((string)$ticket['reporter_name'], ENT_QUOTES); ?>
                <?php if (trim((string)$ticket['reporter_email']) !== ''): ?>
                    &lt;<?php echo htmlspecialchars((string)$ticket['reporter_email'], ENT_QUOTES); ?>&gt;
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </section>

    <?php if ($kontext !== []): ?>
        <section class="surface">
            <h2 class="section-title">Umgebung</h2>
            <p class="section-copy text-muted">Von der Instanz automatisch mitgeschickt.</p>
            <div class="table-wrap">
                <table class="data-table">
                    <tbody>
                    <?php foreach ($kontext as $schluessel => $wert): ?>
                        <tr>
                            <td class="mono"><?php echo htmlspecialchars((string)$schluessel, ENT_QUOTES); ?></td>
                            <td class="mono"><?php echo htmlspecialchars((string)$wert, ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (trim((string)($ticket['answer'] ?? '')) !== ''): ?>
        <section class="surface">
            <h2 class="section-title">Bisherige Antwort</h2>
            <p class="section-copy" style="white-space:pre-wrap;"><?php echo htmlspecialchars((string)$ticket['answer'], ENT_QUOTES); ?></p>
            <p class="section-copy text-muted">Gesendet am <?php echo htmlspecialchars((string)($ticket['answered_at'] ?? ''), ENT_QUOTES); ?></p>
        </section>
    <?php endif; ?>

    <section class="surface">
        <h2 class="section-title">Antworten</h2>
        <p class="section-copy text-muted">Die Antwort erscheint im CMS des Kunden unter „Hilfe" - kein Umweg über E-Mail.</p>
        <form method="post" action="/admin/support/<?php echo (int)$ticket['id']; ?>">
            <?php echo Csrf::field(); ?>
            <div class="field">
                <label for="answer">Antwort</label>
                <textarea class="textarea" id="answer" name="answer" rows="6" placeholder="Was der Kunde wissen muss."><?php echo htmlspecialchars((string)($ticket['answer'] ?? ''), ENT_QUOTES); ?></textarea>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select class="select" id="status" name="status">
                    <option value="">unverändert</option>
                    <?php foreach ($statusText as $schluessel => $beschriftung): ?>
                        <option value="<?php echo htmlspecialchars($schluessel, ENT_QUOTES); ?>"
                            <?php echo $status === $schluessel ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($beschriftung, ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
