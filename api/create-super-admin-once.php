<?php

declare(strict_types=1);

/**
 * One-time browser helper to create or reset the super admin (no CLI).
 * Upload to /wood/api/, open once, then DELETE this file.
 *
 * Default login after seed:
 *   username: superadmin
 *   password: Woodpecker#Admin1
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

require __DIR__ . '/bootstrap.php';

$defaultUser = 'superadmin';
$defaultPass = 'Woodpecker#Admin1';
$message = '';
$error = '';

try {
    // Force re-seed even if migration flag already ran (this file is one-time manual).
    if (!function_exists('hashPassword')) {
        require_once __DIR__ . '/lib/Password.php';
    }

    $hash = hashPassword($defaultPass);
    $existing = $db->get('SELECT id, username FROM super_admins WHERE username = ?', [$defaultUser]);
    if ($existing) {
        $db->run(
            'UPDATE super_admins SET password_hash = ? WHERE id = ?',
            [$hash, (int) $existing['id']]
        );
        $message = 'Password reset for @' . $defaultUser . ' (Admin ID #' . (int) $existing['id'] . ').';
    } else {
        $id = $db->run(
            'INSERT INTO super_admins (name, username, password_hash) VALUES (?, ?, ?)',
            ['Super Admin', $defaultUser, $hash]
        );
        $message = 'Created super admin @' . $defaultUser . ' (Admin ID #' . $id . ').';
    }

    $db->run(
        "INSERT OR REPLACE INTO app_meta (key, value) VALUES ('default_super_admin_seeded', ?)",
        ['1']
    );
} catch (Throwable $e) {
    $error = 'Failed: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Super admin ready</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 480px; margin: 40px auto; padding: 0 16px; }
    .ok { background: #ecfdf5; border: 1px solid #6ee7b7; padding: 12px; border-radius: 8px; }
    .err { background: #fef2f2; border: 1px solid #fca5a5; padding: 12px; border-radius: 8px; }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    .warn { color: #b91c1c; font-weight: 700; margin-top: 24px; }
  </style>
</head>
<body>
  <h1>Super admin</h1>
  <?php if ($error): ?>
    <p class="err"><?= htmlspecialchars($error) ?></p>
  <?php else: ?>
    <p class="ok"><?= htmlspecialchars($message) ?></p>
    <p>Sign in at <a href="/wood/login?role=admin">/wood/login?role=admin</a></p>
    <p>Username: <code><?= htmlspecialchars($defaultUser) ?></code></p>
    <p>Password: <code><?= htmlspecialchars($defaultPass) ?></code></p>
    <p>Then open <a href="/wood/admin">/wood/admin</a> and change the password.</p>
  <?php endif; ?>
  <p class="warn">Delete this file (api/create-super-admin-once.php) immediately.</p>
</body>
</html>
