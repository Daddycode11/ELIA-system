<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/users.php';
$user = requireAdmin();
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : 0;
$account = $id ? managed_user($id) : null;
if ($id === false || ($id && !$account)) {
    http_response_code(404);
    exit('Account not found.');
}
$values = $account ?? ['full_name' => '', 'email' => '', 'role' => 'client', 'is_active' => 1];
$values['version'] = $account ? managed_user_version($account) : '';
$old = $_SESSION['user_form'] ?? null;
unset($_SESSION['user_form']);
if ($old && $old['id'] === $id) {
    $values = array_replace($values, $old['values']);
}
$self = $id === (int) $user['id'];
$pageTitle = 'User management';
require __DIR__ . '/../../includes/header.php';
?>
<a href="<?= escape(url('admin/users/index.php')) ?>" class="d-inline-block mb-3">Back to accounts</a>
<h1 class="h3 mb-3"><?= $id ? 'Account details' : 'Create account' ?></h1>
<?php if ($account): ?><p class="text-secondary">Created: <?= escape($account['created_at']) ?> · Last login: <?= escape($account['last_login_at'] ?? 'Never') ?></p><?php endif; ?>
<form method="post" action="<?= escape(url('actions/users/save.php')) ?>" class="card card-body">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="version" value="<?= escape($values['version']) ?>">
    <div class="row g-3">
        <div class="col-md-6"><label for="full-name" class="form-label">Full name</label><input id="full-name" name="full_name" class="form-control" maxlength="150" autocomplete="name" required value="<?= escape($values['full_name']) ?>"></div>
        <div class="col-md-6"><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" class="form-control" maxlength="254" autocomplete="off" required value="<?= escape($values['email']) ?>"></div>
        <div class="col-md-6"><label for="role" class="form-label">Role</label><select id="role" name="role" class="form-select" <?= $self ? 'disabled' : '' ?>><?php foreach (['client', 'admin'] as $role): ?><option value="<?= $role ?>" <?= $values['role'] === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option><?php endforeach; ?></select><?php if ($self): ?><input type="hidden" name="role" value="admin"><?php endif; ?></div>
        <div class="col-md-6"><label for="is-active" class="form-label">Account status</label><select id="is-active" name="is_active" class="form-select" <?= $self ? 'disabled' : '' ?>><option value="1" <?= (string) $values['is_active'] === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= (string) $values['is_active'] === '0' ? 'selected' : '' ?>>Inactive</option></select><?php if ($self): ?><input type="hidden" name="is_active" value="1"><?php endif; ?></div>
        <?php if (!$id): ?>
        <div class="col-md-6"><label for="password" class="form-label">Initial password</label><input type="password" id="password" name="password" class="form-control" autocomplete="new-password" minlength="12" maxlength="72" required aria-describedby="password-help"></div>
        <div class="col-md-6"><label for="confirmation" class="form-label">Confirm password</label><input type="password" id="confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="12" maxlength="72" required></div>
        <div class="col-12"><p id="password-help" class="form-text mb-0">Use 12–72 bytes (at least 12 characters for plain English text). Share the initial credentials privately with the account owner.</p></div>
        <?php endif; ?>
        <div class="col-12"><p class="text-secondary mb-0"><?= $self ? 'Your own role and active status are protected.' : 'Admin accounts can manage users and all ELIA requests. Inactive accounts cannot sign in; existing access is blocked on their next request. Changing a role changes which workspace the user can access.' ?></p></div>
    </div>
    <div class="mt-4 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><?= $id ? 'Save account' : 'Create account' ?></button><a class="btn btn-outline-secondary" href="<?= escape(url('admin/users/form.php' . ($id ? '?id=' . $id : ''))) ?>">Reload account</a></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
