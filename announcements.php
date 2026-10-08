<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/announcements.php';

$categories = announcement_categories();
$single = null;
if (isset($_GET['id'])) {
    $single = is_string($_GET["id"]) ? announcement_public_find((int) $_GET["id"]) : null;
    if (!$single) { http_response_code(404); }
}
$perPage = 10;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$total = announcement_public_count();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$items = $single ? [] : announcement_public_list($perPage, ($page - 1) * $perPage);
$pageTitle = $single ? $single['title'] : 'Announcements';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto" style="max-width: 52rem">
<?php if (isset($_GET['id']) && !$single): ?>
    <div class="alert alert-warning">That announcement is not available.</div>
    <a href="<?= escape(url('announcements.php')) ?>">Back to all announcements</a>
<?php elseif ($single): ?>
    <p class="eyebrow"><?= escape($categories[$single['category']] ?? '') ?> · <?= escape(announcement_date($single['published_at'])) ?></p>
    <h1 class="h3"><?= escape($single['title']) ?></h1>
    <div class="mt-3"><?= nl2br(escape($single['body'])) ?></div>
    <p class="mt-4"><a href="<?= escape(url('announcements.php')) ?>">&larr; All announcements</a></p>
<?php else: ?>
    <p class="eyebrow">External Linkages and International Affairs</p>
    <h1 class="h3 mb-4">Announcements</h1>
    <?php foreach ($items as $item): ?>
        <article class="card card-body mb-3">
            <p class="small text-secondary mb-1"><?= escape($categories[$item['category']] ?? '') ?> · <?= escape(announcement_date($item['published_at'])) ?></p>
            <h2 class="h5"><a href="<?= escape(url('announcements.php?id=' . (int) $item['id'])) ?>"><?= escape($item['title']) ?></a></h2>
            <p class="mb-0"><?= escape(announcement_excerpt($item, 240)) ?></p>
        </article>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="text-secondary">No announcements have been published yet.</p><?php endif; ?>
    <?php if ($pages > 1): ?>
        <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Pages">
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <div class="d-flex gap-2">
                <?php if ($page > 1): ?><a class="btn btn-outline-primary" href="<?= escape(url('announcements.php?page=' . ($page - 1))) ?>">Previous</a><?php endif; ?>
                <?php if ($page < $pages): ?><a class="btn btn-outline-primary" href="<?= escape(url('announcements.php?page=' . ($page + 1))) ?>">Next</a><?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
