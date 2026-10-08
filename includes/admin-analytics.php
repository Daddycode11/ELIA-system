<?php
declare(strict_types=1);
/**
 * Admin dashboard analytics: requests per month, top countries, partnerships overview.
 * Included from admin/dashboard.php after includes/dashboard.php.
 */
require_once __DIR__ . '/partnerships.php';

$analyticsPdo = database();
$analyticsToday = partnership_today();

// Requests per month for the last 12 months (drafts excluded).
$months = [];
$cursor = new DateTimeImmutable(substr($analyticsToday, 0, 8) . '01');
for ($i = 11; $i >= 0; $i--) { $months[$cursor->modify("-$i months")->format('Y-m')] = 0; }
$statement = $analyticsPdo->prepare("SELECT DATE_FORMAT(COALESCE(submitted_at, created_at), '%Y-%m') AS ym, COUNT(*) AS n FROM requests WHERE status <> 'draft' AND COALESCE(submitted_at, created_at) >= ? GROUP BY ym");
$statement->execute([array_key_first($months) . '-01 00:00:00']);
foreach ($statement->fetchAll() as $row) { if (isset($months[$row['ym']])) { $months[$row['ym']] = (int) $row['n']; } }

$topCountries = $analyticsPdo->query("SELECT country, COUNT(*) AS n FROM requests WHERE status <> 'draft' AND country <> '' GROUP BY country ORDER BY n DESC, country LIMIT 5")->fetchAll();

$statement = $analyticsPdo->prepare('SELECT x.state, COUNT(*) AS n FROM (SELECT (' . agreement_state_sql() . ') AS state FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id) x GROUP BY x.state');
$statement->execute([$analyticsToday, $analyticsToday]);
$agreementStates = array_column($statement->fetchAll(), 'n', 'state');
$partnerCount = (int) $analyticsPdo->query('SELECT COUNT(*) FROM partners WHERE is_archived = 0')->fetchColumn();

$statement = $analyticsPdo->prepare("SELECT a.reference_no, a.title, a.end_date, p.name AS partner_name FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE a.is_archived = 0 AND p.is_archived = 0 AND a.status = 'signed' AND a.end_date IS NOT NULL AND a.end_date BETWEEN ? AND ? ORDER BY a.end_date LIMIT 5");
$statement->execute([$analyticsToday, (new DateTimeImmutable($analyticsToday))->modify('+90 days')->format('Y-m-d')]);
$expiringSoon = $statement->fetchAll();

$monthMax = max(1, max($months));
$countryMax = $topCountries ? max(1, (int) $topCountries[0]['n']) : 1;
?>
<section class="mt-4" aria-labelledby="analytics-heading">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0" id="analytics-heading">Analytics</h2>
        <a class="small" href="<?= escape(url('admin/reports/index.php')) ?>">Open reports</a>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card card-body h-100">
                <h3 class="h6">Requests per month (last 12 months)</h3>
                <div class="d-flex align-items-end gap-1" style="height:140px" role="img" aria-label="Bar chart of requests per month">
                    <?php foreach ($months as $ym => $n): ?>
                        <div class="flex-fill text-center" style="min-width:0">
                            <div class="small"><?= $n ?: '' ?></div>
                            <div class="bg-primary rounded-top" style="height:<?= $n ? max(4, (int) round($n / $monthMax * 100)) : 2 ?>px;opacity:<?= $n ? '1' : '.25' ?>"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-1 mt-1">
                    <?php foreach (array_keys($months) as $ym): ?><div class="flex-fill text-center text-secondary" style="font-size:.7rem;min-width:0"><?= escape(date('M', strtotime($ym . '-01'))) ?></div><?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-body h-100">
                <h3 class="h6">Top destination countries</h3>
                <?php foreach ($topCountries as $row): ?>
                    <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= escape($row['country']) ?></span><strong><?= (int) $row['n'] ?></strong></div>
                        <div class="progress" style="height:.5rem" role="presentation"><div class="progress-bar" style="width:<?= (int) round($row['n'] / $countryMax * 100) ?>%"></div></div></div>
                <?php endforeach; ?>
                <?php if (!$topCountries): ?><p class="text-secondary small mb-0">No submitted requests yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-body h-100">
                <h3 class="h6">Partnerships overview</h3>
                <div class="d-flex gap-4 mb-2">
                    <div><div class="h3 mb-0"><?= $partnerCount ?></div><div class="small text-secondary">Partner institutions</div></div>
                    <div><div class="h3 mb-0"><?= (int) ($agreementStates['active'] ?? 0) ?></div><div class="small text-secondary">Active MOU/MOA</div></div>
                    <div><div class="h3 mb-0"><?= (int) ($agreementStates['expired'] ?? 0) ?></div><div class="small text-secondary">Expired</div></div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card card-body h-100">
                <h3 class="h6">Agreements ending within 90 days</h3>
                <?php foreach ($expiringSoon as $row): ?>
                    <div class="d-flex justify-content-between border-top py-2 small">
                        <span><strong><?= escape($row['reference_no']) ?></strong> — <?= escape($row['partner_name']) ?></span>
                        <span class="text-nowrap"><?= escape(date('M j, Y', strtotime($row['end_date']))) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$expiringSoon): ?><p class="text-secondary small mb-0">None. Agreements with an end date will be listed here as they approach expiry.</p><?php endif; ?>
            </div>
        </div>
    </div>
</section>
