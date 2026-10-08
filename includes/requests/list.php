<?php
require_once __DIR__ . '/workflow.php';

$isAdmin = $user['role'] === 'admin';
$filters = [];
foreach (['search', 'status', 'type', 'client', 'from', 'to'] as $key) {
    $filters[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(trim($_GET[$key]), 0, 200) : '';
}
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$result = request_list($user, $filters, $page);
$pageTitle = $isAdmin ? 'Request management' : 'My requests';
$types = request_types(false);

// Counts for the status tabs (admin: every submitted request, client: only their own).
$tabCounts = [];
try {
    $scope = $isAdmin ? "status <> 'draft'" : 'user_id = ' . (int) $user['id'];
    $tabCounts = array_map('intval', database()->query("SELECT status, COUNT(*) FROM requests WHERE $scope GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR));
} catch (Throwable $exception) {
    error_log((string) $exception);
}
$allCount = array_sum($tabCounts);

// Build a link that keeps the current filters and overrides some of them.
$link = static function (array $override) use ($filters): string {
    $params = array_filter($override + $filters, static fn ($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($params);
};

$moreOpen = $filters['from'] !== '' || $filters['to'] !== '' || ($isAdmin && $filters['client'] !== '');
$hasFilters = $filters['search'] !== '' || $filters['type'] !== '' || $filters['status'] !== '' || $moreOpen;
$firstPage = max(1, $result['page'] - 2);
$lastPage = min($result['pages'], $result['page'] + 2);

require __DIR__ . '/../header.php';
?>
<style>
.rq-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
.rq-head h1 { font-size: 1.6rem; font-weight: 700; color: #0a1f44; margin: 0; }
.rq-head p { margin: .15rem 0 0; color: #6b7280; }
.rq-tabs { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .4rem; margin-bottom: 1rem; scrollbar-width: thin; }
.rq-tab { flex: none; display: inline-flex; align-items: center; gap: .5rem; padding: .4rem .85rem; border: 1px solid #e5e7eb; border-radius: 99px; background: #fff; color: #374151; font-size: .85rem; font-weight: 500; text-decoration: none; white-space: nowrap; transition: background-color .15s, border-color .15s; }
.rq-tab:hover, .rq-tab:focus-visible { background: #f1f4fa; border-color: #c7d2e6; color: #0a1f44; }
.rq-tab b { padding: 0 .45rem; border-radius: 99px; background: #eef0f4; color: #374151; font-size: .75rem; }
.rq-tab.active { background: #0a1f44; border-color: #0a1f44; color: #fff; }
.rq-tab.active b { background: rgba(255, 255, 255, .18); color: #fff; }
.rq-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .875rem; box-shadow: 0 1px 2px rgba(10, 31, 68, .05); overflow: hidden; }
.rq-filters { padding: 1rem 1.25rem; border-bottom: 1px solid #eef0f4; background: #fcfcfd; }
.rq-search { position: relative; }
.rq-search i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; }
.rq-search input { padding-left: 2.4rem; }
.rq-table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; background: #f9fafb; border-bottom: 1px solid #eef0f4; white-space: nowrap; padding-top: .8rem; padding-bottom: .8rem; }
.rq-table tbody td { padding-top: .85rem; padding-bottom: .85rem; border-color: #f1f2f5; }
.rq-ref { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .82rem; color: #14366e; text-decoration: none; font-weight: 600; }
.rq-ref:hover { text-decoration: underline; }
.rq-title { font-weight: 600; color: #111827; }
.rq-avatar { flex: none; width: 2rem; height: 2rem; display: grid; place-items: center; border-radius: 50%; background: #e3e9f5; color: #0a1f44; font-size: .75rem; font-weight: 700; }
.rq-empty { padding: 3.5rem 1rem; text-align: center; color: #6b7280; }
.rq-empty i { font-size: 2.5rem; color: #c3c9d4; }
.rq-pager { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: .9rem 1.25rem; border-top: 1px solid #eef0f4; background: #fcfcfd; }
.rq-pager .pagination { margin: 0; }
.rq-pager .page-link { color: #14366e; }
.rq-pager .page-item.active .page-link { background: #0a1f44; border-color: #0a1f44; color: #fff; }
@media (prefers-reduced-motion: reduce) { .rq-tab { transition: none; } }
</style>

<div class="rq-head">
    <div>
        <h1><?= escape($pageTitle) ?></h1>
        <p><?= $isAdmin ? 'Review, verify, and track every ELIA transaction.' : 'Follow the status of each request you have filed.' ?></p>
    </div>
    <?php if (!$isAdmin): ?>
        <a class="btn btn-primary" href="<?= escape(url('client/requests/create.php')) ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create request</a>
    <?php endif; ?>
</div>

<nav class="rq-tabs" aria-label="Filter by status">
    <a class="rq-tab <?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= escape($link(['status' => '', 'page' => ''])) ?>" <?= $filters['status'] === '' ? 'aria-current="true"' : '' ?>>All <b><?= $allCount ?></b></a>
    <?php foreach (request_statuses() as $status):
        $count = $tabCounts[$status] ?? 0;
        if ($count === 0 && $filters['status'] !== $status) { continue; }
        if ($isAdmin && $status === 'draft') { continue; } ?>
        <a class="rq-tab <?= $filters['status'] === $status ? 'active' : '' ?>" href="<?= escape($link(['status' => $status, 'page' => ''])) ?>" <?= $filters['status'] === $status ? 'aria-current="true"' : '' ?>><?= escape(request_label($status)) ?> <b><?= $count ?></b></a>
    <?php endforeach; ?>
</nav>

<section class="rq-card" aria-label="Requests">
    <form method="get" class="rq-filters">
        <input type="hidden" name="status" value="<?= escape($filters['status']) ?>">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label for="search" class="visually-hidden">Search title or reference</label>
                <div class="rq-search"><i class="bi bi-search" aria-hidden="true"></i><input id="search" class="form-control" name="search" maxlength="200" placeholder="Search title or reference number" value="<?= escape($filters['search']) ?>"></div>
            </div>
            <div class="col-12 col-md-4">
                <label for="type" class="visually-hidden">Request type</label>
                <select id="type" class="form-select" name="type">
                    <option value="">All request types</option>
                    <?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= $filters['type'] === (string) $type['id'] ? 'selected' : '' ?>><?= escape($type['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Apply</button>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rq-more" aria-expanded="<?= $moreOpen ? 'true' : 'false' ?>" aria-controls="rq-more" title="More filters"><i class="bi bi-sliders" aria-hidden="true"></i><span class="visually-hidden">More filters</span></button>
                <?php if ($hasFilters): ?><a class="btn btn-outline-secondary" href="<?= escape(url($user['role'] . '/requests/index.php')) ?>" title="Clear filters"><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Clear filters</span></a><?php endif; ?>
            </div>
        </div>
        <div class="collapse <?= $moreOpen ? 'show' : '' ?>" id="rq-more">
            <div class="row g-2 pt-3">
                <?php if ($isAdmin): ?>
                    <div class="col-md-4"><label for="client" class="form-label small text-secondary mb-1">Client name or email</label><input id="client" class="form-control" name="client" maxlength="200" value="<?= escape($filters['client']) ?>"></div>
                <?php endif; ?>
                <div class="col-md-4"><label for="from" class="form-label small text-secondary mb-1">Submitted from</label><input id="from" type="date" class="form-control" name="from" value="<?= escape($filters['from']) ?>"></div>
                <div class="col-md-4"><label for="to" class="form-label small text-secondary mb-1">Submitted through</label><input id="to" type="date" class="form-control" name="to" value="<?= escape($filters['to']) ?>"></div>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table rq-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Reference</th>
                    <?php if ($isAdmin): ?><th>Client</th><?php endif; ?>
                    <th>Request</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th class="pe-4 text-end"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $row):
                $viewLink = url($user['role'] . '/requests/view.php?id=' . $row['id']);
                $who = (string) ($row['client_name'] ?? ''); ?>
                <tr>
                    <td class="ps-4"><a class="rq-ref" href="<?= escape($viewLink) ?>"><?= escape($row['reference_no'] ?? 'Draft #' . $row['id']) ?></a></td>
                    <?php if ($isAdmin): ?>
                        <td><div class="d-flex align-items-center gap-2"><span class="rq-avatar" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr($who !== '' ? $who : '?', 0, 1))) ?></span><span><?= escape($who) ?></span></div></td>
                    <?php endif; ?>
                    <td><div class="rq-title"><?= escape($row['title'] ?: 'Untitled draft') ?></div><div class="small text-secondary"><?= escape($row['type_name']) ?></div></td>
                    <td class="text-secondary small"><?= $row['submitted_at'] ? escape(date('M j, Y', strtotime((string) $row['submitted_at']))) : 'Not submitted' ?></td>
                    <td><?= request_badge($row['status']) ?></td>
                    <td class="pe-4 text-end"><a class="btn btn-sm btn-outline-primary" aria-label="View <?= escape($row['title'] ?: 'draft request') ?>" href="<?= escape($viewLink) ?>">View <i class="bi bi-arrow-right" aria-hidden="true"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$result['rows']): ?>
        <div class="rq-empty">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            <p class="fw-semibold text-dark mt-2 mb-1"><?= $hasFilters ? 'No requests match these filters' : 'No requests yet' ?></p>
            <p class="small mb-3"><?= $hasFilters ? 'Try a different search or clear the filters.' : ($isAdmin ? 'Submitted requests will appear here.' : 'Create your first request to get started.') ?></p>
            <?php if ($hasFilters): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= escape(url($user['role'] . '/requests/index.php')) ?>">Clear filters</a>
            <?php elseif (!$isAdmin): ?>
                <a class="btn btn-primary btn-sm" href="<?= escape(url('client/requests/create.php')) ?>">Create request</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($result['rows']): ?>
        <div class="rq-pager">
            <span class="small text-secondary">Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?> &middot; <?= (int) $result['total'] ?> request<?= (int) $result['total'] === 1 ? '' : 's' ?></span>
            <?php if ($result['pages'] > 1): ?>
                <nav aria-label="Request pages">
                    <ul class="pagination pagination-sm">
                        <li class="page-item <?= $result['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= escape($link(['page' => (string) max(1, $result['page'] - 1)])) ?>" aria-label="Previous page">&laquo;</a></li>
                        <?php for ($p = $firstPage; $p <= $lastPage; $p++): ?>
                            <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= escape($link(['page' => (string) $p])) ?>" <?= $p === $result['page'] ? 'aria-current="page"' : '' ?>><?= $p ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $result['page'] >= $result['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= escape($link(['page' => (string) min($result['pages'], $result['page'] + 1)])) ?>" aria-label="Next page">&raquo;</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../footer.php'; ?>