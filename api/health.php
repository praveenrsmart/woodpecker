<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo "PHP OK\n";
echo 'PHP version: ' . PHP_VERSION . "\n";

$extensions = ['pdo', 'pdo_sqlite', 'json'];
foreach ($extensions as $ext) {
    echo $ext . ': ' . (extension_loaded($ext) ? 'yes' : 'NO') . "\n";
}

try {
    require __DIR__ . '/bootstrap.php';
    echo "bootstrap: ok\n";
    echo "database: ok\n";
} catch (Throwable $e) {
    echo "bootstrap FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
