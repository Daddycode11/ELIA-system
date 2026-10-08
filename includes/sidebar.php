<?php
require_once __DIR__ . '/notifications.php';

$sxRole   = $layoutUser['role'];
$sxUnread = unread_notifications((int) $layoutUser['id']);
$sxScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

$sxInitials = '';
foreach (array_slice(preg_split('/\s+/', trim((string) $layoutUser['full_name'])) ?: [], 0, 2) as $sxPart) {
    $sxInitials .= mb_strtoupper(mb_substr($sxPart, 0, 1));
}
$sxInitials = $sxInitials !== '' ? $sxInitials : 'U';

/**
 * Menu definition: [label, path, icon, path fragments that mark it active, fragments that exclude it].
 * Active state follows the script path, so sub-pages (form, view, create...) keep their menu highlighted.
 */
$sxSections = $sxRole === 'admin' ? [
    ['Overview', [
        ['Dashboard', 'admin/dashboard.php', 'bi-speedometer2', ['/dashboard.php'], []],
    ]],
    ['Transactions', [
        ['Request management', 'admin/requests/index.php', 'bi-folder2-open', ['/requests/'], []],
        ['Travel monitoring', 'admin/monitoring.php', 'bi-airplane', ['/monitoring.php'], []],
        ['Request types & checklists', 'admin/request-types.php', 'bi-card-checklist', ['/request-types'], []],
    ]],
    ['Linkages', [
        ['Partnerships & agreements', 'admin/partnerships/index.php', 'bi-globe2', ['/partnerships/'], []],
    ]],
    ['Content', [
        ['Announcements', 'admin/announcements/index.php', 'bi-megaphone', ['/announcements/'], []],
        ['Resources & forms', 'admin/resources/index.php', 'bi-file-earmark-text', ['/resources/'], []],
    ]],
    ['Administration', [
        ['Reports', 'admin/reports/index.php', 'bi-graph-up', ['/reports/'], []],
        ['User management', 'admin/users/index.php', 'bi-people', ['/users/'], []],
    ]],
] : [
    ['Overview', [
        ['Dashboard', 'client/dashboard.php', 'bi-speedometer2', ['/dashboard.php'], []],
    ]],
    ['My work', [
        ['My requests', 'client/requests/index.php', 'bi-folder2-open', ['/requests/'], ['create.php']],
        ['Travel monitoring', 'client/monitoring.php', 'bi-airplane', ['/monitoring.php'], []],
    ]],
    ['Help', [
        ['ELIA information', 'client/information.php', 'bi-info-circle', ['/information.php'], []],
    ]],
];
$sxSections[] = ['Account', [
    ['Notifications', 'notifications.php', 'bi-bell', ['/notifications.php'], [], $sxUnread],
]];

$sxIsActive = static function (array $match, array $not) use ($sxScript): bool {
    foreach ($not as $fragment) { if (str_contains($sxScript, $fragment)) { return false; } }
    foreach ($match as $fragment) { if (str_contains($sxScript, $fragment)) { return true; } }
    return false;
};
?>
<style>
aside.sidebar { background: #fff; border-right: 1px solid #e5e7eb; }
.sx-eyebrow { display: flex; align-items: center; gap: .5rem; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #8a6d12; margin: 0 0 1rem; }
.sx-eyebrow::before { content: ""; width: 1.4rem; height: 2px; background: #c9a227; }
.sx-cta { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: .65rem 1rem; margin-bottom: 1.1rem; border-radius: .6rem; background: #c9a227; color: #071a33; font-weight: 600; text-decoration: none; transition: background-color .15s; }
.sx-cta:hover, .sx-cta:focus-visible { background: #d9ab3f; color: #071a33; }
.sx-heading { font-size: .68rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #8b93a1; margin: 1.1rem .75rem .4rem; }
.sx-heading:first-child { margin-top: 0; }
.sx-link { position: relative; display: flex; align-items: center; gap: .7rem; padding: .55rem .75rem; margin-bottom: .15rem; border-radius: .6rem; color: #374151; font-size: .92rem; font-weight: 500; text-decoration: none; transition: background-color .15s, color .15s; }
.sx-link i { flex: none; width: 1.25rem; text-align: center; font-size: 1.05rem; color: #6b7280; }
.sx-link:hover, .sx-link:focus-visible { background: #f1f4fa; color: #0b2545; }
.sx-link:hover i { color: #14366e; }
.sx-link.active { background: #0b2545; color: #fff; box-shadow: inset 3px 0 0 #c9a227; }
.sx-link.active i { color: #e9d8a6; }
.sx-badge { margin-left: auto; min-width: 1.4rem; padding: .05rem .45rem; border-radius: 99px; background: #ef4444; color: #fff; font-size: .72rem; font-weight: 700; text-align: center; }
.sx-user { display: flex; align-items: center; gap: .7rem; padding: .75rem; margin-bottom: .75rem; border-radius: .75rem; background: #f6f7fb; border: 1px solid #eceef4; }
.sx-avatar { flex: none; width: 2.4rem; height: 2.4rem; display: grid; place-items: center; border-radius: 50%; background: #0b2545; color: #e9d8a6; font-weight: 700; font-size: .85rem; }
.sx-user-text { min-width: 0; line-height: 1.2; }
.sx-user-text b { display: block; font-size: .88rem; color: #0b2545; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sx-user-text small { display: block; color: #6b7280; font-size: .75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
@media (prefers-reduced-motion: reduce) { .sx-link, .sx-cta { transition: none; } }
</style>

<aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebar-title">
    <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="sidebar-title">ELIA navigation</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close navigation"></button></div>
    <div class="offcanvas-body d-flex flex-column p-3">
        <p class="sx-eyebrow"><?= escape(ucfirst($sxRole)) ?> workspace</p>

        <?php if ($sxRole === 'client'): ?>
            <a class="sx-cta" href="<?= escape(url('client/requests/create.php')) ?>"><i class="bi bi-plus-lg"></i>New request</a>
        <?php endif; ?>

        <nav aria-label="Main navigation">
            <?php foreach ($sxSections as [$heading, $items]): ?>
                <div class="sx-heading"><?= escape($heading) ?></div>
                <?php foreach ($items as $item):
                    [$label, $path, $icon, $match, $not] = $item;
                    $active = $sxIsActive($match, $not);
                    $badge = $item[5] ?? 0; ?>
                    <a class="sx-link<?= $active ? ' active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?> href="<?= escape(url($path)) ?>">
                        <i class="bi <?= escape($icon) ?>" aria-hidden="true"></i>
                        <span><?= escape($label) ?></span>
                        <?php if ($badge): ?><span class="sx-badge"><?= (int) $badge ?><span class="visually-hidden"> unread</span></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="mt-auto pt-4">
            <div class="sx-user">
                <span class="sx-avatar" aria-hidden="true"><?= escape($sxInitials) ?></span>
                <span class="sx-user-text"><b><?= escape($layoutUser['full_name']) ?></b><small><?= escape($layoutUser['email']) ?></small></span>
            </div>
            <form action="<?= escape(url('actions/logout.php')) ?>" method="post"><?= csrf_field() ?><button class="btn btn-outline-secondary w-100" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Sign out</button></form>
        </div>
    </div>
</aside>