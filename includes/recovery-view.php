<?php
declare(strict_types=1);
$site = app_config();
$title = $resetMode ? 'Choose a new password' : 'Forgot your password?';
$flash = take_flash();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer">
<title><?= escape($title) ?> | <?= escape($site['site_name']) ?></title>
<link rel="stylesheet" href="<?= escape(url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= escape(url('assets/css/landing.css')) ?>">
<link rel="stylesheet" href="<?= escape(url('assets/css/login.css')) ?>">
</head><body>
<nav class="navbar py-3" data-bs-theme="dark" aria-label="Main navigation"><div class="container"><a class="navbar-brand fw-semibold" href="<?= escape(url('index.php')) ?>">ELIA <span class="brand-campus">| OMSC</span></a><a class="nav-link text-white" href="<?= escape(url('login.php')) ?>">Sign in</a></div></nav>
<main id="main-content" class="login-stage"><div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-6">
<div class="login-card"><div class="section-label">Account recovery</div><h1 class="login-title"><?= escape($title) ?></h1><div class="divider-gold"></div>
<?php if ($flash): ?><div class="alert alert-<?= ($flash['type'] ?? '') === 'danger' ? 'danger' : 'success' ?>" role="status"><?= escape($flash['message']) ?></div><?php endif; ?>
<?php if (!$resetMode): ?>
<p>Enter your account email to request a password reset link.</p>
<form method="post" action="<?= escape(url('actions/forgot-password.php')) ?>">
<?= csrf_field() ?><div class="mb-4"><label class="form-label" for="email">Email address</label><input class="form-control form-control-lg" type="email" id="email" name="email" autocomplete="username" maxlength="254" required></div>
<button class="btn btn-gold btn-lg w-100" type="submit">Send reset link</button></form>
<?php elseif ($validReset): ?>
<p>After resetting, sign in again on each device using your new password.</p>
<form method="post" action="<?= escape(url('actions/reset-password.php')) ?>">
<?= csrf_field() ?><div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control form-control-lg" type="password" id="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="password-help" required></div>
<p class="login-note" id="password-help">Use 12–72 bytes (at least 12 characters for plain English text).</p>
<div class="mb-4"><label class="form-label" for="confirmation">Confirm new password</label><input class="form-control form-control-lg" type="password" id="confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required></div>
<button class="btn btn-gold btn-lg w-100" type="submit">Reset password</button></form>
<?php else: ?><div class="alert alert-warning">This reset link is invalid or expired.</div><a class="btn btn-gold" href="<?= escape(url('forgot-password.php')) ?>">Request a new link</a><?php endif; ?>
<hr class="login-rule"><p class="login-note mb-2"><a href="<?= escape(url('login.php')) ?>">Back to sign in</a></p><p class="login-note mb-0">Need help? Contact <a href="mailto:<?= escape($site['support_email']) ?>"><?= escape($site['support_email']) ?></a>.</p>
</div></div></div></div></main>
</body></html>
