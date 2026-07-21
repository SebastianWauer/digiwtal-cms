<?php
/** @var string $title */
/** @var string|null $pageCss */

$title = $title ?? '';
$pageCss = $pageCss ?? null;

$css = function(string $rel) {
  $abs = \Paths::root('public/' . ltrim($rel, '/'));
  $v = is_file($abs) ? filemtime($abs) : time();
  return '/' . ltrim($rel, '/') . '?v=' . $v;
};
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= h(admin_csrf_token()) ?>">
  <title><?= h($title) ?></title>
  <link rel="stylesheet" href="<?= h($css('css/admin-layout.css')) ?>">
  <link rel="stylesheet" href="<?= h($css('css/admin-components.css')) ?>">
  <link rel="stylesheet" href="<?= h($css('css/admin-media-picker.css')) ?>">
  <?php if ($pageCss): ?>
    <link rel="stylesheet" href="<?= h($css('css/admin-' . $pageCss . '.css')) ?>">
  <?php endif; ?>
</head>
<body class="is-picker">
<div class="page">
  <main class="main">
  </main>
</div>
