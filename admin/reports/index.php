<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = requireAdmin();
$pdo = database();

$statusLabels = [
    'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under review', 'for_revision' => 'For revision',
    'resubmitted' => 'Resubmitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'in_progress' => 'In progress',
    'post_travel' => 'Post-travel', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
];

/* ---------- Filters ---------- */
$validDate = static fn (string $d): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false ? $d : '';
$from   = $validDate((string) ($_GET['from'] ?? ''));
$to     = $validDate((string) ($_GET['to'] ?? ''));
$typeId = (int) ($_GET['type'] ?? 0);
$status = (string) ($_GET['status'] ?? '');
if ($status !== '' && !isset($statusLabels[$status])) { $status = ''; }

$where = ["r.status <> 'draft'"];
$args = [];
if ($from !== '') { $where[] = 'DATE(r.submitted_at) >= ?'; $args[] = $from; }
if ($to !== '')   { $where[] = 'DATE(r.submitted_at) <= ?'; $args[] = $to; }
if ($typeId > 0)  { $where[] = 'r.request_type_id = ?';     $args[] = $typeId; }
if ($status !== '') { $where[] = 'r.status = ?';            $args[] = $status; }
$w = implode(' AND ', $where);

$run = static function (string $sql, array $params = []) use ($pdo): array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
};

