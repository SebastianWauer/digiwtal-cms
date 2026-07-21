<?php
$dump = json_encode($block ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<pre><?php echo e($dump !== false ? $dump : '{}'); ?></pre>
