<?php

declare(strict_types=1);

/**
 * One-time repair: show academy/student link status and re-attach all students to Feathers.
 * Upload to /wood/, open in browser once, then DELETE this file.
 *
 * URL: https://chesstrackers.com/wood/repair-academy-data.php
 * (or /wood/api/repair-academy-data.php after .htaccess fix)
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

require __DIR__ . '/api/bootstrap.php';

$action = (string) ($_GET['action'] ?? '');
$messages = [];
$error = '';

try {
    // Ensure columns exist (older live DBs may be missing them).
    $academyCols = array_column($db->all('PRAGMA table_info(academies)'), 'name');
    if (!in_array('is_active', $academyCols, true)) {
        $db->exec('ALTER TABLE academies ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        $messages[] = 'Added academies.is_active';
    }

    $linkCols = array_column($db->all('PRAGMA table_info(academy_students)'), 'name');
    if (!in_array('is_active', $linkCols, true)) {
        $db->exec('ALTER TABLE academy_students ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        $messages[] = 'Added academy_students.is_active';
    }

    $studentCols = array_column($db->all('PRAGMA table_info(students)'), 'name');
    if (!in_array('is_active', $studentCols, true)) {
        $db->exec('ALTER TABLE students ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        $messages[] = 'Added students.is_active';
    }
    if (!in_array('created_by_academy_id', $studentCols, true)) {
        $db->exec('ALTER TABLE students ADD COLUMN created_by_academy_id INTEGER');
        $messages[] = 'Added students.created_by_academy_id';
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS academy_coaches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            academy_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(academy_id, name)
        )
    ");

    $feathers = $db->get(
        "SELECT * FROM academies
         WHERE LOWER(name) LIKE '%feather%' OR LOWER(username) LIKE '%feather%'
         ORDER BY id ASC LIMIT 1"
    );

    if ($action === 'relink' && $feathers) {
        $academyId = (int) $feathers['id'];
        $db->run('UPDATE academies SET is_active = 1 WHERE id = ?', [$academyId]);

        $linked = 0;
        foreach ($db->all('SELECT id FROM students') as $student) {
            $studentId = (int) $student['id'];
            $db->run(
                'INSERT OR IGNORE INTO academy_students (academy_id, student_id, coach_name, is_active) VALUES (?, ?, ?, 1)',
                [$academyId, $studentId, '']
            );
            $db->run(
                'UPDATE academy_students SET is_active = 1, academy_id = academy_id WHERE academy_id = ? AND student_id = ?',
                [$academyId, $studentId]
            );
            // Ensure link exists under Feathers even if student was only on another academy.
            $exists = $db->get(
                'SELECT id FROM academy_students WHERE academy_id = ? AND student_id = ?',
                [$academyId, $studentId]
            );
            if (!$exists) {
                $db->run(
                    'INSERT INTO academy_students (academy_id, student_id, coach_name, is_active) VALUES (?, ?, ?, 1)',
                    [$academyId, $studentId, '']
                );
            } else {
                $db->run(
                    'UPDATE academy_students SET is_active = 1 WHERE academy_id = ? AND student_id = ?',
                    [$academyId, $studentId]
                );
            }
            $db->run(
                'UPDATE students SET is_active = 1, created_by_academy_id = ? WHERE id = ?',
                [$academyId, $studentId]
            );
            $linked++;
        }

        // Keep Feathers links; also keep other academies' links (safer than deleting).
        $db->run(
            "INSERT OR REPLACE INTO app_meta (key, value) VALUES ('feathers_students_moved', ?)",
            [(string) $academyId]
        );
        $messages[] = "Relinked {$linked} students to Feathers academy #{$academyId} (@{$feathers['username']}).";
    }

    if ($action === 'seed-coach' && $feathers) {
        $academyId = (int) $feathers['id'];
        $db->run(
            'INSERT OR IGNORE INTO academy_coaches (academy_id, name) VALUES (?, ?)',
            [$academyId, 'Head Coach']
        );
        $messages[] = 'Added sample coach "Head Coach" under Feathers (if it did not exist).';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$academies = [];
$studentsTotal = 0;
$coachesTotal = 0;
$meta = [];
try {
    $studentsTotal = (int) ($db->get('SELECT COUNT(*) AS c FROM students')['c'] ?? 0);
    $coachesTotal = (int) ($db->get('SELECT COUNT(*) AS c FROM academy_coaches')['c'] ?? 0);
    $academies = $db->all('SELECT id, name, username, COALESCE(is_active,1) AS is_active FROM academies ORDER BY id');
    foreach ($academies as &$row) {
        $id = (int) $row['id'];
        $row['student_links'] = (int) ($db->get(
            'SELECT COUNT(*) AS c FROM academy_students WHERE academy_id = ?',
            [$id]
        )['c'] ?? 0);
        $row['active_links'] = (int) ($db->get(
            'SELECT COUNT(*) AS c FROM academy_students WHERE academy_id = ? AND COALESCE(is_active,1) = 1',
            [$id]
        )['c'] ?? 0);
        $row['coaches'] = (int) ($db->get(
            'SELECT COUNT(*) AS c FROM academy_coaches WHERE academy_id = ?',
            [$id]
        )['c'] ?? 0);
    }
    unset($row);
    $meta = $db->all('SELECT key, value FROM app_meta ORDER BY key');
    $feathers = $db->get(
        "SELECT * FROM academies
         WHERE LOWER(name) LIKE '%feather%' OR LOWER(username) LIKE '%feather%'
         ORDER BY id ASC LIMIT 1"
    );
} catch (Throwable $e) {
    $error = $error ?: $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Repair academy data</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 860px; margin: 32px auto; padding: 0 16px; }
    table { width: 100%; border-collapse: collapse; margin: 16px 0; }
    th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
    .ok { background: #ecfdf5; border: 1px solid #6ee7b7; padding: 10px; border-radius: 8px; margin: 8px 0; }
    .err { background: #fef2f2; border: 1px solid #fca5a5; padding: 10px; border-radius: 8px; }
    .warn { color: #b91c1c; font-weight: 700; }
    a.btn, button.btn {
      display: inline-block; margin: 8px 8px 8px 0; padding: 10px 14px;
      background: #4f46e5; color: #fff; text-decoration: none; border-radius: 8px; border: 0; font-weight: 700; cursor: pointer;
    }
    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>
  <h1>Repair academy / Feathers data</h1>
  <p>Students total in DB: <strong><?= (int) $studentsTotal ?></strong> · Coaches total: <strong><?= (int) $coachesTotal ?></strong></p>

  <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php foreach ($messages as $msg): ?><p class="ok"><?= htmlspecialchars($msg) ?></p><?php endforeach; ?>

  <h2>Academies</h2>
  <table>
    <tr><th>ID</th><th>Name</th><th>Username</th><th>Active</th><th>Student links</th><th>Active links</th><th>Coaches</th></tr>
    <?php foreach ($academies as $a): ?>
      <tr>
        <td>#<?= (int) $a['id'] ?></td>
        <td><?= htmlspecialchars((string) $a['name']) ?></td>
        <td><code><?= htmlspecialchars((string) $a['username']) ?></code></td>
        <td><?= (int) $a['is_active'] === 1 ? 'yes' : 'no' ?></td>
        <td><?= (int) $a['student_links'] ?></td>
        <td><?= (int) $a['active_links'] ?></td>
        <td><?= (int) $a['coaches'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Feathers target</h2>
  <?php if ($feathers): ?>
    <p>Found: <strong><?= htmlspecialchars((string) $feathers['name']) ?></strong>
      · login <code><?= htmlspecialchars((string) $feathers['username']) ?></code>
      · ID #<?= (int) $feathers['id'] ?></p>
    <p>
      <a class="btn" href="?action=relink">Relink ALL students to Feathers</a>
      <a class="btn" href="?action=seed-coach">Add sample coach to Feathers</a>
    </p>
  <?php else: ?>
    <p class="err">No Feathers academy found (name/username containing "feather").</p>
  <?php endif; ?>

  <h2>app_meta</h2>
  <pre><?= htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT)) ?></pre>

  <p>After repair, open <a href="/wood/academy.php">/wood/academy.php</a> while logged in as Feathers.</p>
  <p class="warn">Delete this file (repair-academy-data.php) and api/repair-academy-data.php when done.</p>
</body>
</html>
