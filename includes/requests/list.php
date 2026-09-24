<?php
require_once __DIR__ . '/workflow.php';
$filters = [];
foreach (['search','status','type','client','from','to'] as $key) { $filters[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(trim($_GET[$key]), 0, 200) : ''; }
$page = isset($_GET['page']) && is_scalar($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$result = request_list($user, $filters, $page);
$pageTitle = $user['role'] === 'admin' ? 'Request management' : 'My requests';
$types = request_types(false);
require __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between gap-3 flex-wrap mb-4"><h1 class="h3"><?= escape($pageTitle) ?></h1>
<?php if ($user['role'] === 'client'): ?><a class="btn btn-primary" href="<?= escape(url('client/requests/create.php')) ?>">Create request</a><?php endif; ?></div>
<form method="get" class="card card-body mb-4"><div class="row g-3">
    <div class="col-md-4"><label for="search" class="form-label">Search title or reference</label><input id="search" class="form-control" name="search" maxlength="200" value="<?= escape($filters['search']) ?>"></div>
    <div class="col-md-4"><label for="type" class="form-label">Request type</label><select id="type" class="form-select" name="type"><option value="">All types</option><?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= $filters['type'] === (string) $type['id'] ? 'selected' : '' ?>><?= escape($type['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label for="status" class="form-label">Status</label><select id="status" class="form-select" name="status"><option value="">All statuses</option><?php foreach (request_statuses() as $status): ?><option <?= $filters['status'] === $status ? 'selected' : '' ?> value="<?= escape($status) ?>"><?= escape(request_label($status)) ?></option><?php endforeach; ?></select></div>
    <?php if ($user['role'] === 'admin'): ?><div class="col-md-4"><label for="client" class="form-label">Client name or email</label><input id="client" class="form-control" name="client" maxlength="200" value="<?= escape($filters['client']) ?>"></div><?php endif; ?>
    <div class="col-md-4"><label for="from" class="form-label">Submitted from</label><input id="from" type="date" class="form-control" name="from" value="<?= escape($filters['from']) ?>"></div>
    <div class="col-md-4"><label for="to" class="form-label">Submitted through</label><input id="to" type="date" class="form-control" name="to" value="<?= escape($filters['to']) ?>"></div>
</div><div class="mt-3"><button class="btn btn-primary" type="submit">Apply filters</button> <a class="btn btn-outline-secondary" href="<?= escape(url($user['role'] . '/requests/index.php')) ?>">Clear</a></div></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Reference</th><?php if ($user['role'] === 'admin'): ?><th>Client</th><?php endif; ?><th>Request type / title</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($result['rows'] as $row): ?><tr>
    <td><?= escape($row['reference_no'] ?? 'Draft #' . $row['id']) ?></td>
    <?php if ($user['role'] === 'admin'): ?><td><?= escape($row['client_name']) ?></td><?php endif; ?>
    <td><?= escape($row['type_name']) ?><div class="small text-secondary"><?= escape($row['title'] ?: 'Untitled draft') ?></div></td>
    <td><?= escape($row['submitted_at'] ?? 'Not submitted') ?></td><td><?= request_badge($row['status']) ?></td>
    <td><a aria-label="View <?= escape($row['title'] ?: 'draft request') ?>" href="<?= escape(url($user['role'] . '/requests/view.php?id=' . $row['id'])) ?>">View</a></td>
</tr><?php endforeach; ?>
<?php if (!$result['rows']): ?><tr><td colspan="6" class="p-4 text-center text-secondary">No requests match these filters.</td></tr><?php endif; ?>
</tbody></table></div></div>
<nav class="d-flex gap-3 align-items-center mt-3" aria-label="Request pages">
    <?php if ($result['page'] > 1): ?><a class="btn btn-outline-secondary" href="?<?= escape(http_build_query($filters + ['page' => $result['page'] - 1])) ?>">Previous</a><?php endif; ?>
    <span>Page <?= $result['page'] ?> of <?= $result['pages'] ?> · <?= $result['total'] ?> requests</span>
    <?php if ($result['page'] < $result['pages']): ?><a class="btn btn-outline-secondary" href="?<?= escape(http_build_query($filters + ['page' => $result['page'] + 1])) ?>">Next</a><?php endif; ?>
</nav>
<?php require __DIR__ . '/../footer.php'; ?>
