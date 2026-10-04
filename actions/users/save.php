<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/users.php';
$actor = requireAdmin();
require_post();
verify_csrf();
$id = filter_var(post_string('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($id === false) {
    http_response_code(400);
    exit('Invalid account ID.');
}
$input = [
    'full_name' => trim(post_string('full_name')),
    'email' => strtolower(trim(post_string('email'))),
    'role' => post_string('role'),
    'is_active' => post_string('is_active'),
    'password' => post_string('password'),
    'password_confirmation' => post_string('password_confirmation'),
    'version' => post_string('version'),
];
try {
    $savedId = save_managed_user((int) $actor['id'], $id, $input);
    unset($_SESSION['user_form']);
    flash('success', $id ? 'Account updated.' : 'Account created.');
    redirect('admin/users/form.php?id=' . $savedId);
} catch (DomainException $exception) {
    // Never retain passwords in flash/session data.
    unset($input['password'], $input['password_confirmation']);
    $_SESSION['user_form'] = ['id' => $id, 'values' => $input];
    flash('danger', $exception->getMessage());
    redirect('admin/users/form.php' . ($id ? '?id=' . $id : ''));
}
