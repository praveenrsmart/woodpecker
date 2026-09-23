<?php

declare(strict_types=1);

/**
 * Access-control smoke test against a running PHP server.
 * Usage: php scripts/test-access-control.php [baseUrl]
 */

$base = rtrim($argv[1] ?? 'http://localhost:3001', '/');

function httpJson(string $method, string $url, ?array $payload = null, ?string $token = null): array
{
    $headers = "Content-Type: application/json\r\n";
    if ($token) {
        $headers .= "Authorization: Bearer {$token}\r\n";
    }
    $opts = [
        'method' => $method,
        'header' => $headers,
        'timeout' => 20,
        'ignore_errors' => true,
    ];
    if ($payload !== null) {
        $opts['content'] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    $ctx = stream_context_create(['http' => $opts]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
        $status = (int) $m[0];
    }
    return [
        'status' => $status,
        'data' => json_decode($body === false ? '' : $body, true) ?: [],
    ];
}

$fail = 0;
function expect(string $label, bool $ok, string $detail = ''): void
{
    global $fail;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . ($detail !== '' ? " — $detail" : '') . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
}

$suffix = substr(bin2hex(random_bytes(3)), 0, 6);
$adminUser = 'superadmin';
$adminPass = 'super-test-' . $suffix;

require dirname(__DIR__) . '/api/bootstrap.php';
$existingAdmin = $db->get('SELECT id, username FROM super_admins LIMIT 1');
if ($existingAdmin) {
    $adminUser = $existingAdmin['username'];
    echo "Using existing super admin @{$adminUser} — skip password-login if this fails.\n";
} else {
    $db->run(
        'INSERT INTO super_admins (name, username, password_hash) VALUES (?, ?, ?)',
        ['Super Admin', $adminUser, hashPassword($adminPass)]
    );
}

$adminLogin = httpJson('POST', "$base/api/admin/login", [
    'username' => $adminUser,
    'password' => $existingAdmin ? '__will_fail_if_unknown__' : $adminPass,
]);

if (!$existingAdmin) {
    expect('Super admin login', $adminLogin['status'] === 200 && !empty($adminLogin['data']['token']), "HTTP {$adminLogin['status']}");
    $adminToken = $adminLogin['data']['token'] ?? '';
} else {
    echo "[SKIP] Super admin HTTP login (existing password unknown)\n";
    $adminToken = $auth->createToken('admin', (int) $existingAdmin['id']);
}

$academyUser = 'academy_' . $suffix;
$academyPass = 'pass-' . $suffix;
$created = httpJson('POST', "$base/api/admin/academies", [
    'name' => 'Test Academy ' . $suffix,
    'username' => $academyUser,
    'password' => $academyPass,
], $adminToken);
expect('Super admin creates academy', $created['status'] === 201 && ($created['data']['username'] ?? '') === $academyUser, "HTTP {$created['status']}");

$academyLogin = httpJson('POST', "$base/api/academy/login", [
    'username' => $academyUser,
    'password' => $academyPass,
]);
expect('Academy login', $academyLogin['status'] === 200 && !empty($academyLogin['data']['token']), "HTTP {$academyLogin['status']}");
$academyToken = $academyLogin['data']['token'] ?? '';

$studentUser = 'player_' . $suffix;
$studentPass = 'play-' . $suffix;
$student = httpJson('POST', "$base/api/academy/students", [
    'name' => 'Player ' . $suffix,
    'username' => $studentUser,
    'password' => $studentPass,
    'coachName' => 'Coach A',
], $academyToken);
expect('Academy creates student', $student['status'] === 201 && !empty($student['data']['student']['id']), "HTTP {$student['status']}");
$studentId = (int) ($student['data']['student']['id'] ?? 0);

$studentLogin = httpJson('POST', "$base/api/auth/login", [
    'username' => $studentUser,
    'password' => $studentPass,
]);
expect('Created student can login', $studentLogin['status'] === 200 && !empty($studentLogin['data']['token']), "HTTP {$studentLogin['status']}");

$disabled = httpJson('PATCH', "$base/api/academy/students/{$studentId}", ['active' => false], $academyToken);
expect('Academy disables student', $disabled['status'] === 200, "HTTP {$disabled['status']}");

$blocked = httpJson('POST', "$base/api/auth/login", [
    'username' => $studentUser,
    'password' => $studentPass,
]);
expect('Disabled student cannot login', $blocked['status'] === 403, "HTTP {$blocked['status']}");

$enabled = httpJson('PATCH', "$base/api/academy/students/{$studentId}", ['active' => true], $academyToken);
expect('Academy re-enables student', $enabled['status'] === 200, "HTTP {$enabled['status']}");

$again = httpJson('POST', "$base/api/auth/login", [
    'username' => $studentUser,
    'password' => $studentPass,
]);
expect('Re-enabled student can login', $again['status'] === 200, "HTTP {$again['status']}");

$self = httpJson('POST', "$base/api/auth/register", [
    'name' => 'Self ' . $suffix,
    'username' => 'self_' . $suffix,
    'password' => 'selfpass',
]);
expect('Public student signup still works', $self['status'] === 201, "HTTP {$self['status']}");

$academyReg = httpJson('POST', "$base/api/academy/register", [
    'name' => 'Public Academy ' . $suffix,
    'username' => 'pubacad_' . $suffix,
    'password' => 'pass1234',
]);
expect('Public academy signup still works', $academyReg['status'] === 201, "HTTP {$academyReg['status']}");

echo PHP_EOL;
if ($fail > 0) {
    echo "$fail check(s) failed.\n";
    exit(1);
}
echo "All access-control checks passed.\n";
