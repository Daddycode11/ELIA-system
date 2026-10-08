<?php
/**
 * Shared dashboard (admin + client). Included by admin/dashboard.php and client/dashboard.php
 * between header.php and footer.php. Charts are inline SVG, so no JS library is needed.
 */
$pdo     = database();
$user    = $user ?? current_user();
$role    = $user['role'];
$isAdmin = $role === 'admin';
$uid     = (int) $user['id'];

$requestsUrl = url($role . '/requests/index.php');
$createUrl   = url('client/requests/create.php');
$viewUrl     = static fn (int $id): string => url($role . '/requests/view.php?id=' . $id);

// Admin sees every submitted request, a client sees only their own. $uid is cast to int, so this is safe.
$scope = $isAdmin ? " AND r.status <> 'draft'" : " AND r.user_id = $uid";

$statusMeta = [
    'draft'        => ['Draft',        '#94a3b8'],
    'submitted'    => ['Submitted',    '#f59e0b'],
    'resubmitted'  => ['Resubmitted',  '#f97316'],
    'under_review' => ['Under review', '#0ea5e9'],
    'for_revision' => ['For revision', '#ef4444'],
    'approved'     => ['Approved',     '#3b82f6'],
    'in_progress'  => ['In progress',  '#6366f1'],
    'post_travel'  => ['Post-travel',  '#8b5cf6'],
    'completed'    => ['Completed',    '#16a34a'],
    'rejected'     => ['Rejected',     '#991b1b'],
    'cancelled'    => ['Cancelled',    '#64748b'],
];
$meta = static fn (string $s): array => $statusMeta[$s] ?? [ucwords(str_replace('_', ' ', $s)), '#64748b'];

/* ---------- Status counts ---------- */
$counts = [];
try {
    $counts = array_map('intval', $pdo->query("SELECT r.status, COUNT(*) FROM requests r WHERE 1=1 $scope GROUP BY r.status")->fetchAll(PDO::FETCH_KEY_PAIR));
} catch (Throwable $e) { error_log((string) $e); }

$total      = array_sum($counts);
$completed  = $counts['completed'] ?? 0;
$inReview   = ($counts['submitted'] ?? 0) + ($counts['resubmitted'] ?? 0) + ($counts['under_review'] ?? 0);
$forRevise  = $counts['for_revision'] ?? 0;
$activeTrip = ($counts['approved'] ?? 0) + ($counts['in_progress'] ?? 0) + ($counts['post_travel'] ?? 0);
$rate       = $total > 0 ? (int) round($completed / $total * 100) : 0;

/* ---------- Extra KPIs ---------- */
$avgDays = null; $docsToVerify = 0; $docsToFix = 0; $partners = ['active' => 0, 'expiring' => 0];
$latest = "d.version = (SELECT MAX(d2.version) FROM request_documents d2 WHERE d2.request_requirement_id = d.request_requirement_id)";
try {
    $v = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, r.submitted_at, r.approved_at)) / 24 FROM requests r WHERE r.submitted_at IS NOT NULL AND r.approved_at IS NOT NULL $scope")->fetchColumn();
    $avgDays = $v !== null && $v !== false ? round((float) $v, 1) : null;

    $open = "r.status NOT IN ('draft','cancelled','rejected','completed')";
    if ($isAdmin) {
        $docsToVerify = (int) $pdo->query("SELECT COUNT(*) FROM request_documents d JOIN requests r ON r.id = d.request_id WHERE d.status = 'pending' AND $latest AND $open")->fetchColumn();
    }
    $docsToFix = (int) $pdo->query("SELECT COUNT(*) FROM request_documents d JOIN requests r ON r.id = d.request_id WHERE d.status IN ('for_revision','rejected') AND $latest AND $open $scope")->fetchColumn();
} catch (Throwable $e) { error_log((string) $e); }

