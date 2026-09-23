<?php
declare(strict_types=1);

$base = $argv[1] ?? 'http://localhost:3001/wood/api';

function req(string $method, string $url, array $body = null, ?string $token = null): array
{
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body === null ? '' : json_encode($body),
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);
    $raw = file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    $json = json_decode((string) $raw, true);
    return [$code, is_array($json) ? $json : ['raw' => $raw]];
}

function ok(string $label, bool $pass): void
{
    echo ($pass ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$pass) {
        exit(1);
    }
}

[$code, $academy] = req('POST', $base . '/academy/login', [
    'username' => 'demoacademy',
    'password' => 'demo1234',
]);
if ($code !== 200) {
    [$code, $created] = req('POST', $base . '/academy/register', [
        'name' => 'Demo Academy',
        'username' => 'demoacademy',
        'password' => 'demo1234',
    ]);
    if ($code === 201 || $code === 200) {
        $academy = $created;
    } else {
        [$code, $academy] = req('POST', $base . '/academy/login', [
            'username' => 'demoacademy',
            'password' => 'demo1234',
        ]);
    }
}
ok('Academy can sign in or register', isset($academy['token']));
$aToken = $academy['token'];

[$code, $student] = req('POST', $base . '/auth/login', [
    'username' => 'dummy_student',
    'password' => 'pass1234',
]);
ok('Dummy student login', $code === 200 && isset($student['token']));
$sToken = $student['token'];
$studentId = (int) $student['student']['id'];

req('POST', $base . '/academy/students', [
    'studentId' => $studentId,
], $aToken);

[$code, $opening] = req('POST', $base . '/academy/openings', [
    'name' => 'Italian Game',
    'colorGroup' => 'white',
    'notes' => 'Test opening',
], $aToken);
ok('Create white opening', $code === 201 && ($opening['colorGroup'] ?? '') === 'white');

[$code, $chapter] = req('POST', $base . '/academy/openings/' . $opening['id'] . '/chapters', [
    'title' => 'Main line',
    'pgn' => '1. e4 e5 2. Nf3 Nc6 3. Bc4 Bc5',
], $aToken);
ok('Add PGN chapter', $code === 201 && (int) ($chapter['chapter']['plyCount'] ?? 0) === 6);

[$code, $assign] = req('POST', $base . '/academy/openings/' . $opening['id'] . '/assign', [
    'studentIds' => [$studentId],
    'replace' => true,
], $aToken);
ok('Assign opening to student', $code === 200 && count($assign['assignedStudents'] ?? []) >= 1);

[$code, $mine] = req('GET', $base . '/me/openings', null, $sToken);
ok('Student sees assigned opening', $code === 200 && count($mine['openings'] ?? []) >= 1);

[$code, $test] = req('POST', $base . '/opening-tests/start', [
    'chapterId' => $chapter['chapter']['id'],
], $sToken);
ok('Start test', ($code === 200 || $code === 201) && ($test['playerColor'] ?? '') === 'w');
ok('Test starts on White to move', ($test['turn'] ?? '') === 'w');

[$code, $wrong] = req('POST', $base . '/opening-tests/' . $test['test']['id'] . '/play', [
    'from' => 'e2',
    'to' => 'e3',
], $sToken);
ok('Wrong move is rejected', $code === 200 && empty($wrong['correct']) && (int) $wrong['test']['wrongMoves'] >= 1);

[$code, $right] = req('POST', $base . '/opening-tests/' . $test['test']['id'] . '/play', [
    'from' => 'e2',
    'to' => 'e4',
], $sToken);
ok('Correct White move accepted and Black auto-plays', !empty($right['correct']) && ($right['turn'] ?? '') === 'w');

echo "Opening module checks passed.\n";
