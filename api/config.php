<?php

declare(strict_types=1);

$config = [
    'session_secret' => getenv('SESSION_SECRET') ?: 'woodpecker-dev-secret-change-me',
    'data_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data',
    'db_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'woodpecker.db',
    'puzzles_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'puzzles.json',
    // Auto-created on first request if no super admin exists (change after first login).
    'super_admin_username' => getenv('SUPER_ADMIN_USERNAME') ?: 'superadmin',
    'super_admin_password' => getenv('SUPER_ADMIN_PASSWORD') ?: 'Woodpecker#Admin1',
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $overrides = require $local;
    if (is_array($overrides)) {
        $config = array_merge($config, $overrides);
    }
}

return $config;
