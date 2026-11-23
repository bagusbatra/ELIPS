<?php
// Direktori: partials/head.php
/** <head> bersama. Kunci $page: title, description, css[], head_extra (HTML), noindex. */
$title = $page['title'] ?? 'ELIPS LMS';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
<?php if (!empty($page['description'])): ?>
    <meta name="description" content="<?= e($page['description']) ?>">
<?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/badges.css') ?>">
<?php foreach (($page['css'] ?? []) as $css): ?>
    <link rel="stylesheet" href="<?= asset('css/' . $css . '.css') ?>">
<?php endforeach; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?= $page['head_extra'] ?? '' ?>

    <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
    <meta name="theme-color" content="#e98c1e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ELIPS">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('icons/favicon-32.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('icons/apple-touch-icon.png') ?>">
    <script>window.ELIPS_BASE = <?= json_encode(BASE_URL) ?>;</script>
    <script src="<?= asset('js/pwa.js') ?>" defer></script>
</head>
<body<?= !empty($page['body_class']) ? ' class="' . e($page['body_class']) . '"' : '' ?>>