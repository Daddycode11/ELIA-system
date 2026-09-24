<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();
$pageTitle = 'Create Account';
$old = $_SESSION['registration_old'] ?? [];
unset($_SESSION['registration_old']);
require __DIR__ . '/includes/header.php';
?>
<div class="login-panel card border-0 shadow-sm mx-auto">
    <div class="card-body p-4 p-md-5">
        <p class="eyebrow">OMSC MAIN CAMPUS</p>
        <h1 class="h3 mb-2">Create Account</h1>
        <p class="text-secondary mb-4">Create your Client account for the ELIA system.</p>
        <form method="post" action="<?= escape(url('actions/register.php')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="full_name">Full name</label>
                <input class="form-control" id="full_name" name="full_name" maxlength="150" autocomplete="name" value="<?= escape($old['full_name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="username" value="<?= escape($old['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required>
                <div id="password-help" class="form-text">Use 12–72 bytes. Some characters use more than one byte.</div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Create Account</button>
        </form>
        <p class="small text-secondary mt-4 mb-0">Already registered? <a href="<?= escape(url('login.php')) ?>">Login</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
