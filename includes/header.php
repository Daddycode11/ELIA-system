<?php
declare(strict_types=1);
$layoutUser = current_user();
$notice = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle ?? 'ELIA') ?> | OMSC ELIA</title>
    <?php require __DIR__ . '/styles.php'; ?>
    <link rel="stylesheet" href="<?= escape(url('assets/css/app.css')) ?>">
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to content</a>
<header class="navbar navbar-dark site-header px-3 px-lg-4">
    <a class="navbar-brand" href="<?= escape(url('index.php')) ?>"><strong>OMSC <span class="brand-accent">ELIA</span></strong><span class="brand-caption">Internationalization Affairs</span></a>
    <?php if ($layoutUser): ?>
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="d-none d-lg-flex align-items-center gap-3 text-white"><span><?= escape($layoutUser['full_name']) ?></span><span class="badge text-bg-light"><?= escape(ucfirst($layoutUser['role'])) ?></span></div>
    <?php endif; ?>
</header>
<div class="app-layout <?= $layoutUser ? '' : 'guest-layout' ?>">
    <?php if ($layoutUser) { require __DIR__ . '/sidebar.php'; } ?>
    <main id="main-content" class="main-content p-3 p-md-4 p-xl-5" tabindex="-1">
        <?php if ($layoutUser): ?>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item">ELIA</li><li class="breadcrumb-item active" aria-current="page"><?= escape($pageTitle) ?></li></ol></nav>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="alert alert-<?= in_array($notice['type'], ['success', 'warning', 'danger'], true) ? $notice['type'] : 'info' ?>" role="alert"><?= escape($notice['message']) ?></div>
        <?php endif; ?>
