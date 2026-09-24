<?php
declare(strict_types=1);
// Root file: C:\xampp\htdocs\elia-system\login.php  (the FORM page, not the handler)
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$site = app_config();

// The POST handler that calls attempt_login().
const LOGIN_ACTION = 'actions/login.php';

// Flash message: read once, then clear.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashType = in_array($flash['type'] ?? '', ['danger', 'success', 'warning', 'info'], true)
    ? $flash['type'] : 'info';

// Email kept by the handler after a failed attempt or after registration.
$oldEmail = is_string($_SESSION['login_email'] ?? null) ? $_SESSION['login_email'] : '';
unset($_SESSION['login_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in | <?= escape($site['site_name']) ?> | <?= escape($site['system_name']) ?></title>

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
        <li class="nav-item"><a class="nav-link" href="<?= escape(url('client/register.php')) ?>">Register</a></li>
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
        <h1 class="mt-2 mb-3"><?= escape($site['system_name']) ?></h1>
        <p class="lead mb-4">
          Sign in to submit requests, upload requirements, and track the status of your
          ELIA transactions in one place.
        </p>
        <ul class="login-points list-unstyled mb-0">
          <li><span class="login-dot" aria-hidden="true"></span>Conference and meeting requests</li>
          <li><span class="login-dot" aria-hidden="true"></span>Referendum / BOT submissions</li>
          <li><span class="login-dot" aria-hidden="true"></span>Pre-departure and post-travel requirements</li>
        </ul>
      </div>

      <!-- Right: sign-in card -->
      <div class="col-lg-6 col-xl-5 ms-xl-auto">
        <div class="login-card">
          <div class="section-label">Welcome back</div>
          <h2 class="login-title mb-0">Sign in to your account</h2>
          <div class="divider-gold"></div>

          <?php if ($flash): ?>
            <div class="alert alert-<?= escape($flashType) ?> login-alert" role="<?= $flashType === 'danger' ? 'alert' : 'status' ?>">
              <?= escape((string) ($flash['message'] ?? '')) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= escape(url(LOGIN_ACTION)) ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label for="email" class="form-label">Email address</label>
              <input type="email" class="form-control form-control-lg" id="email" name="email"
                     value="<?= escape($oldEmail) ?>" maxlength="254"
                     autocomplete="username" inputmode="email" required
                     <?= $oldEmail === '' ? 'autofocus' : '' ?>>
            </div>

            <div class="mb-4">
              <label for="password" class="form-label">Password</label>
              <div class="input-group input-group-lg">
                <input type="password" class="form-control" id="password" name="password"
                       maxlength="72" autocomplete="current-password" required
                       <?= $oldEmail !== '' ? 'autofocus' : '' ?>>
                <button class="btn login-toggle" type="button" data-toggle-password="password"
                        aria-controls="password" aria-pressed="false">Show</button>
              </div>
            </div>

            <button type="submit" class="btn btn-gold btn-lg w-100">Sign In</button>
          </form>

          <hr class="login-rule">
          <p class="mb-2 login-note">
            New here?
            <a href="<?= escape(url('client/register.php')) ?>">Create a Client account</a>
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
          <li class="mb-2"><a href="tel:+63434570231"><?= escape($site['support_phone']) ?></a></li>
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
</script>
</body>
</html>