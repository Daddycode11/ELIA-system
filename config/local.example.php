<?php
// Copy to local.php and set deployment-specific values. Do not commit local.php.
return [
    'base_path' => '', // Use '' when hosted at the domain root.
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'elia_system',
    'db_user' => 'elia_app',
    'db_password' => 'replace-with-your-database-password',
    // For production, set document_storage to a writable path outside the web root.
    // 'document_storage' => 'C:/elia-private/documents',
];
