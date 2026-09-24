<?php
declare(strict_types=1);

/**
 * Loaded first by every page and handler.
 * Order matters: functions -> session -> database -> auth.
 */

require_once __DIR__ . '/functions.php';

/* ---------- Session ---------- */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookiePath = rtrim((string) (app_config()['base_path'] ?? ''), '/') . '/';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Sign out users who have been idle too long (attempt_login() sets last_activity).
if (isset($_SESSION['user_id'])) {
    $idleLimit = (int) (app_config()['session_idle_seconds'] ?? 1800);
    $lastSeen  = (int) ($_SESSION['last_activity'] ?? 0);

    if ($lastSeen > 0 && time() - $lastSeen > $idleLimit) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('warning', 'Your session expired. Please sign in again.');
    } else {
        $_SESSION['last_activity'] = time();
    }
}

/* ---------- Database ---------- */

// Load a project database file if one exists (it may already define database()).
foreach (['database.php', 'db.php'] as $databaseFile) {
    if (is_file(__DIR__ . '/' . $databaseFile)) {
        require_once __DIR__ . '/' . $databaseFile;
    }
}

// Fallback so attempt_login() and current_user() always have a connection.
if (!function_exists('database')) {
    function database(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $config = app_config();
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['db_host'],
                $config['db_port'],
                $config['db_name']
            );
            $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return $pdo;
    }
}

/* ---------- Authentication ---------- */

// current_user(), require_guest(), require_role(), attempt_login(), dashboard_path()
require_once __DIR__ . '/auth.php';