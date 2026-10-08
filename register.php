<?php
declare(strict_types=1);
// Root file: C:\xampp\htdocs\elia-system\register.php  (the FORM page, not the handler)
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$site = app_config();
const REGISTER_ACTION = 'actions/register.php';

// Flash message: read once, then clear.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashType = in_array($flash['type'] ?? '', ['danger', 'success', 'warning', 'info'], true) ? $flash['type'] : 'info';

// Values kept by the handler after a failed submission (passwords are never kept).
$old = is_array($_SESSION['registration_old'] ?? null) ? $_SESSION['registration_old'] : [];
unset($_SESSION['registration_old']);
$oldName  = is_string($old['full_name'] ?? null) ? $old['full_name'] : '';
$oldEmail = is_string($old['email'] ?? null) ? $old['email'] : '';

$authTitle = 'Create account';
$authAlt = ['label' => 'Sign in', 'href' => 'login.php'];
$authExtraScript = <<<'JS'
<script>
(function () {
  var pw = document.getElementById('password'), c = document.getElementById('password_confirmation');
  function check() { c.setCustomValidity(c.value !== '' && c.value !== pw.value ? 'Passwords do not match.' : ''); }
  pw.addEventListener('input', check); c.addEventListener('input', check);
})();
</script>
JS;
require __DIR__ . '/includes/auth_top.php';
?>
  <section class="au-side" aria-labelledby="side-title">
    <img class="au-bg" src="<?= escape(url('assets/img/OMSC_thumbnail.jpg')) ?>" alt="" fetchpriority="high">
    <div>
      <div class="au-kicker">External Linkages and International Affairs</div>
      <h1 id="side-title">File once. Follow it to completion.</h1>
      <p class="au-lede">Faculty and authorized users can register to file requests with the ELIA Office and track each one from submission to approval.</p>
      <ol class="au-steps" aria-label="What a client account lets you do">
        <li>Submit conference, meeting, and Referendum / BOT requests</li>
        <li>Upload required documents in one place</li>
        <li>Track status and get notified of every update</li>
      </ol>
    </div>
  </section>

  <section class="au-main" aria-labelledby="form-title">
    <div class="au-card">
      <h2 id="form-title">Create your client account</h2>
      <hr class="au-rule">

      <?php if ($flash): ?>
        <div class="au-alert <?= escape($flashType) ?>" role="<?= $flashType === 'danger' ? 'alert' : 'status' ?>"><?= escape((string) ($flash['message'] ?? '')) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= escape(url(REGISTER_ACTION)) ?>" id="register-form">
        <?= csrf_field() ?>
        <div class="au-field">
          <label for="full_name">Full name</label>
          <input class="au-input" type="text" id="full_name" name="full_name" value="<?= escape($oldName) ?>" maxlength="150"
                 autocomplete="name" required <?= $oldName === '' ? 'autofocus' : '' ?>>
        </div>
        <div class="au-field">
          <label for="email">Email address</label>
          <input class="au-input" type="email" id="email" name="email" value="<?= escape($oldEmail) ?>" maxlength="254"
                 autocomplete="username" inputmode="email" required>
        </div>
        <div class="au-field">
          <label for="password">Password</label>
          <div class="au-pw">
            <input class="au-input" type="password" id="password" name="password" minlength="12" maxlength="72"
                   autocomplete="new-password" aria-describedby="password-help" required>
            <button class="au-toggle" type="button" data-toggle-password="password" aria-controls="password" aria-pressed="false">Show</button>
          </div>
          <div id="password-help" class="au-hint">Use 12–72 bytes. Some characters use more than one byte.</div>
        </div>
        <div class="au-field" style="margin-bottom:1.5rem">
          <label for="password_confirmation">Confirm password</label>
          <div class="au-pw">
            <input class="au-input" type="password" id="password_confirmation" name="password_confirmation" minlength="12" maxlength="72"
                   autocomplete="new-password" required>
            <button class="au-toggle" type="button" data-toggle-password="password_confirmation" aria-controls="password_confirmation" aria-pressed="false">Show</button>
          </div>
        </div>
        <button type="submit" class="el-btn el-btn-gold el-btn-block">Create account</button>
      </form>

      <div class="au-alt">
        <p>Already registered? <a href="<?= escape(url('login.php')) ?>">Sign in</a></p>
      </div>
    </div>
  </section>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>