<?php
declare(strict_types=1);

$title = 'Kunde bearbeiten';
ob_start();
?>
<div class="view-stack view-stack--narrow">
    <section class="surface">
        <header class="page-header">
            <div class="page-header__main">
                <h1 class="page-title">Kunde bearbeiten</h1>
                <p class="page-subtitle">Kundendaten, Vertragsstatus und Aktivierung pflegen.</p>
            </div>
            <div class="page-actions">
                <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>/toggle" class="table-inline-form">
                    <?php echo Csrf::field(); ?>
                    <button type="submit" class="btn <?php echo ((int)($customer['is_active'] ?? 0) === 1) ? 'btn--danger' : 'btn--success'; ?> btn--sm">
                        <?php echo ((int)($customer['is_active'] ?? 0) === 1) ? 'Deaktivieren' : 'Aktivieren'; ?>
                    </button>
                </form>
                <a class="btn btn--secondary btn--sm" href="/admin/customers">Zurück</a>
            </div>
        </header>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/customers/<?php echo (int)($customer['id'] ?? 0); ?>" class="form-stack">
            <?php echo Csrf::field(); ?>

            <div class="form-grid form-grid--2">
                <div class="field">
                    <label for="name">Name *</label>
                    <input class="input" type="text" id="name" name="name" value="<?php echo htmlspecialchars((string)($old['name'] ?? $customer['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="field">
                    <label for="domain">Domain</label>
                    <input class="input" type="text" id="domain" name="domain" value="<?php echo htmlspecialchars((string)($old['domain'] ?? $customer['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="kunde.de">
                </div>
            </div>

            <div class="form-grid form-grid--2">
                <div class="field">
                    <label for="email">E-Mail</label>
                    <input class="input" type="email" id="email" name="email" value="<?php echo htmlspecialchars((string)($old['email'] ?? $customer['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="kunde@beispiel.de">
                </div>
                <div class="field">
                    <label for="abo_status">Abo-Status</label>
                    <select class="select" id="abo_status" name="abo_status">
                        <?php
                        $currentAbo = (string)($old['abo_status'] ?? $customer['abo_status'] ?? 'active');
                        foreach (['active' => 'Aktiv', 'cancelled' => 'Gekündigt', 'suspended' => 'Gesperrt'] as $val => $lbl):
                        ?>
                            <option value="<?php echo $val; ?>" <?php echo $val === $currentAbo ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="notes">Notizen</label>
                <textarea class="textarea" id="notes" name="notes" rows="4" placeholder="Interne Notizen zum Kunden..."><?php echo htmlspecialchars((string)($old['notes'] ?? $customer['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
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
