<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/announcements.php';
require_once __DIR__ . '/../includes/resources.php';
$user = requireClient();
$pageTitle = 'ELIA information';

$announcements = announcement_public_list(5);
$resources = resource_published_list();
$categories = announcement_categories();

// Active requirements of every request type, grouped by type and stage.
$rows = database()->query(
    "SELECT t.id AS type_id, t.name AS type_name, t.description AS type_description, q.stage, q.requirement_name, q.description, q.is_required
     FROM request_types t JOIN requirement_templates q ON q.request_type_id = t.id
     WHERE t.status = 'active' AND q.status = 'active'
     ORDER BY t.id, FIELD(q.stage, 'submission', 'pre_departure', 'post_travel'), q.sort_order, q.id"
)->fetchAll();
$types = [];
foreach ($rows as $row) {
    $types[$row['type_id']]['name'] = $row['type_name'];
    $types[$row['type_id']]['description'] = $row['type_description'];
    $types[$row['type_id']]['stages'][$row['stage']][] = $row;
}
$stageLabels = ['submission' => 'Submission requirements', 'pre_departure' => 'Pre-departure requirements', 'post_travel' => 'Post-travel requirements'];
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4">
    <p class="eyebrow">Client guide</p>
    <h1 class="h3">ELIA information</h1>
    <p class="text-secondary mb-0">Announcements, the documents required for each transaction, and downloadable forms.</p>
</div>

<section class="card card-body mb-4" aria-labelledby="ann-heading">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0" id="ann-heading">Announcements</h2>
        <a class="small" href="<?= escape(url('announcements.php')) ?>">View all</a>
    </div>
    <?php foreach ($announcements as $item): ?>
        <div class="py-2 border-top">
            <p class="small text-secondary mb-0"><?= escape($categories[$item['category']] ?? '') ?> · <?= escape(announcement_date($item['published_at'])) ?></p>
            <a class="fw-semibold" href="<?= escape(url('announcements.php?id=' . (int) $item['id'])) ?>"><?= escape($item['title']) ?></a>
            <p class="mb-0 small"><?= escape(announcement_excerpt($item)) ?></p>
        </div>
    <?php endforeach; ?>
    <?php if (!$announcements): ?><p class="text-secondary mb-0">No announcements at the moment.</p><?php endif; ?>
</section>

<section class="card card-body mb-4" aria-labelledby="req-heading">
    <h2 class="h5" id="req-heading">Requirements per transaction</h2>
    <?php foreach ($types as $type): ?>
        <details class="border-top py-2">
            <summary class="fw-semibold"><?= escape($type['name']) ?></summary>
            <?php if ((string) $type['description'] !== ''): ?><p class="small text-secondary mt-2 mb-2"><?= escape($type['description']) ?></p><?php endif; ?>
            <?php foreach ($type['stages'] as $stage => $items): ?>
                <h3 class="h6 mt-3"><?= escape($stageLabels[$stage] ?? $stage) ?></h3>
                <ul class="mb-0">
                    <?php foreach ($items as $item): ?>
                        <li><?= escape($item['requirement_name']) ?><?= $item['is_required'] ? '' : ' <span class="text-secondary">(if applicable)</span>' ?>
                            <?php if ((string) $item['description'] !== ''): ?><br><span class="small text-secondary"><?= escape($item['description']) ?></span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </details>
    <?php endforeach; ?>
    <?php if (!$types): ?><p class="text-secondary mb-0">The ELIA Office has not published its requirement checklists yet.</p><?php endif; ?>
</section>

<section class="card card-body" aria-labelledby="res-heading">
    <h2 class="h5" id="res-heading">Forms &amp; downloads</h2>
    <?php foreach ($resources as $item): ?>
        <div class="py-2 border-top d-flex justify-content-between gap-3">
            <div><strong><?= escape($item['title']) ?></strong><?php if ($item['description'] !== ''): ?><br><span class="small text-secondary"><?= escape($item['description']) ?></span><?php endif; ?></div>
            <a class="btn btn-sm btn-outline-primary align-self-center text-nowrap" href="<?= escape(url('actions/resources/download.php?id=' . (int) $item['id'])) ?>">Download <?= escape(resource_extension($item)) ?></a>
        </div>
    <?php endforeach; ?>
    <?php if (!$resources): ?><p class="text-secondary mb-0">No downloadable files yet.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