/* ---------- CSV export ---------- */
$export = (string) ($_GET['export'] ?? '');
if ($export === 'requests' || $export === 'partnerships') {
    if ($export === 'requests') {
        $head = ['Reference', 'Title', 'Type', 'Requested by', 'Destination country', 'Start date', 'End date', 'Status', 'Submitted'];
        $rows = $run("SELECT r.reference_no, r.title, t.name, u.full_name, r.country, r.start_date, r.end_date, r.status, r.submitted_at
                      FROM requests r LEFT JOIN request_types t ON t.id = r.request_type_id LEFT JOIN users u ON u.id = r.user_id
                      WHERE $w ORDER BY r.submitted_at DESC, r.id DESC", $args);
    } else {
        $head = ['Partner', 'Country', 'Reference', 'Type', 'Status', 'Start date', 'End date'];
        $rows = $run("SELECT p.name, p.country, a.reference_no, a.agreement_type, a.status, a.start_date, a.end_date
                      FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id
                      WHERE a.is_archived = 0 AND p.is_archived = 0 ORDER BY p.name");
    }
    $safe = static function ($v): string {
        $s = (string) ($v ?? '');
        return $s !== '' && strpos("=+-@\t\r", $s[0]) !== false ? "'" . $s : $s;
    };
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="elia-' . $export . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $head);
    foreach ($rows as $row) { fputcsv($out, array_map($safe, array_values($row))); }
    fclose($out);
    exit;
}

/* ---------- Data ---------- */
$types = $run('SELECT id, name FROM request_types ORDER BY name');

$total     = (int) ($run("SELECT COUNT(*) c FROM requests r WHERE $w", $args)[0]['c'] ?? 0);
$approved  = (int) ($run("SELECT COUNT(*) c FROM requests r WHERE $w AND r.status IN ('approved','in_progress','post_travel','completed')", $args)[0]['c'] ?? 0);
$completed = (int) ($run("SELECT COUNT(*) c FROM requests r WHERE $w AND r.status = 'completed'", $args)[0]['c'] ?? 0);
$pending   = (int) ($run("SELECT COUNT(*) c FROM requests r WHERE $w AND r.status IN ('submitted','resubmitted','under_review','for_revision')", $args)[0]['c'] ?? 0);
$avgDays   = $run("SELECT AVG(TIMESTAMPDIFF(HOUR, r.submitted_at, r.approved_at)) / 24 a FROM requests r WHERE $w AND r.approved_at IS NOT NULL", $args)[0]['a'] ?? null;

$byStatus  = $run("SELECT r.status label, COUNT(*) c FROM requests r WHERE $w GROUP BY r.status ORDER BY c DESC", $args);
$byType    = $run("SELECT COALESCE(t.name,'Unspecified') label, COUNT(*) c FROM requests r LEFT JOIN request_types t ON t.id = r.request_type_id WHERE $w GROUP BY t.id, t.name ORDER BY c DESC", $args);
$byCountry = $run("SELECT r.country label, COUNT(*) c FROM requests r WHERE $w AND r.country <> '' GROUP BY r.country ORDER BY c DESC LIMIT 10", $args);
$byMonth   = $run("SELECT DATE_FORMAT(r.submitted_at, '%Y-%m') label, COUNT(*) c FROM requests r WHERE $w AND r.submitted_at IS NOT NULL GROUP BY label ORDER BY label DESC LIMIT 12", $args);
$byMonth   = array_reverse($byMonth);
$list      = $run("SELECT r.id, r.reference_no, r.title, t.name type_name, u.full_name who, r.country, r.start_date, r.end_date, r.status, r.submitted_at
                   FROM requests r LEFT JOIN request_types t ON t.id = r.request_type_id LEFT JOIN users u ON u.id = r.user_id
                   WHERE $w ORDER BY r.submitted_at DESC, r.id DESC LIMIT 100", $args);

// Partnerships (not affected by the request filters)
$pStatus   = $run("SELECT a.status label, COUNT(*) c FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE a.is_archived = 0 AND p.is_archived = 0 GROUP BY a.status");
$pActive   = (int) ($run("SELECT COUNT(*) c FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE a.is_archived = 0 AND p.is_archived = 0 AND a.status = 'signed' AND (a.end_date IS NULL OR a.end_date >= CURDATE())")[0]['c'] ?? 0);
$pPartners = (int) ($run('SELECT COUNT(*) c FROM partners WHERE is_archived = 0')[0]['c'] ?? 0);
$pCountries = $run("SELECT p.country label, COUNT(DISTINCT p.id) c FROM partners p JOIN partnership_agreements a ON a.partner_id = p.id WHERE p.is_archived = 0 AND a.is_archived = 0 AND a.status = 'signed' AND p.country NOT IN ('Philippines','Not specified') GROUP BY p.country ORDER BY c DESC LIMIT 10");
$expiring  = $run("SELECT p.name, p.country, a.reference_no, a.end_date, DATEDIFF(a.end_date, CURDATE()) days
                   FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id
                   WHERE a.is_archived = 0 AND p.is_archived = 0 AND a.status = 'signed' AND a.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 180 DAY)
                   ORDER BY a.end_date");
$expired   = (int) ($run("SELECT COUNT(*) c FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE a.is_archived = 0 AND p.is_archived = 0 AND a.status = 'signed' AND a.end_date < CURDATE()")[0]['c'] ?? 0);

$pageTitle = 'Reports';
$qs = static fn (array $extra = []): string => '?' . http_build_query(array_filter(
    array_merge(['from' => $from, 'to' => $to, 'type' => $typeId ?: '', 'status' => $status], $extra),
    static fn ($v) => $v !== '' && $v !== null
));

/** Horizontal bar list: [[label, count], ...] */
$bars = static function (array $rows, string $color = '#16365F', ?array $labels = null): string {
    if (!$rows) { return '<p class="text-muted small mb-0">No data for this selection.</p>'; }
    $max = max(array_map(static fn ($r) => (int) $r['c'], $rows)) ?: 1;
    $html = '';
    foreach ($rows as $r) {
        $label = $labels[$r['label']] ?? $r['label'];
        $pct = max(3, (int) round(((int) $r['c'] / $max) * 100));
        $html .= '<div class="rp-bar"><span class="rp-bl" title="' . escape((string) $label) . '">' . escape((string) $label) . '</span>'
              . '<span class="rp-track"><i style="width:' . $pct . '%;background:' . $color . '"></i></span>'
              . '<b>' . (int) $r['c'] . '</b></div>';
    }
    return $html;
};

require __DIR__ . '/../../includes/header.php';
?>
<style>
.rp-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;margin-bottom:1.25rem}
.rp-kpi{background:#fff;border:1px solid #e3e1d9;border-radius:10px;padding:1rem 1.1rem;border-top:3px solid #C99A2E}
.rp-kpi small{display:block;color:#546071;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600}
.rp-kpi b{display:block;font-size:1.9rem;line-height:1.15;color:#0B2545;margin-top:.2rem}
.rp-kpi span{font-size:.8rem;color:#546071}
.rp-card{background:#fff;border:1px solid #e3e1d9;border-radius:10px;padding:1.1rem 1.25rem;height:100%}
.rp-card h2{font-size:1rem;font-weight:700;color:#0B2545;margin:0 0 .85rem}
.rp-bar{display:grid;grid-template-columns:minmax(90px,38%) 1fr 2.2rem;gap:.6rem;align-items:center;font-size:.86rem;margin-bottom:.45rem}
.rp-bl{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#17202B}
.rp-track{background:#eef0f3;border-radius:99px;height:10px;overflow:hidden;display:block}
.rp-track i{display:block;height:100%;border-radius:99px}
.rp-bar b{text-align:right;color:#0B2545}
.rp-sec{font-family:'Source Serif 4',Georgia,serif;font-size:1.25rem;color:#0B2545;margin:2rem 0 .8rem;padding-bottom:.4rem;border-bottom:2px solid #C99A2E;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
@media print{.no-print,.sidebar,.el-sidebar,nav,header,footer{display:none !important}.rp-card,.rp-kpi{break-inside:avoid}}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h3 mb-0">Reports</h1>
    <small class="text-muted">Requests and partnerships at a glance. Generated <?= escape(date('M j, Y g:i A')) ?>.</small>
  </div>
  <div class="d-flex gap-2 no-print">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print / Save as PDF</button>
  </div>
</div>

<form method="get" class="card card-body mb-3 no-print">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Submitted from</label><input type="date" class="form-control" name="from" value="<?= escape($from) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input type="date" class="form-control" name="to" value="<?= escape($to) ?>"></div>
    <div class="col-12 col-md-3">
      <label class="form-label small mb-1">Request type</label>
      <select class="form-select" name="type">
        <option value="">All types</option>
        <?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $typeId === (int) $t['id'] ? ' selected' : '' ?>><?= escape($t['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label small mb-1">Status</label>
      <select class="form-select" name="status">
        <option value="">All statuses</option>
        <?php foreach ($statusLabels as $k => $lbl): if ($k === 'draft') { continue; } ?><option value="<?= escape($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= escape($lbl) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-fill" type="submit">Apply</button>
      <a class="btn btn-outline-secondary" href="index.php">Reset</a>
    </div>
  </div>
</form>

<div class="rp-sec" style="margin-top:.5rem"><span>Requests</span>
  <a class="btn btn-sm btn-outline-primary no-print" href="<?= escape($qs(['export' => 'requests'])) ?>">Export CSV</a>
</div>

<div class="rp-kpis">
  <div class="rp-kpi"><small>Total requests</small><b><?= $total ?></b><span>submitted, excluding drafts</span></div>
  <div class="rp-kpi"><small>Awaiting action</small><b><?= $pending ?></b><span>submitted, under review or revision</span></div>
  <div class="rp-kpi"><small>Approved onward</small><b><?= $approved ?></b><span><?= $total ? round($approved / $total * 100) : 0 ?>% of total</span></div>
  <div class="rp-kpi"><small>Completed</small><b><?= $completed ?></b><span>travel closed out</span></div>
  <div class="rp-kpi"><small>Avg. approval time</small><b><?= $avgDays !== null ? number_format((float) $avgDays, 1) : '&ndash;' ?></b><span>days, submit to approval</span></div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6"><div class="rp-card"><h2>By status</h2><?= $bars($byStatus, '#16365F', $statusLabels) ?></div></div>
  <div class="col-12 col-lg-6"><div class="rp-card"><h2>By request type</h2><?= $bars($byType, '#C99A2E') ?></div></div>
  <div class="col-12 col-lg-6"><div class="rp-card"><h2>Top destination countries</h2><?= $bars($byCountry, '#2F6FD0') ?></div></div>
  <div class="col-12 col-lg-6"><div class="rp-card"><h2>Submitted per month (last 12)</h2><?= $bars($byMonth, '#1E7B34') ?></div></div>
</div>

<h2 class="h6 mt-4 mb-2">Request list <small class="text-muted fw-normal">(latest 100<?= $total > 100 ? ' of ' . $total . ', export CSV for all' : '' ?>)</small></h2>
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead><tr><th>Reference</th><th>Title</th><th>Type</th><th>Requested by</th><th>Country</th><th>Travel dates</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$list): ?><tr><td colspan="7" class="text-center text-muted py-4">No requests match these filters.</td></tr><?php endif; ?>
    <?php foreach ($list as $r): ?>
      <tr>
        <td class="text-nowrap"><a href="<?= escape(url('admin/requests/view.php?id=' . (int) $r['id'])) ?>"><?= escape($r['reference_no'] ?: '#' . $r['id']) ?></a></td>
        <td><?= escape($r['title']) ?></td>
        <td><?= escape((string) $r['type_name']) ?></td>
        <td><?= escape((string) $r['who']) ?></td>
        <td><?= escape((string) $r['country']) ?></td>
        <td class="text-nowrap small"><?= escape((string) $r['start_date']) ?><?= $r['end_date'] ? ' &ndash; ' . escape((string) $r['end_date']) : '' ?></td>
        <td><span class="badge text-bg-secondary"><?= escape($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="rp-sec"><span>Partnerships</span>
  <a class="btn btn-sm btn-outline-primary no-print" href="<?= escape($qs(['export' => 'partnerships'])) ?>">Export CSV</a>
</div>
<div class="rp-kpis">
  <div class="rp-kpi"><small>Partners</small><b><?= $pPartners ?></b><span>organizations on record</span></div>
  <div class="rp-kpi"><small>Active agreements</small><b><?= $pActive ?></b><span>signed and not expired</span></div>
  <div class="rp-kpi"><small>Expiring in 180 days</small><b><?= count($expiring) ?></b><span>needs renewal follow-up</span></div>
  <div class="rp-kpi"><small>Expired</small><b><?= $expired ?></b><span>signed, past end date</span></div>
</div>
<div class="row g-3">
  <div class="col-12 col-lg-4"><div class="rp-card"><h2>Agreements by status</h2><?= $bars($pStatus, '#16365F', ['draft' => 'Draft', 'signed' => 'Signed', 'terminated' => 'Terminated']) ?></div></div>
  <div class="col-12 col-lg-4"><div class="rp-card"><h2>International partners by country</h2><?= $bars($pCountries, '#C99A2E') ?></div></div>
  <div class="col-12 col-lg-4">
    <div class="rp-card">
      <h2>Expiring soon</h2>
      <?php if (!$expiring): ?><p class="text-muted small mb-0">Nothing expires in the next 180 days.</p><?php endif; ?>
      <?php foreach ($expiring as $e): ?>
        <div class="d-flex justify-content-between gap-2 small border-bottom py-1">
          <span><?= escape($e['name']) ?></span>
          <span class="text-nowrap text-muted"><?= escape((string) $e['end_date']) ?> (<?= (int) $e['days'] ?>d)</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>