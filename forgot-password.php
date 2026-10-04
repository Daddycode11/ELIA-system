<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$resetMode = false;
$validReset = false;
require __DIR__ . '/includes/recovery-view.php';