if ($isAdmin) {
    try {
        $row = $pdo->query("SELECT COUNT(*) AS active, COALESCE(SUM(a.end_date IS NOT NULL AND a.end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)), 0) AS expiring
            FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id
            WHERE a.status = 'signed' AND a.is_archived = 0 AND p.is_archived = 0 AND a.start_date <= CURDATE() AND (a.end_date IS NULL OR a.end_date >= CURDATE())")->fetch();
        if ($row) { $partners = array_map('intval', $row); }
    } catch (Throwable $e) { error_log((string) $e); }
}

$kpis = $isAdmin ? [
    ['Total requests',      $total,        'bi-folder2-open',     '#0a1f44', 'Excludes drafts'],
    ['Awaiting review',     $inReview,     'bi-hourglass-split',  '#c9a227', 'Submitted or under review'],
    ['Active travel',       $activeTrip,   'bi-airplane',         '#6366f1', 'Approved to post-travel'],
    ['Completed',           $completed,    'bi-check2-circle',    '#16a34a', $rate . '% completion rate'],
    ['Documents to verify', $docsToVerify, 'bi-file-earmark-check', '#0ea5e9', 'Latest uploads pending'],
    ['Avg. approval time',  $avgDays === null ? '—' : $avgDays . ' d', 'bi-stopwatch', '#8b5cf6', 'Submission to approval'],
] : [
    ['My requests',  $total,      'bi-folder2-open',    '#0a1f44', 'All submitted and draft'],
    ['In review',    $inReview,   'bi-hourglass-split', '#c9a227', 'With the ELIA Office'],
    ['Needs action', $forRevise + $docsToFix, 'bi-exclamation-circle', '#ef4444', 'Revisions or documents to replace'],
    ['Completed',    $completed,  'bi-check2-circle',   '#16a34a', $rate . '% completion rate'],
];

/* ---------- Trend: submissions in the last 6 months ---------- */
$months = [];
for ($i = 5; $i >= 0; $i--) { $months[date('Y-m', strtotime("first day of -$i month"))] = 0; }
try {
    $first = array_key_first($months) . '-01';
    $rows = $pdo->query("SELECT DATE_FORMAT(r.submitted_at, '%Y-%m') AS ym, COUNT(*) FROM requests r WHERE r.submitted_at >= '$first' $scope GROUP BY ym")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($rows as $ym => $c) { if (isset($months[$ym])) { $months[$ym] = (int) $c; } }
} catch (Throwable $e) { error_log((string) $e); }
$trendMax = max(4, max($months));

/* ---------- Breakdowns ---------- */
$byType = []; $byCountry = [];
try {
    $byType = $pdo->query("SELECT t.name AS label, COUNT(*) AS c FROM requests r JOIN request_types t ON t.id = r.request_type_id WHERE 1=1 $scope GROUP BY t.id, t.name ORDER BY c DESC LIMIT 6")->fetchAll();
    $byCountry = $pdo->query("SELECT r.country AS label, COUNT(*) AS c FROM requests r WHERE r.country <> '' $scope GROUP BY r.country ORDER BY c DESC LIMIT 6")->fetchAll();
} catch (Throwable $e) { error_log((string) $e); }

/* ---------- Monitoring (pre-departure / post-travel checklists) ---------- */
$monitoring = [];
try {
    $monitoring = $pdo->query("SELECT r.id, r.reference_no, r.title, r.status, r.country, r.start_date, r.end_date,
            COALESCE(SUM(rr.stage = 'pre_departure'  AND rr.is_required = 1), 0) AS pre_total,
            COALESCE(SUM(rr.stage = 'pre_departure'  AND rr.is_required = 1 AND d.status = 'verified'), 0) AS pre_done,
            COALESCE(SUM(rr.stage = 'post_travel'    AND rr.is_required = 1), 0) AS post_total,
            COALESCE(SUM(rr.stage = 'post_travel'    AND rr.is_required = 1 AND d.status = 'verified'), 0) AS post_done
        FROM requests r
        LEFT JOIN request_requirements rr ON rr.request_id = r.id AND rr.stage IN ('pre_departure','post_travel')
        LEFT JOIN request_documents d ON d.request_requirement_id = rr.id
             AND d.version = (SELECT MAX(d2.version) FROM request_documents d2 WHERE d2.request_requirement_id = rr.id)
        WHERE r.status IN ('approved','in_progress','post_travel') $scope
        GROUP BY r.id, r.reference_no, r.title, r.status, r.country, r.start_date, r.end_date
        ORDER BY r.start_date IS NULL, r.start_date ASC
        LIMIT 6")->fetchAll();
} catch (Throwable $e) { error_log((string) $e); }

/* ---------- Attention list + recent requests ---------- */
$attention = [];
try {
    if ($isAdmin) {
        $attention = $pdo->query("SELECT r.id, r.title, r.reference_no, r.status, u.full_name AS who, DATEDIFF(NOW(), r.submitted_at) AS waiting
            FROM requests r LEFT JOIN users u ON u.id = r.user_id
            WHERE r.status IN ('submitted','resubmitted','under_review')
            ORDER BY r.submitted_at ASC LIMIT 5")->fetchAll();
    } else {
        $attention = $pdo->query("SELECT r.id, r.title, r.reference_no, r.status, NULL AS who, DATEDIFF(NOW(), r.updated_at) AS waiting
            FROM requests r WHERE r.user_id = $uid AND r.status = 'for_revision'
            ORDER BY r.updated_at ASC LIMIT 5")->fetchAll();
    }
} catch (Throwable $e) { error_log((string) $e); }

$recent = [];
try {
    $recent = $pdo->query("SELECT r.id, r.reference_no, r.title, r.status, r.country, t.name AS type_name, u.full_name AS who,
            COALESCE(r.submitted_at, r.created_at) AS sort_date
        FROM requests r LEFT JOIN request_types t ON t.id = r.request_type_id LEFT JOIN users u ON u.id = r.user_id
        WHERE 1=1 $scope ORDER BY sort_date DESC LIMIT 6")->fetchAll();
} catch (Throwable $e) { error_log((string) $e); }

/* ---------- Chart geometry ---------- */
$pct = static fn (int|float $part, int|float $whole): int => $whole > 0 ? (int) round($part / $whole * 100) : 0;
$donut = []; $cum = 0;
foreach ($statusMeta as $key => [$label, $color]) {
    $c = $counts[$key] ?? 0;
    if ($c > 0 && !($isAdmin && $key === 'draft')) {
        $p = $total > 0 ? $c / $total * 100 : 0;
        $donut[] = ['key' => $key, 'label' => $label, 'color' => $color, 'count' => $c, 'p' => $p, 'offset' => 25 - $cum];
        $cum += $p;
    }
}
$monthLabels = array_map(static fn (string $k): string => date('M', strtotime($k . '-01')), array_keys($months));
?>

<section class="dx-hero mb-4">
    <div>
        <p class="dx-eyebrow mb-1"><?= escape(ucfirst($role)) ?> dashboard &middot; <?= escape(date('F j, Y')) ?></p>
        <h1 class="h3 mb-1">Welcome back, <?= escape($user['full_name']) ?></h1>
        <p class="mb-0 opacity-75">ELIA Portal &mdash; Occidental Mindoro State College</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!$isAdmin): ?>
            <a class="btn btn-warning fw-semibold" href="<?= escape($createUrl) ?>"><i class="bi bi-plus-lg me-1"></i>New request</a>
        <?php endif; ?>
        <a class="btn btn-outline-light" href="<?= escape($requestsUrl) ?>"><?= $isAdmin ? 'Review requests' : 'My requests' ?></a>
    </div>
</section>

<!-- KPI cards -->
<div class="row g-3 mb-4">
    <?php foreach ($kpis as [$label, $value, $icon, $color, $hint]): ?>
        <div class="col-6 col-md-4 <?= $isAdmin ? 'col-xl-2' : 'col-xl-3' ?>">
            <div class="dx-card dx-kpi h-100" style="--c: <?= escape($color) ?>">
                <div class="dx-kpi-icon"><i class="bi <?= escape($icon) ?>"></i></div>
                <div class="dx-kpi-value"><?= escape((string) $value) ?></div>
                <div class="dx-kpi-label"><?= escape($label) ?></div>
                <div class="dx-kpi-hint"><?= escape($hint) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Analytics -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <section class="dx-card h-100" aria-labelledby="trend-h">
            <div class="dx-head"><h2 id="trend-h">Submissions, last 6 months</h2><span class="dx-sub">Requests submitted per month</span></div>
            <div class="p-3">
                <?php
                $w = 480; $h = 200; $left = 30; $base = 165; $plot = 135; $n = count($months); $slot = ($w - $left - 10) / $n; $bar = 34;
                ?>
                <svg viewBox="0 0 <?= $w ?> <?= $h ?>" class="w-100" role="img" aria-label="Bar chart of requests submitted per month: <?= escape(implode(', ', array_map(static fn ($l, $v) => "$l $v", $monthLabels, $months))) ?>">
                    <?php foreach ([0, .5, 1] as $g): $y = $base - $plot * $g; ?>
                        <line x1="<?= $left ?>" x2="<?= $w - 10 ?>" y1="<?= $y ?>" y2="<?= $y ?>" stroke="#e5e7eb" stroke-dasharray="<?= $g > 0 ? '3 3' : '0' ?>"/>
                        <text x="<?= $left - 6 ?>" y="<?= $y + 3 ?>" text-anchor="end" font-size="10" fill="#6b7280"><?= (int) round($trendMax * $g) ?></text>
                    <?php endforeach; ?>
                    <?php $i = 0; foreach ($months as $ym => $v):
                        $bh = $v / $trendMax * $plot; $x = $left + $slot * $i + ($slot - $bar) / 2; ?>
                        <rect x="<?= round($x, 1) ?>" y="<?= round($base - $bh, 1) ?>" width="<?= $bar ?>" height="<?= round($bh, 1) ?>" rx="4" fill="#14366e"/>
                        <?php if ($v > 0): ?><text x="<?= round($x + $bar / 2, 1) ?>" y="<?= round($base - $bh - 5, 1) ?>" text-anchor="middle" font-size="11" font-weight="600" fill="#0a1f44"><?= $v ?></text><?php endif; ?>
                        <text x="<?= round($x + $bar / 2, 1) ?>" y="<?= $base + 16 ?>" text-anchor="middle" font-size="11" fill="#6b7280"><?= escape($monthLabels[$i]) ?></text>
                    <?php $i++; endforeach; ?>
                </svg>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="dx-card h-100" aria-labelledby="status-h">
            <div class="dx-head"><h2 id="status-h">Status breakdown</h2><span class="dx-sub"><?= (int) $total ?> total</span></div>
            <div class="p-3">
                <?php if (!$donut): ?>
                    <p class="text-secondary text-center my-5">No data yet.</p>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <svg viewBox="0 0 42 42" width="132" height="132" role="img" aria-label="Donut chart of requests by status">
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#eef0f4" stroke-width="5"/>
                            <?php foreach ($donut as $s): ?>
                                <circle cx="21" cy="21" r="15.9155" fill="none" stroke="<?= escape($s['color']) ?>" stroke-width="5"
                                        stroke-dasharray="<?= round($s['p'], 2) ?> <?= round(100 - $s['p'], 2) ?>" stroke-dashoffset="<?= round($s['offset'], 2) ?>"/>
                            <?php endforeach; ?>
                            <text x="21" y="22.5" text-anchor="middle" font-size="7" font-weight="700" fill="#0a1f44"><?= (int) $total ?></text>
                        </svg>
                        <ul class="dx-legend list-unstyled mb-0 flex-fill">
                            <?php foreach ($donut as $s): ?>
                                <li><span class="dot" style="background: <?= escape($s['color']) ?>"></span><?= escape($s['label']) ?><b><?= (int) $s['count'] ?></b></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- Monitoring + attention -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <section class="dx-card h-100" aria-labelledby="mon-h">
            <div class="dx-head"><h2 id="mon-h">Travel monitoring</h2><span class="dx-sub">Verified pre-departure and post-travel documents</span></div>
            <?php if (!$monitoring): ?>
                <p class="text-secondary text-center my-5 px-3">No approved requests are being monitored right now.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 dx-table">
                        <thead><tr><th class="ps-4">Request</th><th>Travel dates</th><th style="min-width:150px">Pre-departure</th><th style="min-width:150px">Post-travel</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($monitoring as $m): [$ml, $mc] = $meta((string) $m['status']);
                            $pp = $pct((int) $m['pre_done'], (int) $m['pre_total']); $qq = $pct((int) $m['post_done'], (int) $m['post_total']); ?>
                            <tr>
                                <td class="ps-4">
                                    <a class="fw-medium text-decoration-none" href="<?= escape($viewUrl((int) $m['id'])) ?>"><?= escape($m['title']) ?></a>
                                    <div class="small text-secondary"><?= escape($m['reference_no'] ?? 'Draft') ?> &middot; <?= escape($m['country']) ?></div>
                                    <span class="dx-pill" style="--c: <?= escape($mc) ?>"><?= escape($ml) ?></span>
                                </td>
                                <td class="small text-secondary">
                                    <?= $m['start_date'] ? escape(date('M j', strtotime((string) $m['start_date']))) : '—' ?>
                                    &ndash; <?= $m['end_date'] ? escape(date('M j, Y', strtotime((string) $m['end_date']))) : '—' ?>
                                </td>
                                <td>
                                    <?php if ((int) $m['pre_total'] === 0): ?><span class="small text-secondary">No checklist</span>
                                    <?php else: ?>
                                        <div class="progress dx-progress" role="progressbar" aria-label="Pre-departure" aria-valuenow="<?= $pp ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= $pp ?>%"></div></div>
                                        <span class="small text-secondary"><?= (int) $m['pre_done'] ?> of <?= (int) $m['pre_total'] ?> verified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) $m['post_total'] === 0): ?><span class="small text-secondary">No checklist</span>
                                    <?php else: ?>
                                        <div class="progress dx-progress" role="progressbar" aria-label="Post-travel" aria-valuenow="<?= $qq ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $qq ?>%"></div></div>
                                        <span class="small text-secondary"><?= (int) $m['post_done'] ?> of <?= (int) $m['post_total'] ?> verified</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end"><a class="btn btn-sm btn-outline-primary" href="<?= escape($viewUrl((int) $m['id'])) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="dx-card h-100" aria-labelledby="att-h">
            <div class="dx-head"><h2 id="att-h"><?= $isAdmin ? 'Review queue' : 'Needs your action' ?></h2><span class="dx-sub"><?= $isAdmin ? 'Oldest first' : 'Returned for revision' ?></span></div>
            <?php if (!$attention): ?>
                <p class="text-secondary text-center my-5 px-3"><i class="bi bi-check2-circle fs-3 d-block text-success"></i>Nothing waiting. You are all caught up.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($attention as $a): ?>
                        <li class="list-group-item px-4 py-3">
                            <a class="fw-medium text-decoration-none d-block" href="<?= escape($viewUrl((int) $a['id'])) ?>"><?= escape($a['title']) ?></a>
                            <span class="small text-secondary"><?= escape($a['reference_no'] ?? 'Draft') ?><?= $a['who'] ? ' &middot; ' . escape($a['who']) : '' ?></span>
                            <div class="small mt-1 text-<?= (int) $a['waiting'] >= 3 ? 'danger' : 'secondary' ?>"><i class="bi bi-clock me-1"></i><?= (int) $a['waiting'] ?> day<?= (int) $a['waiting'] === 1 ? '' : 's' ?> <?= $isAdmin ? 'waiting' : 'since returned' ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($docsToFix > 0): ?>
                <div class="px-4 py-3 border-top small text-danger"><i class="bi bi-file-earmark-x me-1"></i><?= $docsToFix ?> document<?= $docsToFix === 1 ? '' : 's' ?> need<?= $docsToFix === 1 ? 's' : '' ?> replacing.</div>
            <?php endif; ?>
        </section>
    </div>
</div>

<!-- Breakdowns -->
<div class="row g-4 mb-4">
    <?php foreach ([['By request type', $byType, '#14366e', 'type-h'], ['Top destinations', $byCountry, '#c9a227', 'dest-h']] as [$heading, $items, $barColor, $hid]):
        $max = $items ? max(array_map(static fn ($r) => (int) $r['c'], $items)) : 1; ?>
        <div class="col-md-6 <?= $isAdmin ? 'col-xl-4' : 'col-xl-6' ?>">
            <section class="dx-card h-100" aria-labelledby="<?= $hid ?>">
                <div class="dx-head"><h2 id="<?= $hid ?>"><?= escape($heading) ?></h2></div>
                <div class="p-4">
                    <?php if (!$items): ?><p class="text-secondary mb-0">No data yet.</p><?php endif; ?>
                    <?php foreach ($items as $it): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1"><span><?= escape($it['label']) ?></span><b><?= (int) $it['c'] ?></b></div>
                            <div class="dx-bar"><span style="width: <?= $pct((int) $it['c'], $max) ?>%; background: <?= $barColor ?>"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    <?php endforeach; ?>
    <?php if ($isAdmin): ?>
        <div class="col-md-12 col-xl-4">
            <section class="dx-card h-100" aria-labelledby="part-h">
                <div class="dx-head"><h2 id="part-h">Partnerships</h2></div>
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div><div class="dx-kpi-value"><?= $partners['active'] ?></div><div class="small text-secondary">Active MOU/MOA</div></div>
                        <div class="text-end"><div class="dx-kpi-value" style="color: <?= $partners['expiring'] > 0 ? '#c9a227' : '#16a34a' ?>"><?= $partners['expiring'] ?></div><div class="small text-secondary">Expiring in 90 days</div></div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="<?= escape(url('admin/partnerships/index.php')) ?>">Manage partnerships</a>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<!-- Recent requests -->
<section class="dx-card mb-4" aria-labelledby="recent-h">
    <div class="dx-head"><h2 id="recent-h">Recent requests</h2><a class="small text-decoration-none" href="<?= escape($requestsUrl) ?>">View all <i class="bi bi-arrow-right"></i></a></div>
    <?php if (!$recent): ?>
        <div class="text-center py-5 px-3">
            <i class="bi bi-inbox display-6 text-secondary"></i>
            <p class="fw-semibold mt-3 mb-1">No requests yet</p>
            <p class="text-secondary small mb-3"><?= $isAdmin ? 'Submitted requests will appear here.' : 'Create your first request to get started.' ?></p>
            <?php if (!$isAdmin): ?><a class="btn btn-primary btn-sm" href="<?= escape($createUrl) ?>">Create request</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 dx-table">
                <thead><tr><th class="ps-4">Request</th><?php if ($isAdmin): ?><th>Client</th><?php endif; ?><th>Type</th><th>Status</th><th>Date</th><th class="pe-4"><span class="visually-hidden">Action</span></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): [$rl, $rc] = $meta((string) $r['status']); ?>
                    <tr>
                        <td class="ps-4"><div class="fw-medium"><?= escape($r['title']) ?></div><div class="small text-secondary"><?= escape($r['reference_no'] ?? 'Draft') ?> &middot; <?= escape($r['country']) ?></div></td>
                        <?php if ($isAdmin): ?><td class="small"><?= escape($r['who'] ?? '—') ?></td><?php endif; ?>
                        <td class="small text-secondary"><?= escape($r['type_name'] ?? '—') ?></td>
                        <td><span class="dx-pill" style="--c: <?= escape($rc) ?>"><?= escape($rl) ?></span></td>
                        <td class="small text-secondary"><?= escape(date('M j, Y', strtotime((string) $r['sort_date']))) ?></td>
                        <td class="pe-4 text-end"><a class="btn btn-sm btn-outline-primary" href="<?= escape($viewUrl((int) $r['id'])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<style>
:root { --dx-navy: #0a1f44; --dx-navy-2: #14366e; --dx-gold: #c9a227; }
.dx-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.5rem 2rem; border-radius: 1rem; color: #fff;
  background: linear-gradient(135deg, var(--dx-navy) 0%, var(--dx-navy-2) 100%); border-bottom: 4px solid var(--dx-gold); }
.dx-eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: .72rem; font-weight: 600; color: var(--dx-gold); }
.dx-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .875rem; box-shadow: 0 1px 2px rgba(10, 31, 68, .05); overflow: hidden; }
.dx-head { display: flex; justify-content: space-between; align-items: baseline; gap: .75rem; flex-wrap: wrap; padding: 1rem 1.5rem; border-bottom: 1px solid #eef0f4; }
.dx-head h2 { font-size: 1rem; font-weight: 600; margin: 0; color: var(--dx-navy); }
.dx-sub { font-size: .8rem; color: #6b7280; }
.dx-kpi { padding: 1.1rem 1.2rem; border-top: 3px solid var(--c); position: relative; transition: box-shadow .15s, transform .15s; }
.dx-kpi:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(10, 31, 68, .08); }
.dx-kpi-icon { position: absolute; top: .9rem; right: 1rem; width: 36px; height: 36px; display: grid; place-items: center; border-radius: 10px; color: var(--c); background: #f3f4f6; font-size: 1.1rem; }
.dx-kpi-value { font-size: 1.9rem; font-weight: 700; line-height: 1.1; color: var(--dx-navy); }
.dx-kpi-label { font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #374151; margin-top: .35rem; }
.dx-kpi-hint { font-size: .78rem; color: #6b7280; }
.dx-pill { display: inline-block; padding: .15rem .6rem; border-radius: 99px; font-size: .75rem; font-weight: 600; color: var(--c); background: #f3f4f6; background: color-mix(in srgb, var(--c) 13%, white); }
.dx-table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; background: #f9fafb; border-bottom: 0; white-space: nowrap; }
.dx-progress { height: .5rem; border-radius: 99px; margin-bottom: .25rem; }
.dx-progress .progress-bar { background: var(--dx-navy-2); }
.dx-bar { height: .5rem; background: #eef0f4; border-radius: 99px; overflow: hidden; }
.dx-bar span { display: block; height: 100%; border-radius: 99px; }
.dx-legend li { display: flex; align-items: center; gap: .5rem; font-size: .85rem; padding: .15rem 0; }
.dx-legend b { margin-left: auto; }
.dx-legend .dot { width: .6rem; height: .6rem; border-radius: 50%; flex: none; }
@media (prefers-reduced-motion: reduce) { .dx-kpi { transition: none; } .dx-kpi:hover { transform: none; } }
</style>