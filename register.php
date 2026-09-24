<?php
declare(strict_types=1);
// Root file: C:\xampp\htdocs\elia-system\register.php  (the FORM page, not the handler)
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$site = app_config();

// The POST handler that creates the account.
const REGISTER_ACTION = 'actions/register.php';

// Flash message: read once, then clear.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashType = in_array($flash['type'] ?? '', ['danger', 'success', 'warning', 'info'], true)
    ? $flash['type'] : 'info';

// Values kept by the handler after a failed submission (passwords are never kept).
$old = is_array($_SESSION['registration_old'] ?? null) ? $_SESSION['registration_old'] : [];
unset($_SESSION['registration_old']);
$oldName  = is_string($old['full_name'] ?? null) ? $old['full_name'] : '';
$oldEmail = is_string($old['email'] ?? null) ? $old['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account | <?= escape($site['site_name']) ?> | <?= escape($site['system_name']) ?></title>

<?php require __DIR__ . '/includes/styles.php'; ?>
<link rel="stylesheet" href="<?= escape(url('assets/css/landing.css')) ?>">
<link rel="stylesheet" href="<?= escape(url('assets/css/login.css')) ?>">
</head>
<body><a class="visually-hidden-focusable landing-skip" href="#main-content">Skip to content</a>

<nav class="navbar navbar-expand-xl py-3" data-bs-theme="dark" aria-label="Main navigation">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= escape(url('/')) ?>">
      <?= escape($site['site_name']) ?> <span class="brand-campus">| OMSU</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navMain">
      <ul class="navbar-nav align-items-xl-center gap-xl-3">
        <li class="nav-item"><a class="nav-link" href="<?= escape(url('/')) ?>">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= escape(url('/#about')) ?>">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= escape(url('/#process')) ?>">How It Works</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= escape(url('login.php')) ?>">Sign In</a></li>
      </ul>
    </div>
  </div>
</nav>

<main id="main-content" tabindex="-1">
<div class="login-stage">
  <div class="container">
    <div class="row align-items-center g-5">

      <!-- Left: branding, mirrors the landing hero -->
      <div class="col-lg-6 login-intro">
        <img src="<?= escape(url('assets/img/elia-logo.png')) ?>" alt="OMSC External Linkages and International Affairs Office logo" class="login-logo">
        <div class="eyebrow">External Linkages and International Affairs Office</div>
        <h1 class="mt-2 mb-3">Create your Client account</h1>
        <p class="lead mb-4">
          Faculty and authorized users can register to file requests with the ELIA Office
          and follow each one from submission to completion.
        </p>
        <ul class="login-points list-unstyled mb-0">
          <li><span class="login-dot" aria-hidden="true"></span>Submit conference, meeting, and Referendum / BOT requests</li>
          <li><span class="login-dot" aria-hidden="true"></span>Upload required documents in one place</li>
          <li><span class="login-dot" aria-hidden="true"></span>Track status and get notified of updates</li>
        </ul>
      </div>

      <!-- Right: registration card -->
      <div class="col-lg-6 col-xl-5 ms-xl-auto">
        <div class="login-card">
          <div class="section-label">Get started</div>
          <h2 class="login-title mb-0">Create Account</h2>
          <div class="divider-gold"></div>

          <?php if ($flash): ?>
            <div class="alert alert-<?= escape($flashType) ?> login-alert" role="<?= $flashType === 'danger' ? 'alert' : 'status' ?>">
              <?= escape((string) ($flash['message'] ?? '')) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= escape(url(REGISTER_ACTION)) ?>" id="register-form">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label for="full_name" class="form-label">Full name</label>
              <input type="text" class="form-control form-control-lg" id="full_name" name="full_name"
                     value="<?= escape($oldName) ?>" maxlength="150" autocomplete="name" required
                     <?= $oldName === '' ? 'autofocus' : '' ?>>
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input type="email" class="form-control form-control-lg" id="email" name="email"
                     value="<?= escape($oldEmail) ?>" maxlength="254"
                     autocomplete="username" inputmode="email" required>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <div class="input-group input-group-lg">
                <input type="password" class="form-control" id="password" name="password"
                       minlength="12" maxlength="72" autocomplete="new-password"
                       aria-describedby="password-help" required>
                <button class="btn login-toggle" type="button" data-toggle-password="password"
                        aria-controls="password" aria-pressed="false">Show</button>
              </div>
              <div id="password-help" class="form-text">
                Use 12–72 bytes. Some characters use more than one byte.
              </div>
            </div>

            <div class="mb-4">
              <label for="password_confirmation" class="form-label">Confirm password</label>
              <div class="input-group input-group-lg">
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                       minlength="12" maxlength="72" autocomplete="new-password" required>
                <button class="btn login-toggle" type="button" data-toggle-password="password_confirmation"
                        aria-controls="password_confirmation" aria-pressed="false">Show</button>
              </div>
            </div>

            <button type="submit" class="btn btn-gold btn-lg w-100">Create Account</button>
          </form>

          <hr class="login-rule">
          <p class="mb-2 login-note">
            Already registered?
            <a href="<?= escape(url('login.php')) ?>">Sign in</a>
          </p>
          <p class="mb-0 login-note">
            Need help? Contact the ELIA Office at
            <a href="mailto:<?= escape($site['support_email']) ?>"><?= escape($site['support_email']) ?></a>.
          </p>
        </div>
      </div>

    </div>
  </div>
</div>
</main>

<footer>
  <div class="container">
    <div class="row g-4">
      <div class="col-md-7">
        <h2 class="footer-heading"><?= escape($site['site_name']) ?> — <?= escape($site['office_name']) ?></h2>
        <p class="mb-0 footer-address">
          <?= escape($site['campus']) ?><br>
          San Jose, Occidental Mindoro, Philippines
        </p>
      </div>
      <div class="col-md-5">
        <h2 class="footer-heading">Contact</h2>
        <ul class="list-unstyled footer-list">
          <li class="mb-2"><a href="mailto:<?= escape($site['support_email']) ?>"><?= escape($site['support_email']) ?></a></li>
          <li class="mb-2"><a href="tel:<?= escape(preg_replace('/[^\d+]/', '', $site['support_phone'])) ?>"><?= escape($site['support_phone']) ?></a></li>
        </ul>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="footer-copyright">
      &copy; <?= date('Y') ?> <?= escape($site['office_name']) ?> Office — Occidental Mindoro State College.
    </div>
  </div>
</footer>

<script src="<?= escape(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script>
// Show / hide password
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.togglePassword);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
});

// Tell the user right away if the two passwords differ (the server checks again).
(function () {
  var pw = document.getElementById('password');
  var confirm = document.getElementById('password_confirmation');
  function check() {
    confirm.setCustomValidity(confirm.value !== '' && confirm.value !== pw.value
      ? 'Passwords do not match.' : '');
  }
  pw.addEventListener('input', check);
  confirm.addEventListener('input', check);
})();
</script>
</body>
</html>