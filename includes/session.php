<?php
declare(strict_types=1);

function initialize_session(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('elia_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => url(''),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Unable to initialize session storage.');
    }
    if (isset($_SESSION['user_id'], $_SESSION['last_activity'])
        && time() - $_SESSION['last_activity'] > app_config()['session_idle_seconds']) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('warning', 'Your session expired. Please sign in again.');
    }
    $_SESSION['last_activity'] = time();
}

function destroy_session(): void
{
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'secure' => $cookie['secure'],
        'httponly' => true,
        'samesite' => $cookie['samesite'],
    ]);
    session_destroy();
}
