<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/password-recovery.php';
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
if (isset($_GET['token'])) {
    $token = is_string($_GET['token']) ? $_GET['token'] : '';
    $_SESSION['recovery_token'] = preg_match('/\A[a-f0-9]{64}\z/D', $token) ? $token : '';
    // Remove the bearer token from the URL before loading any assets or navigation.
    redirect('reset-password.php');
}
$resetMode = true;
$validReset = recovery_token_record((string) ($_SESSION['recovery_token'] ?? '')) !== null;
require __DIR__ . '/includes/recovery-view.php';
