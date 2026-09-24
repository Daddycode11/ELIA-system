<?php
$authUser = current_user();
$itemClass = $authLinksInNavbar ? 'nav-item mt-2 mt-lg-0' : 'mb-2';
$secondaryClass = $authLinksInNavbar ? 'btn btn-outline-light-custom btn-sm px-3 me-2' : '';
$primaryClass = $authLinksInNavbar ? 'btn btn-gold btn-sm px-3' : '';
?>
<?php if ($authUser): ?>
    <li class="<?= $itemClass ?>"><a href="<?= escape(url(dashboard_path($authUser['role']))) ?>" class="<?= $secondaryClass ?>">Dashboard</a></li>
    <li class="<?= $itemClass ?>"><form method="post" action="<?= escape(url('actions/logout.php')) ?>"><?= csrf_field() ?><button type="submit" class="<?= $authLinksInNavbar ? $primaryClass : 'footer-logout' ?>">Logout</button></form></li>
<?php else: ?>
    <li class="<?= $itemClass ?>"><a href="<?= escape(url('login.php')) ?>" class="<?= $secondaryClass ?>">Login</a></li>
    <li class="<?= $itemClass ?>"><a href="<?= escape(url('register.php')) ?>" class="<?= $primaryClass ?>">Create Account</a></li>
<?php endif; ?>
