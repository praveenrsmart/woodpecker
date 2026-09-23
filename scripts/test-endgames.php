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
            'timeout' => 60,
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

function ok(string $label, bool $pass, $extra = null): void
{
    echo ($pass ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$pass) {
        if ($extra !== null) {
            echo json_encode($extra, JSON_PRETTY_PRINT) . PHP_EOL;
        }
        exit(1);
    }
}

[$code, $academy] = req('POST', $base . '/academy/login', [
    'username' => 'demoacademy',
    'password' => 'demo1234',
]);
ok('Academy login', $code === 200 && isset($academy['token']), [$code, $academy]);
$aToken = $academy['token'];

[$code, $student] = req('POST', $base . '/auth/login', [
    'username' => 'dummy_student',
    'password' => 'pass1234',
]);
ok('Dummy student login', $code === 200 && isset($student['token']), [$code, $student]);
$sToken = $student['token'];
$studentId = (int) $student['student']['id'];

req('POST', $base . '/academy/students', ['studentId' => $studentId], $aToken);

[$code, $cat] = req('POST', $base . '/academy/endgames', [
    'name' => 'Basic mates',
    'notes' => 'API check',
], $aToken);
ok('Create category', $code === 201 && isset($cat['id']), [$code, $cat]);

[$code, $sub] = req('POST', $base . '/academy/endgames/' . $cat['id'] . '/subcategories', [
    'name' => 'Rook mates',
], $aToken);
ok('Create subcategory', $code === 201 && isset($sub['id']), [$code, $sub]);

$fen = '7k/5R2/6K1/8/8/8/8/8 w - - 0 1';
[$code, $chapter] = req('POST', $base . '/academy/endgame-subcategories/' . $sub['id'] . '/chapters', [
    'title' => 'Rook mate',
    'fen' => $fen,
    'goal' => 'white_win',
], $aToken);
ok('Save editor position', $code === 201 && ($chapter['goal'] ?? '') === 'white_win', [$code, $chapter]);

[$code, $assign] = req('POST', $base . '/academy/endgames/' . $cat['id'] . '/assign', [
    'studentIds' => [$studentId],
    'replace' => true,
], $aToken);
ok('Assign category to student', $code === 200 && count($assign['assignedStudents'] ?? []) >= 1, [$code, $assign]);

[$code, $mine] = req('GET', $base . '/me/endgames', null, $sToken);
ok('Student lists assigned endgames', $code === 200 && count($mine['categories'] ?? []) >= 1, [$code, $mine]);

[$code, $attempt] = req('POST', $base . '/endgame-attempts/start', [
    'chapterId' => $chapter['id'],
    'mode' => 'practice',
    'level' => 'beginner',
], $sToken);
ok('Start practice', ($code === 200 || $code === 201) && ($attempt['playerColor'] ?? '') === 'w', [$code, $attempt]);

[$code, $moved] = req('POST', $base . '/endgame/move', [
    'fen' => $fen,
    'from' => 'f7',
    'to' => 'f8',
    'goal' => 'white_win',
    'playerColor' => 'w',
], $sToken);
ok('Winning move ends the game', $code === 200 && !empty($moved['judge']['passed']), [$code, $moved]);

[$code, $done] = req('POST', $base . '/endgame-attempts/' . $attempt['attempt']['id'] . '/finish', [
    'passed' => true,
    'failedRestarts' => 0,
    'result' => '1-0',
], $sToken);
ok('Practice finish saved', $code === 200, [$code, $done]);

[$code, $test] = req('POST', $base . '/endgame-attempts/start', [
    'chapterId' => $chapter['id'],
    'mode' => 'test',
    'level' => 'beginner',
], $sToken);
ok('Start test after practice', $code === 201 || $code === 200, [$code, $test]);

[$code, $testDone] = req('POST', $base . '/endgame-attempts/' . $test['attempt']['id'] . '/finish', [
    'passed' => true,
    'failedRestarts' => 0,
    'result' => '1-0',
], $sToken);
ok('Clean test is marked green', $code === 200 && !empty($testDone['green']), [$code, $testDone]);

[$code, $detail] = req('GET', $base . '/me/endgames/' . $cat['id'], null, $sToken);
$passed = $detail['subcategories'][0]['chapters'][0]['passed'] ?? false;
ok('Student chapter is green', $code === 200 && $passed === true, [$code, $detail]);

[$code, $progress] = req('GET', $base . '/academy/endgames/' . $cat['id'] . '/progress', null, $aToken);
$green = 0;
foreach ($progress['progress'] ?? [] as $row) {
    if ((int) ($row['student_id'] ?? 0) === $studentId) {
        $green = (int) ($row['green'] ?? 0);
    }
}
ok('Academy sees green progress', $code === 200 && $green >= 1, [$code, $progress]);

echo "Endgame module checks passed.\n";
