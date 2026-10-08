<?php
$pdo     = database();
$user    = $user ?? current_user();   // alisin kung defined na sa taas ng file mo
$role    = $user['role'];
$isAdmin = $role === 'admin';

$requestsUrl = url($role . '/requests/index.php');
$createUrl   = url('client/requests/create.php');

// Admin: lahat ng requests. Client: sa kanya lang.
$where  = $isAdmin ? '' : 'WHERE user_id = :uid';
$params = $isAdmin ? [] : ['uid' => $user['id']];

/* ---------- Stats ---------- */
$stmt = $pdo->prepare("SELECT status, COUNT(*) FROM requests $where GROUP BY status");
$stmt->execute($params);
$counts = array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

$total     = array_sum($counts);
$completed = $counts['completed'] ?? 0;
$awaiting  = ($counts['submitted'] ?? 0) + ($counts['under_review'] ?? 0);
$revision  = $counts['for_revision'] ?? 0;
$rate      = $total > 0 ? (int) round($completed / $total * 100) : 0;

$stats = [
    ['Total requests',  $total,     'bi-folder2-open',    'navy'],
    ['Awaiting review', $awaiting,  'bi-hourglass-split', 'gold'],
    ['For revision',    $revision,  'bi-pencil-square',   'rose'],
    ['Completed',       $completed, 'bi-check2-circle',   'green'],
];

/* ---------- Recent requests ---------- */
$recentWhere = $isAdmin ? "WHERE status <> 'draft'" : 'WHERE user_id = :uid';
$stmt = $pdo->prepare(
    "SELECT id, reference_no, title, status, COALESCE(submitted_at, created_at) AS sort_date
     FROM requests $recentWhere
     ORDER BY sort_date DESC
     LIMIT 6"
);
$stmt->execute($params);
$recent = $stmt->fetchAll();

$statusMap = [
    'draft'        => ['secondary', 'Draft'],
    'submitted'    => ['warning',   'Submitted'],
    'under_review' => ['info',      'Under review'],
    'for_revision' => ['danger',    'For revision'],
    'approved'     => ['primary',   'Approved'],
    'completed'    => ['success',   'Completed'],
];

$steps = $isAdmin
    ? ['Review submitted requests', 'Verify uploaded documents', 'Update status and notify the requester', 'Mark the request as completed']
    : ['Create a request', 'Upload the requirements', 'Wait for ELIA Office review', 'Track updates and results'];
?>

<!-- Hero -->
<section class="dash-hero mb-4">
    <div>
        <p class="dash-eyebrow mb-1"><?= escape(ucfirst($role)) ?> dashboard</p>
        <h1 class="h3 mb-1">Welcome back, <?= escape($user['full_name']) ?></h1>
        <p class="mb-0 opacity-75">OMSC Internationalization Affairs workspace</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!$isAdmin): ?>
            <a class="btn btn-warning fw-semibold" href="<?= escape($createUrl) ?>">
                <i class="bi bi-plus-lg me-1"></i>New request
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-light" href="<?= escape($requestsUrl) ?>">
            <?= $isAdmin ? 'Review requests' : 'My requests' ?>
        </a>
    </div>
