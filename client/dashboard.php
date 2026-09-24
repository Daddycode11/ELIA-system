<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$user = requireClient();
$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/dashboard.php';
require __DIR__ . '/../includes/footer.php';
