<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/registration.php';
require_post();
verify_csrf();
require_guest();

$name = trim(post_string('full_name'));
$email = strtolower(trim(post_string('email')));
$password = post_string('password');
$errors = registration_errors($name, $email, $password, post_string('password_confirmation'));
if ($errors) {
    $_SESSION['registration_old'] = ['full_name' => mb_substr($name, 0, 150), 'email' => substr($email, 0, 254)];
    flash('danger', implode(' ', $errors));
    redirect('register.php');
}

if (!register_client($name, $email, $password)) {
    flash('danger', 'Unable to create this account. Try another email address or sign in if you already have an account.');
    redirect('register.php');
}
unset($_SESSION['registration_old']);
$_SESSION['login_email'] = $email;
flash('success', 'Your Client account has been created. You can now sign in.');
redirect('login.php');
