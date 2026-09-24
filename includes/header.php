<?php
declare(strict_types=1);
$layoutUser = current_user();
$layoutSite = app_config();
$pageTitle = $pageTitle ?? 'ELIA';

// Flash message: read once, then clear.
$notice = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$noticeType = in_array($notice['type'] ?? '', ['success', 'warning', 'danger'], true) ? $notice['type'] : 'info';

// Signed-in users go to their own dashboard; guests go to the landing page.
$homePath = $layoutUser ? dashboard_path($layoutUser['role']) : 'index.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> | OMSC <?= escape($layoutSite['site_name']) ?></title>
    <?php require __DIR__ . '/styles.php'; ?>
    <link rel="stylesheet" href="<?= escape(url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= escape(url('assets/css/app-theme.css')) ?>">
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to content</a>

<header class="navbar navbar-dark site-header px-3 px-lg-4" data-bs-theme="dark">
    <a class="navbar-brand" href="<?= escape(url($homePath)) ?>">
        <img src="<?= escape(url('assets/img/elia-logo.png')) ?>" alt="" class="brand-logo" width="36" height="36">
        <span>
            <strong>OMSC <span class="brand-accent"><?= escape($layoutSite['site_name']) ?></span></strong>
            <span class="brand-caption">Internationalization Affairs</span>
        </span>
    </a>

    <?php if ($layoutUser): ?>
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="header-user d-none d-lg-flex align-items-center gap-3">
            <span class="user-name"><?= escape($layoutUser['full_name']) ?></span>
            <span class="badge text-bg-light"><?= escape(ucfirst($layoutUser['role'])) ?></span>
        </div>
    <?php else: ?>
        <a class="btn btn-sm btn-outline-light" href="<?= escape(url('login.php')) ?>">Sign In</a>
    <?php endif; ?>
</header>

<div class="app-layout <?= $layoutUser ? '' : 'guest-layout' ?>">
    <?php if ($layoutUser) { require __DIR__ . '/sidebar.php'; } ?>
    <main id="main-content" class="main-content p-3 p-md-4 p-xl-5" tabindex="-1">
        <?php if ($layoutUser): ?>
            <nav aria-label="Breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><?= escape($layoutSite['site_name']) ?></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= escape($pageTitle) ?></li>
                </ol>
            </nav>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="alert alert-<?= escape($noticeType) ?>" role="<?= $noticeType === 'danger' ? 'alert' : 'status' ?>"><?= escape((string) ($notice['message'] ?? '')) ?></div>
        <?php endif; ?>