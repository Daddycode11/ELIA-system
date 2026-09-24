<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_guest();
$pageTitle = 'Sign in';
$oldEmail = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_email']);
require __DIR__ . '/includes/header.php';
?>
<div class="login-panel card border-0 shadow-sm mx-auto">
    <div class="card-body p-4 p-md-5">
        <p class="eyebrow">OMSC MAIN CAMPUS</p>
        <h1 class="h3 mb-2">Welcome to ELIA</h1>
        <p class="text-secondary mb-4">Sign in to the Internationalization Affairs system.</p>
        <form method="post" action="<?= escape(url('actions/login.php')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="254" autocomplete="username" value="<?= escape($oldEmail) ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" maxlength="72" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Sign in</button>
        </form>
        <p class="small text-secondary mt-4 mb-0">New here? <a href="<?= escape(url('register.php')) ?>">Create Account</a></p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
