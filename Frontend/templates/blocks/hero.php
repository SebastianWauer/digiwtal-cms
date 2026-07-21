<?php
$headline = $data['headline'] ?? $data['title'] ?? $block['headline'] ?? $block['title'] ?? null;
$subheadline = $data['subheadline'] ?? $data['subtitle'] ?? $block['subheadline'] ?? $block['subtitle'] ?? null;

if ($headline !== null || $subheadline !== null):
    if ($headline !== null):
?>
<h2><?php echo e((string)$headline); ?></h2>
<?php
    endif;
    if ($subheadline !== null):
?>
<p><?php echo e((string)$subheadline); ?></p>
<?php
    endif;
else:
    $dump = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<pre><?php echo e($dump !== false ? $dump : '{}'); ?></pre>
<?php endif; ?>
