<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/users.php';
$user = requireAdmin();
$pageTitle = 'User management';
$search = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 150) : '';
$role = isset($_GET['role']) && is_string($_GET['role']) && in_array($_GET['role'], ['admin', 'client'], true) ? $_GET['role'] : '';
$status = isset($_GET['status']) && is_string($_GET['status']) && in_array($_GET['status'], ['active', 'inactive'], true) ? $_GET['status'] : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$result = managed_users($search, $role, $status, $page);
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><p class="eyebrow">Administration</p><h1 class="h3">User management</h1><p class="text-secondary mb-0">Manage accounts and access to the ELIA system.</p></div>
    <a class="btn btn-primary" href="<?= escape(url('admin/users/form.php')) ?>">Create account</a>
</div>
<form method="get" class="card card-body mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label" for="search">Name or email</label><input class="form-control" type="search" id="search" name="q" maxlength="150" value="<?= escape($search) ?>"></div>
        <div class="col-md-2"><label class="form-label" for="role">Role</label><select class="form-select" name="role" id="role"><option value="">All roles</option><?php foreach (['admin', 'client'] as $option): ?><option value="<?= $option ?>" <?= $role === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label" for="status">Status</label><select class="form-select" name="status" id="status"><option value="">All statuses</option><?php foreach (['active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary" href="<?= escape(url('admin/users/index.php')) ?>">Reset</a></div>
    </div>
</form>
<p class="text-secondary"><?= $result['total'] ?> account(s). Last login indicates a successful sign-in, not online presence.</p>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th scope="col">Account</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last login</th><th scope="col">Action</th></tr></thead>
    <tbody><?php foreach ($result['rows'] as $account): ?><tr>
        <td><strong><?= escape($account['full_name']) ?></strong><br><span class="text-secondary"><?= escape($account['email']) ?></span></td>
        <td><?= escape(ucfirst($account['role'])) ?></td><td><span class="badge <?= $account['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $account['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        <td><?= escape($account['last_login_at'] ?? 'Never') ?></td><td><a href="<?= escape(url('admin/users/form.php?id=' . (int) $account['id'])) ?>" aria-label="<?= escape('View or edit ' . $account['full_name']) ?>">View / Edit</a></td>
    </tr><?php endforeach; ?>
    <?php if (!$result['rows']): ?><tr><td colspan="5" class="text-center text-secondary p-4">No matching accounts.</td></tr><?php endif; ?></tbody>
</table></div></div>
<nav aria-label="Account pages" class="d-flex justify-content-between align-items-center mt-3">
    <span>Page <?= $result['page'] ?> of <?= $result['pages'] ?></span><div class="d-flex gap-2">
    <?php foreach ([-1 => 'Previous', 1 => 'Next'] as $step => $label): $next = $result['page'] + $step; if ($next >= 1 && $next <= $result['pages']): ?>
    <a class="btn btn-outline-primary" href="<?= escape(url('admin/users/index.php?' . http_build_query(['q' => $search, 'role' => $role, 'status' => $status, 'page' => $next]))) ?>"><?= $label ?></a>
    <?php endif; endforeach; ?></div>
</nav>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