</section>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php foreach ($stats as [$label, $value, $icon, $tone]): ?>
        <div class="col-6 col-xl-3">
            <div class="card dash-card stat-card tone-<?= $tone ?> h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small text-uppercase fw-semibold"><?= escape($label) ?></div>
                        <div class="stat-value"><?= (int) $value ?></div>
                    </div>
                    <div class="stat-icon"><i class="bi <?= $icon ?>"></i></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Recent requests -->
    <div class="col-lg-8">
        <section class="card dash-card h-100" aria-labelledby="recent-heading">
            <div class="dash-panel-head">
                <h2 class="h6 mb-0" id="recent-heading">Recent requests</h2>
                <a class="small text-decoration-none" href="<?= escape($requestsUrl) ?>">
                    View all <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if (!$recent): ?>
                <div class="text-center py-5 px-3">
                    <i class="bi bi-inbox display-5 text-secondary"></i>
                    <p class="fw-semibold mt-3 mb-1">No requests yet</p>
                    <p class="text-secondary small mb-3">
                        <?= $isAdmin ? 'Submitted requests will appear here.' : 'Create your first request to get started.' ?>
                    </p>
                    <?php if (!$isAdmin): ?>
                        <a class="btn btn-primary btn-sm" href="<?= escape($createUrl) ?>">Create request</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Request</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="pe-4"><span class="visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r):
                                [$color, $text] = $statusMap[$r['status']]
                                    ?? ['secondary', ucwords(str_replace('_', ' ', (string) $r['status']))]; ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-medium"><?= escape($r['title']) ?></div>
                                        <div class="text-secondary small"><?= escape($r['reference_no'] ?? 'Draft') ?></div>
                                    </td>
                                    <td><span class="badge rounded-pill text-bg-<?= $color ?>"><?= escape($text) ?></span></td>
                                    <td class="text-secondary small"><?= escape(date('M j, Y', strtotime((string) $r['sort_date']))) ?></td>
                                    <td class="pe-4 text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= escape(url($role . '/requests/view.php?id=' . (int) $r['id'])) ?>">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Side column -->
    <div class="col-lg-4 d-flex flex-column gap-4">
        <section class="card dash-card" aria-labelledby="progress-heading">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0" id="progress-heading">Completion rate</h2>
                    <span class="fw-bold"><?= $rate ?>%</span>
                </div>
                <div class="progress" role="progressbar" aria-label="Completion rate"
                     aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                </div>
                <p class="text-secondary small mt-2 mb-0"><?= $completed ?> of <?= $total ?> requests completed</p>
            </div>
        </section>

        <section class="card dash-card flex-fill" aria-labelledby="guide-heading">
            <div class="card-body p-4">
                <span class="badge text-bg-success mb-3">Account active</span>
                <h2 class="h6" id="guide-heading"><?= $isAdmin ? 'Review workflow' : 'How it works' ?></h2>
                <ol class="list-unstyled mt-3 mb-0">
                    <?php foreach ($steps as $i => $step): ?>
                        <li class="d-flex gap-3 mb-3">
                            <span class="step-num"><?= $i + 1 ?></span>
                            <span class="small"><?= escape($step) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>
    </div>
</div>

<style>
:root { --elia-navy: #0a1f44; --elia-navy-2: #14366e; --elia-gold: #c9a227; }

.dash-hero {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;
  padding: 1.75rem 2rem; border-radius: 1rem; color: #fff;
  background: linear-gradient(135deg, var(--elia-navy) 0%, var(--elia-navy-2) 100%);
  border-bottom: 4px solid var(--elia-gold);
}
.dash-eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: .75rem; font-weight: 600; color: var(--elia-gold); }

.dash-card { border: 1px solid var(--bs-border-color-translucent); border-radius: .875rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.stat-card { border-left: 4px solid var(--tone); transition: transform .15s, box-shadow .15s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08); }
.tone-navy  { --tone: var(--elia-navy);  --tone-bg: #e3e9f5; }
.tone-gold  { --tone: var(--elia-gold);  --tone-bg: #fbf3d6; }
.tone-rose  { --tone: #c0392b;           --tone-bg: #fbe4e1; }
.tone-green { --tone: #198754;           --tone-bg: #d9f0e3; }
.stat-value { font-size: 2rem; font-weight: 700; line-height: 1.1; }
.stat-icon  { width: 48px; height: 48px; display: grid; place-items: center; border-radius: 12px; font-size: 1.3rem; background: var(--tone-bg); color: var(--tone); }

.dash-panel-head { display: flex; justify-content: space-between; align-items: center; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
.dash-card .table thead th { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); border-bottom: 0; }
.progress { height: .6rem; border-radius: 1rem; }
.step-num { flex: 0 0 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; font-size: .8rem; font-weight: 600; background: #e3e9f5; color: var(--elia-navy); }
</style>