<?php
$isAdmin     = $user['role'] === 'admin';
$requestsUrl = url($user['role'] . '/requests/index.php');
$createUrl   = url('user/requests/create.php'); // ayusin sa tunay na route

$where  = $isAdmin ? '' : 'WHERE user_id = :uid';
$params = $isAdmin ? [] : ['uid' => $user['id']];

// Counts per status
$stmt = $pdo->prepare("SELECT status, COUNT(*) FROM requests $where GROUP BY status");
$stmt->execute($params);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total     = array_sum($counts);
$completed = (int) ($counts['completed'] ?? 0);
$rate      = $total > 0 ? round($completed / $total * 100) : 0;

$stats = [
    ['Total requests', $total,                          'bi-folder2-open',    'primary'],
    ['Pending',        (int) ($counts['pending'] ?? 0),   'bi-hourglass-split', 'warning'],
    ['In review',      (int) ($counts['in_review'] ?? 0), 'bi-search',          'info'],
    ['Completed',      $completed,                      'bi-check2-circle',   'success'],
];

// Recent requests
$stmt = $pdo->prepare("SELECT id, title, status, created_at FROM requests $where ORDER BY created_at DESC LIMIT 6");
$stmt->execute($params);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [
    'pending'   => ['warning',   'Pending'],
    'in_review' => ['info',      'In review'],
    'approved'  => ['primary',   'Approved'],
    'completed' => ['success',   'Completed'],
    'rejected'  => ['danger',    'Rejected'],
];

$steps = $isAdmin
    ? ['Review submitted requests', 'Verify uploaded documents', 'Update status and notify the requester', 'Mark the request as completed']
    : ['Create a request', 'Upload the requirements', 'Wait for ELIA Office review', 'Track updates and results'];
?>

<!-- Hero -->
<section class="dash-hero mb-4">
    <div>
        <p class="eyebrow mb-1"><?= escape(ucfirst($user['role'])) ?> dashboard</p>
        <h1 class="h3 mb-1">Welcome back, <?= escape($user['full_name']) ?></h1>
        <p class="mb-0 opacity-75">OMSC Internationalization Affairs workspace</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (!$isAdmin): ?>
            <a class="btn btn-light" href="<?= escape($createUrl) ?>"><i class="bi bi-plus-lg me-1"></i>New request</a>
        <?php endif; ?>
        <a class="btn btn-outline-light" href="<?= escape($requestsUrl) ?>">
            <?= $isAdmin ? 'Review requests' : 'My requests' ?>
        </a>
    </div>
</section>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php foreach ($stats as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-xl-3">
            <div class="card stat-card accent-<?= $color ?> h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-secondary small text-uppercase fw-semibold"><?= escape($label) ?></div>
                        <div class="stat-value"><?= $value ?></div>
                    </div>
                    <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Recent requests -->
    <div class="col-lg-8">
        <section class="card panel h-100" aria-labelledby="recent-heading">
            <div class="panel-head">
                <h2 class="h6 mb-0" id="recent-heading">Recent requests</h2>
                <a class="small text-decoration-none" href="<?= escape($requestsUrl) ?>">View all <i class="bi bi-arrow-right"></i></a>
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
                                <th>Submitted</th>
                                <th class="pe-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r):
                                [$color, $text] = $statusMap[$r['status']] ?? ['secondary', ucfirst($r['status'])]; ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= escape($r['title']) ?></td>
                                    <td><span class="badge rounded-pill text-bg-<?= $color ?>"><?= escape($text) ?></span></td>
                                    <td class="text-secondary small"><?= escape(date('M j, Y', strtotime($r['created_at']))) ?></td>
                                    <td class="pe-4 text-end">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= escape(url($user['role'] . '/requests/view.php?id=' . (int) $r['id'])) ?>">Open</a>
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
        <section class="card panel" aria-labelledby="progress-heading">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0" id="progress-heading">Completion rate</h2>
                    <span class="fw-bold"><?= $rate ?>%</span>
                </div>
                <div class="progress" role="progressbar" aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                </div>
                <p class="text-secondary small mt-2 mb-0"><?= $completed ?> of <?= $total ?> requests completed</p>
            </div>
        </section>

        <section class="card panel flex-fill" aria-labelledby="guide-heading">
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
.dash-hero {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem;
  padding: 1.75rem 2rem; border-radius: 1rem; color: #fff;
  background: linear-gradient(135deg, var(--bs-primary) 0%, #0b3d2e 100%);
}
.dash-hero .eyebrow { color: rgba(255,255,255,.7); }
.eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: .75rem; font-weight: 600; }

.card.stat-card, .card.panel { border: 1px solid var(--bs-border-color-translucent); border-radius: .875rem; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.stat-card { border-left: 4px solid var(--accent) !important; transition: transform .15s, box-shadow .15s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08); }
.accent-primary { --accent: var(--bs-primary); }
.accent-warning { --accent: var(--bs-warning); }
.accent-info    { --accent: var(--bs-info); }
.accent-success { --accent: var(--bs-success); }
.stat-value { font-size: 2rem; font-weight: 700; line-height: 1.1; }
.stat-icon { width: 48px; height: 48px; display: grid; place-items: center; border-radius: 12px; font-size: 1.3rem; }

.panel-head { display: flex; justify-content: space-between; align-items: center; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
.panel .table thead th { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); border-bottom: 0; }
.progress { height: .6rem; border-radius: 1rem; }
.step-num { flex: 0 0 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; font-size: .8rem; font-weight: 600; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); }
</style>