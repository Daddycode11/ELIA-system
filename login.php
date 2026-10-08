<?php
declare(strict_types=1);
// Root file: C:\xampp\htdocs\elia-system\login.php  (the FORM page, not the handler)
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$site = app_config();
const LOGIN_ACTION = 'actions/login.php';

// Flash message: read once, then clear.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashType = in_array($flash['type'] ?? '', ['danger', 'success', 'warning', 'info'], true) ? $flash['type'] : 'info';

// Email kept by the handler after a failed attempt or after registration.
$oldEmail = is_string($_SESSION['login_email'] ?? null) ? $_SESSION['login_email'] : '';
unset($_SESSION['login_email']);

$authTitle = 'Sign in';
$authAlt = ['label' => 'Create account', 'href' => 'register.php'];
require __DIR__ . '/includes/auth_top.php';
?>
  <section class="au-side" aria-labelledby="side-title">
    <img class="au-bg" src="<?= escape(url('assets/img/OMSC_thumbnail.jpg')) ?>" alt="" fetchpriority="high">
    <div>
      <div class="au-kicker">External Linkages and International Affairs</div>
      <h1 id="side-title">Pick up your request where you left off.</h1>
      <p class="au-lede">Sign in to submit requests, upload requirements, and follow every step of the ELIA Office review.</p>
      <ol class="au-steps" aria-label="What you can do after signing in">
        <li>Conference and meeting requests</li>
        <li>Referendum / BOT submissions</li>
        <li>Pre-departure and post-travel requirements</li>
      </ol>
    </div>
  </section>

  <section class="au-main" aria-labelledby="form-title">
    <div class="au-card">
      <h2 id="form-title">Sign in to your account</h2>
      <hr class="au-rule">

      <?php if ($flash): ?>
        <div class="au-alert <?= escape($flashType) ?>" role="<?= $flashType === 'danger' ? 'alert' : 'status' ?>"><?= escape((string) ($flash['message'] ?? '')) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= escape(url(LOGIN_ACTION)) ?>">
        <?= csrf_field() ?>
        <div class="au-field">
          <label for="email">Email address</label>
          <input class="au-input" type="email" id="email" name="email" value="<?= escape($oldEmail) ?>" maxlength="254"
                 autocomplete="username" inputmode="email" required <?= $oldEmail === '' ? 'autofocus' : '' ?>>
        </div>
        <div class="au-field">
          <label for="password">Password</label>
          <div class="au-pw">
            <input class="au-input" type="password" id="password" name="password" maxlength="72"
                   autocomplete="current-password" required <?= $oldEmail !== '' ? 'autofocus' : '' ?>>
            <button class="au-toggle" type="button" data-toggle-password="password" aria-controls="password" aria-pressed="false">Show</button>
          </div>
        </div>
        <a class="au-forgot" href="<?= escape(url('forgot-password.php')) ?>">Forgot your password?</a>
        <button type="submit" class="el-btn el-btn-gold el-btn-block">Sign in</button>
      </form>

      <div class="au-alt">
        <p>New here? <a href="<?= escape(url('register.php')) ?>">Create a client account</a></p>
        <p>Client accounts are for faculty, staff, and students.</p>
      </div>
    </div>
  </section>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>