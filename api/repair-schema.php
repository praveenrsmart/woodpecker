<?php

declare(strict_types=1);

/**
 * One-time repair for servers with older SQLite that cannot parse partial indexes.
 * Upload and open in a browser once, then delete this file.
 */

$config = require __DIR__ . '/config.php';
$dbPath = $config['db_path'];

header('Content-Type: text/plain; charset=utf-8');

if (!is_file($dbPath)) {
    echo "Database not found at {$dbPath}\n";
    echo "Nothing to repair. The app will create a fresh database on first use.\n";
    exit(0);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('DROP INDEX IF EXISTS idx_one_active_cycle_per_section');
    echo "Repair complete. Removed idx_one_active_cycle_per_section.\n";
    echo "Delete this repair-schema.php file, then reload the app.\n";
} catch (PDOException $e) {
    echo "Automatic repair failed:\n";
    echo $e->getMessage() . "\n\n";
    echo "The database schema is already corrupted on this server.\n";
    echo "Choose one of these options:\n\n";
    echo "1. Fresh start (loses existing data):\n";
    echo "   Rename or delete: {$dbPath}\n\n";
    echo "2. Keep data (run on your computer with SQLite 3.8+):\n";
    echo "   sqlite3 woodpecker.db \"DROP INDEX idx_one_active_cycle_per_section;\"\n";
    echo "   Then re-upload the repaired database file.\n";
    exit(1);
}
