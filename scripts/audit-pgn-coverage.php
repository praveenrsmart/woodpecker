<?php

require_once dirname(__DIR__) . '/api/lib/Chess.php';

$pgn = file_get_contents(dirname(__DIR__) . '/data/woodpecker_method.pgn');
$puzzles = json_decode(file_get_contents(dirname(__DIR__) . '/data/puzzles.json'), true);

$games = [];
foreach (preg_split('/\n(?=\[Event)/', $pgn) as $part) {
    $t = trim($part);
    if ($t !== '') {
        $games[] = $t;
    }
}

function countRawComments(string $game): int
{
    return preg_match_all('/\{/', $game) ?: 0;
}

function extractAllComments(string $text): array
{
    $comments = [];
    $len = strlen($text);
    $i = 0;
    while ($i < $len) {
        if ($text[$i] !== '{') {
            $i++;
            continue;
        }
        $i++;
        $depth = 1;
        $start = $i;
        while ($i < $len && $depth > 0) {
            if ($text[$i] === '{') {
                $depth++;
            } elseif ($text[$i] === '}') {
                $depth--;
            }
            $i++;
        }
        $comments[] = substr($text, $start, $i - $start - 1);
    }

    return $comments;
}

$missingNotes = [];
$commentGaps = [];

foreach ($games as $game) {
    if (!preg_match('/\[Black "Exercise (\d+)([a-z])?"\]/i', $game, $em)) {
        continue;
    }
    if (!preg_match('/\[White "([^"]+)"\]/', $game, $wm) || !preg_match('/Advanced|Intermediate|Easy/i', $wm[1])) {
        continue;
    }
    $num = (int) $em[1];
    $variant = $em[2] ?? '';
    $section = preg_match('/Easy/i', $wm[1]) ? 'Easy' : (preg_match('/Intermediate/i', $wm[1]) ? 'Intermediate' : 'Advanced');

    $puzzle = null;
    foreach ($puzzles as $p) {
        if ($p['section'] === $section && $p['number'] === $num && (string) ($p['variant'] ?? '') === $variant) {
            $puzzle = $p;
            break;
        }
    }
    if (!$puzzle) {
        continue;
    }

    $allComments = extractAllComments($game);
    $moveSection = '';
    $inHeaders = true;
    foreach (explode("\n", $game) as $line) {
        if ($inHeaders && str_starts_with($line, '[')) {
            continue;
        }
        $inHeaders = false;
        if (trim($line) !== '') {
            $moveSection .= $line . "\n";
        }
    }
    $moveComments = extractAllComments($moveSection);
    // skip first description comment
    if ($moveComments !== []) {
        array_shift($moveComments);
    }

    $noteCount = count($puzzle['studyNotes'] ?? []);
    $rawMoveComments = count($moveComments);

    if ($noteCount === 0) {
        $missingNotes[] = "{$section} #{$num}{$variant} (pgn comments: {$rawMoveComments})";
    } elseif ($rawMoveComments > $noteCount + 2) {
        $commentGaps[] = "{$section} #{$num}{$variant}: pgn={$rawMoveComments} notes={$noteCount}";
    }
}

echo 'Puzzles without study notes: ' . count($missingNotes) . PHP_EOL;
foreach (array_slice($missingNotes, 0, 25) as $m) {
    echo "  $m\n";
}
echo 'Puzzles with comment gaps (>2 missing): ' . count($commentGaps) . PHP_EOL;
foreach (array_slice($commentGaps, 0, 15) as $g) {
    echo "  $g\n";
}
