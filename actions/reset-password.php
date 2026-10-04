<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/password-recovery.php';
require_post();
verify_csrf();
try {
    reset_account_password((string) ($_SESSION['recovery_token'] ?? ''), post_string('password'), post_string('password_confirmation'));
} catch (DomainException $exception) {
    flash('danger', $exception->getMessage());
    redirect('reset-password.php');
}
destroy_session();
flash('success', 'Your password has been reset. Sign in with your new password.');
redirect('login.php');
