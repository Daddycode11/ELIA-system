<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();
$email = strtolower(trim(post_string('email')));
$password = post_string('password');
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $password === '' || strlen($password) > 72) {
    flash('danger', 'Enter a valid email address and password.');
    redirect('login.php');
}
if (!attempt_login($email, $password)) {
    $_SESSION['login_email'] = $email;
    flash('danger', 'Unable to sign in. Check your credentials or contact the ELIA Office. After repeated attempts, wait 15 minutes before trying again.');
    redirect('login.php');
}
// Read the role from the database, never from submitted form fields.
$user = current_user();
redirect(dashboard_path($user['role']));
