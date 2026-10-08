<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reports.php';
$user = requireAdmin();
$pageTitle = 'Reports';

$view = ($_GET['view'] ?? '') === 'agreements' ? 'agreements' : 'requests';
$maxRows = 500;
$exportQuery = ['view' => $view];

if ($view === 'requests') {
    $f = report_request_filters($_GET);
    $exportQuery += array_filter($f, static fn($v) => $v !== '' && $v !== 0);
    $summary = report_request_summary($f);
    $rows = report_requests($f, $maxRows);
    $types = request_types(false);
    $period = date('F j, Y', strtotime($f['from'])) . ' to ' . date('F j, Y', strtotime($f['to']));
} else {
    $f = report_agreement_filters($_GET);
    $exportQuery += array_filter($f, static fn($v) => $v !== '');
    $rows = report_agreements($f);
    $summary = report_agreement_summary($rows);
    $period = 'As of ' . date('F j, Y', strtotime(partnership_today()));
}

/** Simple horizontal bar list (no JavaScript needed). */
function report_bars(array $data, ?callable $label = null): void
{
    $max = $data ? max(1, max($data)) : 1;
    foreach ($data as $key => $count) {
        $text = $label ? $label((string) $key) : (string) $key;
        echo '<div class="mb-2"><div class="d-flex justify-content-between small"><span>' . escape($text) . '</span><strong>' . (int) $count . '</strong></div>'
            . '<div class="progress" style="height:.5rem" role="presentation"><div class="progress-bar" style="width:' . round($count / $max * 100) . '%"></div></div></div>';
    }
    if (!$data) { echo '<p class="text-secondary small mb-0">No data for this selection.</p>'; }
}

require __DIR__ . '/../../includes/header.php';
?>
<style>
    .print-only { display: none; }
    @media print {
        .site-header, .sidebar, .no-print, .page-footer, .breadcrumb, .navbar { display: none !important; }
        .print-only { display: block; }
        .main-content { padding: 0 !important; }
        .card { border: 0 !important; box-shadow: none !important; }
        table { font-size: 11px; }
        a { color: inherit !important; text-decoration: none !important; }
    }
</style>

<div class="print-only mb-3">
    <h1 style="font-size:18px" class="mb-0">Occidental Mindoro State College — External Linkages and International Affairs</h1>
    <div><?= $view === 'requests' ? 'Transactions report' : 'MOU / MOA report' ?> · <?= escape($period) ?></div>
    <div class="small">Generated <?= escape(date('F j, Y g:i A')) ?> by <?= escape($user['full_name']) ?></div>
</div>

<div class="d-flex flex-wrap justify-content-between gap-3 mb-4 no-print">
    <div><p class="eyebrow">Monitoring &amp; documentation</p><h1 class="h3">Reports</h1>
        <p class="text-secondary mb-0">Generate transaction and MOU/MOA reports. Print the page or export to CSV (opens in Excel).</p></div>
    <div class="d-flex gap-2 align-items-start">
        <a class="btn btn-outline-primary" href="<?= escape(url('admin/reports/export.php?' . http_build_query($exportQuery))) ?>">Export CSV</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>

<nav class="d-flex gap-2 mb-4 no-print" aria-label="Report type">
    <a class="btn <?= $view === 'requests' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= escape(url('admin/reports/index.php')) ?>">Transactions</a>
    <a class="btn <?= $view === 'agreements' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= escape(url('admin/reports/index.php?view=agreements')) ?>">MOU / MOA</a>
</nav>

