<?php
declare(strict_types=1);
/** Shared helpers for the admin announcement pages. */

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Admin gate: uses requireAdmin() if your project defines it, otherwise require_role('admin'). */
function announcement_admin(): array
{
    if (function_exists('requireAdmin')) {
        requireAdmin();
    } else {
        require_role('admin');
    }
    return current_user();
}

/** CSRF hidden input. Adjust if your functions.php uses different names. */
function announcement_csrf_field(): string
{
    if (function_exists('csrf_field')) {
        return (string) csrf_field();
    }
    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
    return '';
}