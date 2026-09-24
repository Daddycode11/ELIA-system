<?php
declare(strict_types=1);

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = post_string('csrf_token');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Your form has expired. Go back, refresh the page, and try again.');
    }
}