<form method="get" class="card card-body mb-4 no-print">
    <input type="hidden" name="view" value="<?= escape($view) ?>">
    <div class="row g-3 align-items-end">
    <?php if ($view === 'requests'): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="<?= escape($f['from']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="<?= escape($f['to']) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="type_id">Request type</label>
            <select class="form-select" id="type_id" name="type_id"><option value="">All types</option>
                <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $f['type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= escape($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (array_diff(request_statuses(), ['draft']) as $s): ?><option value="<?= escape($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= escape(request_label($s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label" for="country">Country</label><input class="form-control" id="country" name="country" maxlength="100" value="<?= escape($f['country']) ?>"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Go</button></div>
    <?php else: ?>
        <div class="col-md-3"><label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status"><option value="">All</option>
                <?php foreach (['active', 'upcoming', 'expired', 'draft', 'terminated', 'archived'] as $s): ?><option value="<?= $s ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label" for="type">Type</label>
            <select class="form-select" id="type" name="type"><option value="">MOU and MOA</option>
                <?php foreach (['MOU', 'MOA'] as $s): ?><option <?= $f['type'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="country">Country</label><input class="form-control" id="country" name="country" maxlength="100" value="<?= escape($f['country']) ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Generate</button></div>
    <?php endif; ?>
    </div>
</form>

<?php if ($view === 'requests'): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Total transactions</div><div class="h2 mb-0"><?= (int) $summary['total'] ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Completed</div><div class="h2 mb-0"><?= (int) ($summary['by_status']['completed'] ?? 0) ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Pending review</div><div class="h2 mb-0"><?= (int) (($summary['by_status']['submitted'] ?? 0) + ($summary['by_status']['resubmitted'] ?? 0) + ($summary['by_status']['under_review'] ?? 0)) ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Avg. days to complete</div><div class="h2 mb-0"><?= $summary['avg_days'] !== null ? escape((string) $summary['avg_days']) : '—' ?></div></div></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-lg-4"><div class="card card-body h-100"><h2 class="h6">By status</h2><?php report_bars($summary['by_status'], 'request_label'); ?></div></div>
        <div class="col-lg-4"><div class="card card-body h-100"><h2 class="h6">By request type</h2><?php report_bars($summary['by_type']); ?></div></div>
        <div class="col-lg-4"><div class="card card-body h-100"><h2 class="h6">By destination country (top 10)</h2><?php report_bars($summary['by_country']); ?></div></div>
        <div class="col-12"><div class="card card-body"><h2 class="h6">By month</h2><?php report_bars($summary['by_month'], static fn(string $m): string => date('F Y', strtotime($m . '-01'))); ?></div></div>
    </div>
    <h2 class="h5">Transactions <span class="text-secondary fs-6">(<?= count($rows) ?><?= $summary['total'] > count($rows) ? ' of ' . (int) $summary['total'] . ' shown — export CSV for all' : '' ?>)</span></h2>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th scope="col">Reference</th><th scope="col">Title / Client</th><th scope="col">Type</th><th scope="col">Destination</th><th scope="col">Travel dates</th><th scope="col">Status</th><th scope="col">Submitted</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= escape($r['reference_no'] ?: ('#' . $r['id'])) ?></td>
                <td><strong><?= escape($r['title']) ?></strong><br><span class="small text-secondary"><?= escape($r['client']) ?></span></td>
                <td><?= escape($r['type_name']) ?></td>
                <td><?= escape(trim($r['destination'] . ', ' . $r['country'], ', ')) ?></td>
                <td><?= escape(($r['start_date'] ?? '—') . ' to ' . ($r['end_date'] ?? '—')) ?></td>
                <td><?= request_badge($r['status']) ?></td>
                <td><?= escape($r['submitted_at'] ? date('M j, Y', strtotime($r['submitted_at'])) : '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-center text-secondary">No transactions match these filters.</td></tr><?php endif; ?>
        </tbody></table></div></div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Agreements</div><div class="h2 mb-0"><?= (int) $summary['total'] ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Partner institutions</div><div class="h2 mb-0"><?= (int) $summary['partners'] ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Active</div><div class="h2 mb-0"><?= (int) ($summary['by_state']['active'] ?? 0) ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="small text-secondary text-uppercase">Ending in 90 days</div><div class="h2 mb-0"><?= (int) $summary['expiring'] ?></div></div></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-lg-6"><div class="card card-body h-100"><h2 class="h6">By status</h2><?php report_bars($summary['by_state'], 'ucfirst'); ?></div></div>
        <div class="col-lg-6"><div class="card card-body h-100"><h2 class="h6">By country (top 10)</h2><?php report_bars($summary['by_country']); ?></div></div>
    </div>
    <h2 class="h5">Agreements</h2>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th scope="col">Reference</th><th scope="col">Institution</th><th scope="col">Country</th><th scope="col">Type</th><th scope="col">Effective</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= escape($r['reference_no']) ?></strong><br><span class="small text-secondary"><?= escape($r['title']) ?></span></td>
                <td><?= escape($r['partner_name']) ?></td>
                <td><?= escape($r['country']) ?></td>
                <td><?= escape($r['agreement_type']) ?></td>
                <td><?= escape(($r['start_date'] ?? '—') . ' to ' . ($r['end_date'] ?? 'no end date')) ?></td>
                <td><?= escape(ucfirst($r['state'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="p-4 text-center text-secondary">No agreements match these filters.</td></tr><?php endif; ?>
        </tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
