<?php
declare(strict_types=1);
$canEdit = admin_can('pages.edit');
echo flash_render($flash ?? null);
?>
<form method="post" action="<?= h(cms_base_path()) ?>/pages/onepager" class="onepager-form">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="onepager_revision" value="<?= h($snapshot['revision']) ?>">
    <input type="hidden" name="onepager_order" value="<?= h(json_encode(array_column($snapshot['pages'], 'id'))) ?>" data-onepager-order>
    <ol class="onepager-list" data-onepager-list>
        <?php foreach ($snapshot['pages'] as $index => $row): ?>
        <li class="onepager-row" data-page-id="<?= $row['id'] ?>" data-home="<?= $row['is_home'] ? '1' : '0' ?>" draggable="<?= $canEdit && !$row['is_home'] ? 'true' : 'false' ?>">
            <span data-position><?= $index + 1 ?></span>
            <label class="onepager-check">
                <input type="checkbox" name="onepager_selected[]" value="<?= $row['id'] ?>" <?= $row['visible'] ? 'checked' : '' ?> <?= !$canEdit || !$row['eligible'] || $row['is_home'] ? 'disabled' : '' ?> aria-label="<?= h($row['title']) ?> im Onepager anzeigen">
                <span>Im Onepager</span>
            </label>
            <div class="onepager-title"><a href="<?= h(cms_base_path()) ?>/pages/edit?id=<?= $row['id'] ?>"><?= h($row['title']) ?></a><small><?= h($row['slug']) ?></small></div>
            <span class="onepager-status"><?= $row['is_home'] ? 'Startseite' : ($row['status'] !== 'live' ? 'Entwurf' : ($row['redirect_type'] !== 'none' ? 'Weiterleitung' : '')) ?></span>
            <div class="onepager-controls">
                <button type="button" data-move="up" title="Nach oben" aria-label="<?= h($row['title']) ?> nach oben" <?= !$canEdit || $row['is_home'] ? 'disabled' : '' ?>>&#8593;</button>
                <button type="button" data-move="down" title="Nach unten" aria-label="<?= h($row['title']) ?> nach unten" <?= !$canEdit || $row['is_home'] ? 'disabled' : '' ?>>&#8595;</button>
                <span title="Verschieben" aria-hidden="true">&#8942;</span>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
    <div class="onepager-actions"><a class="btn btn--ghost" href="<?= h(cms_base_path()) ?>/pages">Seiten</a><?php if ($canEdit): ?><button class="btn btn--primary" type="submit">Speichern</button><?php endif; ?></div>
</form>
<script src="<?= h(cms_base_path()) ?>/assets/js/admin-onepager.js?v=<?= (int)filemtime(__DIR__ . '/../../public/assets/js/admin-onepager.js') ?>" defer></script>
