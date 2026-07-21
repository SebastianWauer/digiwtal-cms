<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$title = 'Geheimnis anzeigen';
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Zugangsdaten</h1>
                <p class="page-subtitle">Einmalige Anzeige des entschlüsselten Secrets.</p>
            </div>
        </header>

        <div class="list-detail">
            <div><strong>Label:</strong> <?php echo htmlspecialchars((string)($meta['label'] ?? ''), ENT_QUOTES); ?></div>
            <div><strong>Host:</strong> <?php echo htmlspecialchars((string)($meta['host'] ?? ''), ENT_QUOTES); ?></div>
            <div><strong>Username:</strong> <?php echo htmlspecialchars((string)($meta['username'] ?? ''), ENT_QUOTES); ?></div>
        </div>

        <div class="secret-box">
            <p class="secret-box__label">Secret</p>
            <pre class="secret-box__value"><?php echo htmlspecialchars($secret, ENT_QUOTES); ?></pre>
        </div>

        <div class="alert alert--warning">Diese Seite wird nicht gecacht. Bitte kopiere das Secret und schließe die Seite wieder.</div>

        <div class="submit-row">
            <a class="btn btn--secondary" href="/admin/customers/<?php echo (int)($meta['customer_id'] ?? 0); ?>/vault">Zurück</a>
        </div>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
