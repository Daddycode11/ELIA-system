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

// Header extras for signed-in users: initials avatar and notification bell.
$layoutInitials = '';
$layoutAlerts = [];
$layoutUnread = 0;
if ($layoutUser) {
    foreach (array_slice(preg_split('/\s+/', trim((string) $layoutUser['full_name'])) ?: [], 0, 2) as $part) {
        $layoutInitials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $layoutInitials = $layoutInitials !== '' ? $layoutInitials : 'U';
    try {
        $statement = database()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $statement->execute([$layoutUser['id']]);
        $layoutUnread = (int) $statement->fetchColumn();
        $statement = database()->prepare('SELECT request_id, message, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 6');
        $statement->execute([$layoutUser['id']]);
        $layoutAlerts = $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log((string) $exception);
    }
}
$layoutAgo = static function (string $value): string {
    $seconds = max(0, time() - (int) strtotime($value));
    if ($seconds < 3600) { return max(1, intdiv($seconds, 60)) . ' min ago'; }
    if ($seconds < 86400) { return intdiv($seconds, 3600) . ' h ago'; }
    if ($seconds < 604800) { return intdiv($seconds, 86400) . ' d ago'; }
    return date('M j, Y', (int) strtotime($value));
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> | OMSC <?= escape($layoutSite['site_name']) ?></title>
    <?php require __DIR__ . '/styles.php'; ?>
    <link rel="stylesheet" href="<?= escape(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= escape(url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= escape(url('assets/css/app-theme.css')) ?>">
    <style>
    body header.site-header {
        background: linear-gradient(100deg, #071a33 0%, #0b2545 55%, #14366e 100%);
        border-bottom: 3px solid #c9a227;
        min-height: 4.1rem;
        box-shadow: 0 2px 10px rgba(7, 26, 51, .25);
    }
    header.site-header .navbar-brand { display: flex; align-items: center; gap: .75rem; margin-right: 0; }
    header.site-header .brand-logo { background: #fff; border-radius: 50%; padding: 2px; object-fit: contain; }
    header.site-header .navbar-brand strong { display: block; font-size: 1.05rem; letter-spacing: .01em; line-height: 1.15; }
    header.site-header .brand-accent { color: #e9d8a6; }
    header.site-header .brand-caption { display: block; font-size: .72rem; font-weight: 400; color: #b9c6d8; letter-spacing: .06em; text-transform: uppercase; }
    .hx-actions { display: flex; align-items: center; gap: .35rem; }
    .hx-date { color: #b9c6d8; font-size: .82rem; padding-right: .9rem; margin-right: .5rem; border-right: 1px solid rgba(255, 255, 255, .2); }
    .hx-icon-btn { position: relative; width: 2.5rem; height: 2.5rem; display: grid; place-items: center; border: 0; border-radius: 50%; color: #fff; background: transparent; font-size: 1.15rem; }
    .hx-icon-btn:hover, .hx-icon-btn:focus-visible, .hx-icon-btn.show { background: rgba(255, 255, 255, .14); color: #fff; }
    .hx-badge { position: absolute; top: .15rem; right: .1rem; min-width: 1.1rem; height: 1.1rem; padding: 0 .3rem; border-radius: 99px; background: #ef4444; color: #fff; font-size: .68rem; font-weight: 700; line-height: 1.1rem; text-align: center; border: 2px solid #0b2545; box-sizing: content-box; }
    .hx-user { display: flex; align-items: center; gap: .6rem; color: #fff; background: transparent; border: 0; border-radius: 99px; padding: .25rem .6rem .25rem .3rem; }
    .hx-user:hover, .hx-user:focus-visible, .hx-user.show { background: rgba(255, 255, 255, .14); color: #fff; }
    .hx-avatar { width: 2.2rem; height: 2.2rem; display: grid; place-items: center; border-radius: 50%; background: #c9a227; color: #071a33; font-weight: 700; font-size: .85rem; }
    .hx-user-text { text-align: left; line-height: 1.15; }
    .hx-user-text b { display: block; font-size: .88rem; font-weight: 600; }
    .hx-user-text small { color: #e9d8a6; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
    .hx-menu { min-width: 18rem; border: 1px solid #e5e7eb; border-radius: .75rem; box-shadow: 0 12px 32px rgba(7, 26, 51, .18); padding: 0; overflow: hidden; }
    .hx-menu-head { padding: .85rem 1rem; background: #f9fafb; border-bottom: 1px solid #eef0f4; font-weight: 600; color: #0b2545; display: flex; justify-content: space-between; }
    .hx-alert { display: block; padding: .7rem 1rem; border-bottom: 1px solid #f1f2f5; color: #1f2937; text-decoration: none; font-size: .86rem; white-space: normal; }
    .hx-alert:hover { background: #f9fafb; color: #0b2545; }
    .hx-alert.unread { background: #fffbeb; border-left: 3px solid #c9a227; }
    .hx-alert small { display: block; color: #6b7280; margin-top: .15rem; }
    .hx-empty { padding: 1.5rem 1rem; text-align: center; color: #6b7280; font-size: .88rem; }
    .hx-crumbs { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .hx-crumbs .breadcrumb { margin: 0; font-size: .85rem; }
    .hx-crumbs .breadcrumb a { color: #14366e; text-decoration: none; }
    @media (max-width: 991.98px) { header.site-header .brand-caption { display: none; } }
    </style>
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to content</a>

<header class="navbar navbar-dark site-header px-3 px-lg-4" data-bs-theme="dark">
    <a class="navbar-brand" href="<?= escape(url($homePath)) ?>">
        <img src="<?= escape(url('assets/img/elia-logo.png')) ?>" alt="" class="brand-logo" width="40" height="40">
        <span>
            <strong>OMSC <span class="brand-accent"><?= escape($layoutSite['site_name']) ?></span></strong>
            <span class="brand-caption">Internationalization Affairs</span>
        </span>
    </a>

    <?php if ($layoutUser): ?>
        <div class="hx-actions">
            <span class="hx-date d-none d-xl-inline"><?= escape(date('l, F j, Y')) ?></span>

            <div class="dropdown">
                <button class="hx-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                        aria-label="Notifications<?= $layoutUnread > 0 ? ', ' . $layoutUnread . ' unread' : '' ?>">
                    <i class="bi bi-bell"></i>
                    <?php if ($layoutUnread > 0): ?><span class="hx-badge"><?= $layoutUnread > 9 ? '9+' : $layoutUnread ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end hx-menu" style="width: min(22rem, 92vw)">
                    <div class="hx-menu-head"><span>Notifications</span><?php if ($layoutUnread > 0): ?><span class="text-secondary fw-normal small"><?= $layoutUnread ?> unread</span><?php endif; ?></div>
                    <?php foreach ($layoutAlerts as $alert): ?>
                        <a class="hx-alert <?= $alert['read_at'] === null ? 'unread' : '' ?>"
                           href="<?= escape(url($layoutUser['role'] . '/requests/view.php?id=' . (int) $alert['request_id'])) ?>">
                            <?= escape($alert['message']) ?>
                            <small><?= escape($layoutAgo((string) $alert['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$layoutAlerts): ?><div class="hx-empty"><i class="bi bi-bell-slash fs-4 d-block mb-1"></i>No notifications yet.</div><?php endif; ?>
                </div>
            </div>

            <div class="dropdown d-none d-lg-block">
                <button class="hx-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="hx-avatar" aria-hidden="true"><?= escape($layoutInitials) ?></span>
                    <span class="hx-user-text"><b><?= escape($layoutUser['full_name']) ?></b><small><?= escape(ucfirst($layoutUser['role'])) ?></small></span>
                    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end hx-menu" style="min-width: 14rem">
                    <li class="hx-menu-head"><span><?= escape($layoutUser['full_name']) ?></span></li>
                    <li><a class="dropdown-item py-2" href="<?= escape(url($homePath)) ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                    <li><hr class="dropdown-divider m-0"></li>
                    <li>
                        <form method="post" action="<?= escape(url('actions/logout.php')) ?>"><?= csrf_field() ?>
                            <button class="dropdown-item py-2 text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>

            <button class="navbar-toggler d-lg-none ms-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    <?php else: ?>
        <a class="btn btn-sm btn-outline-light" href="<?= escape(url('login.php')) ?>">Sign In</a>
    <?php endif; ?>
</header>

<div class="app-layout <?= $layoutUser ? '' : 'guest-layout' ?>">
    <?php if ($layoutUser) { require __DIR__ . '/sidebar.php'; } ?>
    <main id="main-content" class="main-content p-3 p-md-4 p-xl-5" tabindex="-1">
        <?php if ($layoutUser): ?>
            <div class="hx-crumbs">
                <nav aria-label="Breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= escape(url($homePath)) ?>"><i class="bi bi-house-door me-1"></i><?= escape($layoutSite['site_name']) ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= escape($pageTitle) ?></li>
                    </ol>
                </nav>
            </div>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="alert alert-<?= escape($noticeType) ?>" role="<?= $noticeType === 'danger' ? 'alert' : 'status' ?>"><?= escape((string) ($notice['message'] ?? '')) ?></div>
        <?php endif; ?>