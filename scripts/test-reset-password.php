<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://localhost:3001', '/');

$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode([
            'username' => 'alice2026',
            'studentId' => 4,
            'newPassword' => 'pass1234',
        ], JSON_THROW_ON_ERROR),
        'timeout' => 15,
        'ignore_errors' => true,
    ],
]);

$body = @file_get_contents("$base/api/auth/reset-password", false, $ctx);
$data = json_decode($body ?: '', true);
$status = 0;
if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
    $status = (int) $m[0];
}

echo "HTTP $status\n";
echo $body . "\n";

if ($status !== 200 || empty($data['ok'])) {
    exit(1);
}

echo "Password reset endpoint OK\n";
