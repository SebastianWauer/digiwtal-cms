<?php
$text = $data['text'] ?? $block['text'] ?? null;
if ($text !== null):
?>
<div><?php echo e((string)$text); ?></div>
<?php
else:
    $dump = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<pre><?php echo e($dump !== false ? $dump : '{}'); ?></pre>
<?php endif; ?>
