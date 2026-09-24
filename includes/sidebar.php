<aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebar-title">
    <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="sidebar-title">ELIA navigation</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close navigation"></button></div>
    <div class="offcanvas-body d-flex flex-column p-3">
        <p class="eyebrow mb-3"><?= escape(ucfirst($layoutUser['role'])) ?> workspace</p>
        <nav aria-label="Main navigation" class="d-grid gap-2">
            <a class="nav-link <?= ($pageTitle ?? '') === 'Dashboard' ? 'dashboard-link' : '' ?> rounded px-3 py-2" <?= ($pageTitle ?? '') === 'Dashboard' ? 'aria-current="page"' : '' ?> href="<?= escape(url(dashboard_path($layoutUser['role']))) ?>">Dashboard</a>
            <a class="nav-link px-3 py-2" href="<?= escape(url($layoutUser['role'] . '/requests/index.php')) ?>"><?= $layoutUser['role'] === 'admin' ? 'Request management' : 'My requests' ?></a>
            <?php if ($layoutUser['role'] === 'client'): ?><a class="nav-link px-3 py-2" href="<?= escape(url('client/requests/create.php')) ?>">Create request</a><?php else: ?><a class="nav-link px-3 py-2" href="<?= escape(url('admin/request-types.php')) ?>">Request types &amp; checklists</a><?php endif; ?>
            <?php require_once __DIR__ . '/notifications.php'; $unread = unread_notifications((int) $layoutUser['id']); ?>
            <a class="nav-link px-3 py-2" href="<?= escape(url('notifications.php')) ?>">Notifications <?php if ($unread): ?><span class="badge text-bg-primary"><?= $unread ?><span class="visually-hidden"> unread</span></span><?php endif; ?></a>
        </nav>
        <div class="mt-auto pt-5">
            <p class="small text-secondary mb-3"><?= escape($layoutUser['full_name']) ?><br><?= escape($layoutUser['email']) ?></p>
            <form action="<?= escape(url('actions/logout.php')) ?>" method="post"><?= csrf_field() ?><button class="btn btn-outline-secondary w-100" type="submit">Sign out</button></form>
        </div>
    </div>
</aside>
