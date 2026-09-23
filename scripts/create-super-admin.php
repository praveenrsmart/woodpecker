<?php

declare(strict_types=1);

/**
 * Create or reset a super admin account.
 * Usage:
 *   php scripts/create-super-admin.php <username> <password>
 *   php scripts/create-super-admin.php <username> <password> --reset
 */

$username = $argv[1] ?? 'superadmin';
$password = $argv[2] ?? '';
$reset = in_array('--reset', $argv, true);

if ($password === '' || strlen($password) < 4) {
    fwrite(STDERR, "Usage: php scripts/create-super-admin.php <username> <password> [--reset]\n");
    fwrite(STDERR, "Password must be at least 4 characters.\n");
    exit(1);
}

require dirname(__DIR__) . '/api/bootstrap.php';

$clean = cleanUsername($username);
if ($clean === '') {
    fwrite(STDERR, "Invalid username.\n");
    exit(1);
}

$existing = $db->get('SELECT id, username FROM super_admins WHERE username = ?', [$clean]);
if ($existing) {
    if (!$reset) {
        echo "Super admin already exists: @{$existing['username']}\n";
        echo "To set a new password, add --reset\n";
        exit(0);
    }
    $db->run(
        'UPDATE super_admins SET password_hash = ? WHERE id = ?',
        [hashPassword($password), (int) $existing['id']]
    );
    echo "Updated password for @{$existing['username']} (Admin ID #{$existing['id']})\n";
    echo "Sign in at /wood/admin\n";
    exit(0);
}

$id = $db->run(
    'INSERT INTO super_admins (name, username, password_hash) VALUES (?, ?, ?)',
    ['Super Admin', $clean, hashPassword($password)]
);
echo "Created super admin @{$clean} (Admin ID #{$id})\n";
echo "Sign in at /wood/admin\n";
