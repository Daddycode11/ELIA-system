<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/../config/app.php';
}

function escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path): string
{
    return rtrim(app_config()['base_path'], '/') . '/' . ltrim($path, '/');
}

/** Takes a path relative to the app root; url() adds the base path. */
function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('This action requires a form submission.');
    }
}

function post_string(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

/* ---------- Flash messages ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Read and clear the flash message. Call once in your layout/view. */
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Use inside every POST form: <?= csrf_field() ?> */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = post_string('csrf_token');
    $real = $_SESSION['csrf_token'] ?? '';

    if ($sent === '' || !is_string($real) || $real === '' || !hash_equals($real, $sent)) {
        http_response_code(419);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}