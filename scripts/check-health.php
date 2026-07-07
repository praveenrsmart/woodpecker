<?php

declare(strict_types=1);

/**
 * PHP-only smoke test — no Node.js required.
 * Usage: php scripts/check-health.php [baseUrl]
 * Default baseUrl: http://localhost:3001
 */

$base = rtrim($argv[1] ?? 'http://localhost:3001', '/');
$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if ($ok) {
        echo "[OK] $label" . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
        return;
    }
    echo "[FAIL] $label" . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
    $failures++;
}

function httpGet(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
        $status = (int) $m[0];
    }
    return ['status' => $status, 'body' => $body === false ? '' : $body];
}

function httpPostJson(string $url, array $payload): array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
        $status = (int) $m[0];
    }
    return ['status' => $status, 'body' => $body === false ? '' : $body];
}

echo "Woodpecker health check — $base\n\n";

$index = httpGet("$base/");
check('index.html loads', $index['status'] === 200 && str_contains($index['body'], 'id="root"'));

$assetsDir = dirname(__DIR__) . '/assets';
check('assets/index-sA8ZWdLG.js exists', is_file($assetsDir . '/index-sA8ZWdLG.js'));
check('assets/index-B2gsu7wV.css exists', is_file($assetsDir . '/index-B2gsu7wV.css'));

$login = httpPostJson("$base/api/auth/login", [
    'username' => 'alice2026',
    'password' => 'pass1234',
]);
$loginData = json_decode($login['body'], true);
check('API login', $login['status'] === 200 && !empty($loginData['token']), "HTTP {$login['status']}");

$puzzles = httpGet("$base/api/puzzles?section=Easy");
check('API puzzles', $puzzles['status'] === 200, "HTTP {$puzzles['status']}");

echo PHP_EOL;
if ($failures > 0) {
    echo "$failures check(s) failed.\n";
    exit(1);
}

echo "All checks passed. App is PHP-only at runtime (static JS/CSS in assets/).\n";
