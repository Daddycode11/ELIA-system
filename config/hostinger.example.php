<?php
declare(strict_types=1);

// On Hostinger, copy this file to config/local.php and replace placeholders.
// Keep the completed local.php private; it is excluded from Git.
return [
    'base_path' => '', // Use '/elia-system' if installed in that subfolder.
    'app_url' => 'https://YOUR-DOMAIN.com', // Include the subfolder if applicable.
    'db_host' => 'localhost', // Use the host shown in your hPanel database details.
    'db_port' => '3306',
    'db_name' => 'u123456789_elia', // Full database name, including hPanel prefix.
    'db_user' => 'u123456789_eliauser', // Full database username.
    'db_password' => 'REPLACE_WITH_DATABASE_PASSWORD',
    'timezone' => 'Asia/Manila',
    // Replace with an existing writable directory OUTSIDE public_html.
    'document_storage' => '/home/u123456789/domains/YOUR-DOMAIN.com/elia-private/documents',
    'recovery_mail_enabled' => false,
    'mail_from' => 'no-reply@YOUR-DOMAIN.com',
];
