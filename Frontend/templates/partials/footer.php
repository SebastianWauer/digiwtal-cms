<footer>
    <?php if (!empty($footerNavItems ?? [])): ?>
        <nav>
            <?php foreach (($footerNavItems ?? []) as $item): ?>
                <?php
                $url = (string)($item['url'] ?? '');
                if ($url === '') {
                    $itemSlug = (string)($item['slug'] ?? '');
                    $url = in_array($itemSlug, ['start', 'home'], true) ? '/' : '/' . ltrim($itemSlug, '/');
                }
                $label = (string)($item['title'] ?? '');
                ?>
                <a href="<?php echo e($url); ?>"><?php echo e($label !== '' ? $label : 'Link'); ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <p>
        &copy; <?php echo date('Y'); ?> <?php echo e($siteName ?? 'Website'); ?>.
        Alle Rechte vorbehalten.
        Website entwickelt von <a href="https://digiwtal.de" target="_blank" rel="noopener">DigiWtal</a>.
    </p>
</footer>
