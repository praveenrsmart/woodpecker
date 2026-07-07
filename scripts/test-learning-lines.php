<?php

require __DIR__ . '/../api/bootstrap.php';

$puzzles = json_decode(file_get_contents(__DIR__ . '/../data/puzzles.json'), true);
$chess = new ChessService();

function findByNumber(array $puzzles, int $num, ?string $variant = null): ?array
{
    foreach ($puzzles as $p) {
        if ((int) ($p['number'] ?? 0) !== $num) {
            continue;
        }
        $v = $p['variant'] ?? null;
        if ($variant === null && empty($v)) {
            return $p;
        }
        if ($variant !== null && $v === $variant) {
            return $p;
        }
    }
    return null;
}

$attemptCompleted = ['completed' => 1, 'solution_revealed' => 0, 'active_moves' => null];
$attemptRevealed = ['completed' => 0, 'solution_revealed' => 1, 'active_moves' => null];

foreach ([658, 918, 690] as $num) {
    $p = findByNumber($puzzles, $num);
    if (!$p) {
        echo "#$num not found\n";
        continue;
    }
    $lines = $chess->getLearningLines($p, $attemptCompleted, 'key');
    echo "=== #$num (completed, key mode) ===\n";
    if (!$lines) {
        echo "  (none)\n";
        continue;
    }
    echo '  played: ' . implode(' ', $lines['playedLine']['moves']) . "\n";
    foreach ($lines['otherLines'] ?? [] as $alt) {
        echo '  - ' . $alt['label'] . ': ' . implode(' ', $alt['moves']) . "\n";
    }
    echo "\n";
}

$p690 = findByNumber($puzzles, 690);
$altAttempt = [
    'completed' => 1,
    'solution_revealed' => 0,
    'active_moves' => json_encode([
        'moves' => ['gxh4'],
        'keyMoveIndex' => 0,
    ]),
];
$lines = $chess->getLearningLines($p690, $altAttempt, 'key');
echo "=== #690 played gxh4 ===\n";
foreach ($lines['otherLines'] ?? [] as $alt) {
    echo '  - ' . $alt['label'] . ': ' . implode(' ', $alt['moves']) . "\n";
}
