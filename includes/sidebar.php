<?php
require_once __DIR__ . '/notifications.php';

$role    = $layoutUser['role'];
$isAdmin = $role === 'admin';
$unread  = unread_notifications((int) $layoutUser['id']);
$current = $pageTitle ?? '';

// [label, icon, url, pageTitle na magha-highlight, badge]
$sections = [
    'Overview' => [
        ['Dashboard', 'bi-speedometer2', dashboard_path($role), 'Dashboard'],
        [$isAdmin ? 'Request management' : 'My requests', 'bi-folder2-open', $role . '/requests/index.php', 'Requests'],
        ['Travel monitoring', 'bi-airplane', $role . '/monitoring.php', 'Travel monitoring'],
    ],
];

if ($isAdmin) {
    $sections['Administration'] = [
        ['Partnerships & agreements', 'bi-diagram-3', 'admin/partnerships/index.php', 'Partnerships'],
        ['Request types & checklists', 'bi-ui-checks', 'admin/request-types.php', 'Request types'],
        ['User management', 'bi-people', 'admin/users/index.php', 'User management'],
    ];
} else {
    $sections['Actions'] = [
        ['Create request', 'bi-plus-circle', 'client/requests/create.php', 'Create request'],
    ];
}

$sections['Account'] = [
    ['Notifications', 'bi-bell', 'notifications.php', 'Notifications', $unread],
];

$initial = strtoupper(mb_substr($layoutUser['full_name'], 0, 1));
?>
<aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebar-title">
    <div class="offcanvas-header border-bottom">
        <h2 class="offcanvas-title h6 mb-0" id="sidebar-title">ELIA navigation</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close navigation"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <!-- Brand -->
        <div class="sidebar-brand">
            <span class="brand-mark"><i class="bi bi-globe-asia-australia"></i></span>
            <div>
                <div class="fw-bold lh-1">ELIA</div>
                <div class="small text-secondary"><?= escape(ucfirst($role)) ?> workspace</div>
            </div>
        </div>

        <!-- Nav -->
        <nav aria-label="Main navigation" class="sidebar-nav flex-grow-1">
            <?php foreach ($sections as $heading => $items): ?>
                <p class="nav-heading"><?= escape($heading) ?></p>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($items as $item):
                        [$label, $icon, $path, $match] = $item;
                        $badge  = $item[4] ?? 0;
                        $active = $current === $match; ?>
                        <li>
                            <a class="nav-item-link <?= $active ? 'active' : '' ?>"
                               <?= $active ? 'aria-current="page"' : '' ?>
                               href="<?= escape(url($path)) ?>">
                                <i class="bi <?= $icon ?>"></i>
                                <span class="flex-grow-1"><?= escape($label) ?></span>
                                <?php if ($badge): ?>
                                    <span class="badge rounded-pill text-bg-primary"><?= (int) $badge ?><span class="visually-hidden"> unread</span></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <!-- User footer -->
        <div class="sidebar-user">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="avatar"><?= escape($initial) ?></span>
                <div class="min-w-0">
                    <div class="fw-semibold small text-truncate"><?= escape($layoutUser['full_name']) ?></div>
                    <div class="text-secondary small text-truncate"><?= escape($layoutUser['email']) ?></div>
                </div>
            </div>
            <form action="<?= escape(url('actions/logout.php')) ?>" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-outline-secondary btn-sm w-100" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i> Sign out
                </button>
            </form>
        </div>
    </div>
</aside>
<style>
    .sidebar { --bs-offcanvas-width: 272px; border-right: 1px solid var(--bs-border-color-translucent); background: var(--bs-body-bg); }

.sidebar-brand { display: flex; align-items: center; gap: .75rem; padding: 1.25rem 1.25rem 1rem; }
.brand-mark {
  width: 40px; height: 40px; display: grid; place-items: center; border-radius: 10px;
  background: linear-gradient(135deg, var(--bs-primary), #0b3d2e); color: #fff; font-size: 1.2rem;
}

.sidebar-nav { padding: .5rem .75rem; overflow-y: auto; }
.nav-heading {
  margin: .75rem .5rem .4rem; font-size: .7rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .08em; color: var(--bs-secondary-color);
}
.nav-item-link {
  display: flex; align-items: center; gap: .75rem;
  padding: .55rem .75rem; margin-bottom: 2px; border-radius: .6rem;
  color: var(--bs-body-color); text-decoration: none; font-size: .925rem;
  transition: background .15s, color .15s;
}
.nav-item-link i { font-size: 1.05rem; color: var(--bs-secondary-color); }
.nav-item-link:hover { background: var(--bs-tertiary-bg); }
.nav-item-link.active { background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); font-weight: 600; }
.nav-item-link.active i { color: var(--bs-primary); }

.sidebar-user { padding: 1rem 1.25rem; border-top: 1px solid var(--bs-border-color-translucent); }
.avatar {
  flex: 0 0 36px; height: 36px; display: grid; place-items: center; border-radius: 50%;
  background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); font-weight: 700;
}
.min-w-0 { min-width: 0; }
</style>