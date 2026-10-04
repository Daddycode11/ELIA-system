<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/password-recovery.php';
require_post();
verify_csrf();
$started = microtime(true);
request_password_recovery(post_string('email'), (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
// A minimum response time reduces simple lookup timing differences; do not trust forwarded IPs.
$remaining = 500000 - (int) ((microtime(true) - $started) * 1000000);
if ($remaining > 0) { usleep($remaining); }
flash('success', RECOVERY_MESSAGE);
redirect('forgot-password.php');
