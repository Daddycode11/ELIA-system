<?php
declare(strict_types=1);

$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];

return array_replace([
    'site_name' => 'ELIA',
    'office_name' => 'External Linkages and International Affairs',
    'system_name' => 'Web-Based Monitoring and Management System for Internationalization Affairs',
    'campus' => 'Occidental Mindoro State College — Main Campus',
    'base_path' => '/elia-system',
    'db_host' => getenv('ELIA_DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('ELIA_DB_PORT') ?: '3306',
    'db_name' => getenv('ELIA_DB_NAME') ?: 'elia_system',
    'db_user' => getenv('ELIA_DB_USER') ?: 'root',
    'db_password' => getenv('ELIA_DB_PASSWORD') ?: '',
    'session_idle_seconds' => 1800,
    'document_storage' => dirname(__DIR__) . '/uploads',
    'document_max_bytes' => 10 * 1024 * 1024,
], $local);
