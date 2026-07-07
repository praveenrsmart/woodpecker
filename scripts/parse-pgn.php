<?php

declare(strict_types=1);

/**
 * Parse Woodpecker Method PGN into puzzles.json with book-faithful metadata:
 * - keyMoveIndices (all ✔ marks on the main line)
 * - alternativeLines (also full points / another winning move / etc.)
 * - variant suffix (e.g. exercise 690a)
 *
 * Usage: php scripts/parse-pgn.php [path-to.pgn]
 */

require_once dirname(__DIR__) . '/api/lib/Chess.php';

use Ryanhs\Chess\Chess;

$defaultPgn = dirname(__DIR__) . '/data/woodpecker_method.pgn';
$archivePgn = dirname(__DIR__, 2) . '/Archive/Wood/Smith,_Axel_&_Tikkanen,_Hans_The_Woodpecker_Method_2018.pgn';
$pgnPath = $argv[1] ?? (is_file($defaultPgn) ? $defaultPgn : $archivePgn);
$outPath = dirname(__DIR__) . '/data/puzzles.json';

if (!is_file($pgnPath)) {
    fwrite(STDERR, "PGN not found: {$pgnPath}\n");
    exit(1);
}

if (!is_file($defaultPgn) && $pgnPath !== $defaultPgn) {
    copy($pgnPath, $defaultPgn);
    echo "Copied PGN to data/woodpecker_method.pgn\n";
}

$pgn = file_get_contents($pgnPath);
$puzzles = parsePuzzles($pgn);

file_put_contents($outPath, json_encode($puzzles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'Parsed ' . count($puzzles) . " puzzles → {$outPath}\n";

$withAlt = count(array_filter($puzzles, static fn (array $p) => !empty($p['alternativeLines'])));
$withVariant = count(array_filter($puzzles, static fn (array $p) => !empty($p['variant'])));
$multiKey = count(array_filter($puzzles, static fn (array $p) => count($p['keyMoveIndices'] ?? []) > 1));
$withNotes = count(array_filter($puzzles, static fn (array $p) => !empty($p['studyNotes'])));
$totalAlts = array_sum(array_map(static fn (array $p) => count($p['alternativeLines'] ?? []), $puzzles));
$totalNotes = array_sum(array_map(static fn (array $p) => count($p['studyNotes'] ?? []), $puzzles));
echo "  With alternative lines: {$withAlt} ({$totalAlts} total branches)\n";
echo "  With variant suffix: {$withVariant}\n";
echo "  With multiple key moves: {$multiKey}\n";
echo "  With study notes: {$withNotes} ({$totalNotes} total notes)\n";

function parsePuzzles(string $pgn): array
{
    $games = splitGames($pgn);
    $puzzles = [];

    foreach ($games as $game) {
        $headers = parseHeaders($game);
        if (($headers['SetUp'] ?? '') !== '1' || empty($headers['FEN'])) {
            continue;
        }

        $section = parseSection($headers['White'] ?? '');
        $exercise = parseExerciseMeta($headers['Black'] ?? '');
        if (!$section || !$exercise) {
            continue;
        }

        $moves = parseMovesFromPgn($game, $headers['FEN']);
        if ($moves === []) {
            continue;
        }

        $moveSection = extractMoveSection($game);
        $keyMoveIndices = parseKeyMoveIndices($moveSection);
        $lastKeyIndex = $keyMoveIndices !== []
            ? max($keyMoveIndices)
            : count($moves) - 1;

        $alternativeLines = extractAlternativeLines($game, $headers['FEN'], $moves, $keyMoveIndices);
        $studyNotes = extractStudyNotes($game, $moves);

        $puzzles[] = [
            'id' => 0,
            'section' => $section,
            'number' => $exercise['number'],
            'variant' => $exercise['variant'],
            'fen' => $headers['FEN'],
            'description' => extractDescription($game),
            'moves' => $moves,
            'keyMoveIndex' => min($lastKeyIndex, count($moves) - 1),
            'keyMoveIndices' => $keyMoveIndices,
            'alternativeLines' => $alternativeLines,
            'studyNotes' => $studyNotes,
            'sideToMove' => explode(' ', $headers['FEN'])[1] ?? 'w',
        ];
    }

    usort($puzzles, static function (array $a, array $b): int {
        $order = ['Easy' => 0, 'Intermediate' => 1, 'Advanced' => 2];
        if ($order[$a['section']] !== $order[$b['section']]) {
            return $order[$a['section']] <=> $order[$b['section']];
        }
        if ($a['number'] !== $b['number']) {
            return $a['number'] <=> $b['number'];
        }
        return strcmp((string) ($a['variant'] ?? ''), (string) ($b['variant'] ?? ''));
    });

    foreach ($puzzles as $i => &$puzzle) {
        $puzzle['id'] = $i + 1;
        if ($puzzle['alternativeLines'] === []) {
            unset($puzzle['alternativeLines']);
        } else {
            foreach ($puzzle['alternativeLines'] as &$alt) {
                if (($alt['keyMoveIndices'] ?? []) === []) {
                    unset($alt['keyMoveIndices']);
                }
            }
            unset($alt);
        }
        if ($puzzle['variant'] === null) {
            unset($puzzle['variant']);
        }
        if (count($puzzle['keyMoveIndices']) <= 1) {
            unset($puzzle['keyMoveIndices']);
        }
        if (($puzzle['studyNotes'] ?? []) === []) {
            unset($puzzle['studyNotes']);
        }
    }
    unset($puzzle);

    return $puzzles;
}

function splitGames(string $pgn): array
{
    $games = [];
    foreach (preg_split('/\n(?=\[Event)/', $pgn) as $part) {
        $trimmed = trim($part);
        if ($trimmed !== '') {
            $games[] = $trimmed;
        }
    }
    return $games;
}

function parseHeaders(string $gameText): array
{
    $headers = [];
    if (preg_match_all('/^\[(\w+)\s+"([^"]*)"\]/m', $gameText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $headers[$m[1]] = $m[2];
        }
    }
    return $headers;
}

function parseSection(string $whiteTag): ?string
{
    if (preg_match('/Easy/i', $whiteTag)) {
        return 'Easy';
    }
    if (preg_match('/Intermediate/i', $whiteTag)) {
        return 'Intermediate';
    }
    if (preg_match('/Advanced/i', $whiteTag)) {
        return 'Advanced';
    }
    return null;
}

/** @return array{number:int,variant:?string}|null */
function parseExerciseMeta(string $blackTag): ?array
{
    if (!preg_match('/Exercise\s+(\d+)([a-z])?/i', $blackTag, $m)) {
        return null;
    }
    return [
        'number' => (int) $m[1],
        'variant' => isset($m[2]) && $m[2] !== '' ? $m[2] : null,
    ];
}

function extractMoveSection(string $gameText): string
{
    $parts = [];
    $inHeaders = true;
    foreach (explode("\n", $gameText) as $line) {
        if ($inHeaders && str_starts_with($line, '[')) {
            continue;
        }
        $inHeaders = false;
        if (trim($line) !== '') {
            $parts[] = $line;
        }
    }
    return implode("\n", $parts);
}

function hasCheckmark(string $text): bool
{
    return (bool) preg_match('/✔|✓|\x{2714}/u', $text);
}

function parseKeyMoveIndices(string $moveSection): array
{
    $keyIndices = [];
    $stripped = preg_replace_callback('/\{[^}]*\}/', static function (array $m): string {
        return hasCheckmark($m[0]) ? ' {TICK} ' : ' ';
    }, $moveSection) ?? $moveSection;

    $pattern = '/(?:(\d+)\.(?:\.\.)?\s*)?([NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O(?:\+|#)?|O-O(?:\+|#)?|\{TICK\})/';
    $moveIndex = -1;
    if (preg_match_all($pattern, $stripped, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            if ($match[2] === '{TICK}') {
                if ($moveIndex >= 0) {
                    $keyIndices[] = $moveIndex;
                }
            } else {
                $moveIndex++;
            }
        }
    }

    return array_values(array_unique($keyIndices));
}

function parseMovesFromPgn(string $gameText, string $fen): array
{
    try {
        $chess = new Chess();
        $chess->load($fen);
        if (!$chess->loadPgn($gameText)) {
            return parseMovesManual($gameText, $fen);
        }
        $history = $chess->history();
        return is_array($history) ? $history : [];
    } catch (Throwable) {
        return parseMovesManual($gameText, $fen);
    }
}

function parseMovesManual(string $gameText, string $fen): array
{
    $moveSection = extractMoveSection($gameText);
    $cleaned = preg_replace('/\{[^}]*\}/', ' ', $moveSection) ?? $moveSection;
    $cleaned = preg_replace('/\d+\.\.\./', ' ', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('/\d+\./', ' ', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('/\*|1-0|0-1|1\/2-1\/2|--/', ' ', $cleaned) ?? $cleaned;
    $tokens = preg_split('/\s+/', trim($cleaned)) ?: [];

    $chess = new Chess();
    $chess->load($fen);
    $moves = [];

    foreach ($tokens as $token) {
        if ($token === '' || preg_match('/^\{TICK\}$/', $token)) {
            continue;
        }
        try {
            $mv = $chess->move($token);
            if ($mv && isset($mv['san'])) {
                $moves[] = $mv['san'];
            } else {
                break;
            }
        } catch (Throwable) {
            break;
        }
    }

    return $moves;
}

function extractDescription(string $gameText): string
{
    $comments = extractAllComments(extractMoveSection($gameText));
    $text = $comments[0] ?? '';
    if ($text === '' && preg_match('/\{([^}]{10,})\}/', $gameText, $m)) {
        $text = $m[1];
    }
    $text = preg_replace('/@@\w+@@/', '', $text) ?? $text;
    $text = str_replace(['@@StartBlockQuote@@', '@@EndBlockQuote@@'], '"', $text);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return substr(trim($text), 0, 400);
}

/**
 * @return list<string>
 */
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

function extractAlternativeLines(string $gameText, string $fen, array $mainMoves, array $mainKeyIndices): array
{
    $moveSection = extractMoveSection($gameText);
    $comments = extractAllComments($moveSection);
    if ($comments !== []) {
        array_shift($comments);
    }

    $alternatives = [];
    $seen = [];

    foreach ($comments as $comment) {
        if (!shouldExtractAlternativesFromComment($comment)) {
            continue;
        }
        foreach (splitCommentFragments($comment) as $fragment) {
            if (!commentLikelyHasMoves($fragment)) {
                continue;
            }
            $sequences = extractMoveSequencesFromComment($fragment, $fen, $mainMoves, false);
            foreach ($sequences as $sequence) {
                $branch = buildAlternativeBranch($mainMoves, $sequence, $fen, $fragment);
                if ($branch === null) {
                    continue;
                }
                $sig = $branch['branchAt'] . ':' . implode(',', $branch['moves']);
                if (isset($seen[$sig])) {
                    continue;
                }
                $seen[$sig] = true;
                $alternatives[] = $branch;
            }
        }
    }

    return $alternatives;
}

function shouldExtractAlternativesFromComment(string $comment): bool
{
    if (!commentLikelyHasMoves($comment)) {
        return false;
    }

    if (preg_match(
        '/also full points|another winning|not the only|among them|also wins|full points also|another winning line|winning line is|transposes|Easiest is|Instead\s|worth one point|For example|move order|Material is equal|\bOr\s+\d|\bOr\s+[NBRQKa-h=O\-+#]/iu',
        $comment
    )) {
        return true;
    }

    // ✔ in a comment with 2+ move references usually marks an alternative line in the text.
    return hasCheckmark($comment) && preg_match_all('/\d+\.{1,3}/', $comment) >= 2;
}

/**
 * @return list<string>
 */
function splitCommentFragments(string $comment): array
{
    $fragments = [trim($comment)];
    $patterns = [
        '/\s+Or\s+/i',
        '/\s+Alternatively[,:]?\s+/i',
        '/\s+Another winning (?:line|move) is[:\s]+/i',
        '/\s+Full points also for[:\s]+/i',
        '/\s+Easiest is[:\s]+/i',
        '/\s+For example[:\s]+/i',
        '/\s+among them[:\s]+/i',
        '/\s+Instead[,\s]+/i',
        '/\s+not the only winning move[,.]?\s+/i',
        '/\s+The move order[:\s]+/i',
        '/\(\s*\)\s*A\.\s+/i',
        '/\.\s+A\.\s+/',
        '/\s+B\.\s+/',
        '/\s+C\.\s+/',
        '/\s+D\.\s+/',
        '/\s+And (?:he|she|they|White|Black) (?:doesn\'t|don\'t|also)[^:]*:\s*/i',
        '/\s+Material is equal after[:\s]+/i',
    ];

    foreach ($patterns as $pattern) {
        $next = [];
        foreach ($fragments as $frag) {
            $parts = preg_split($pattern, $frag) ?: [$frag];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $next[] = $part;
                }
            }
        }
        if (count($next) > count($fragments)) {
            $fragments = $next;
        }
    }

    return array_values(array_unique($fragments));
}

function commentLikelyHasMoves(string $text): bool
{
    return (bool) preg_match(
        '/\d+\.{1,3}\s*[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8]|O-O(?:-O)?|[NBRQK]?[a-h]x[a-h][1-8]/i',
        $text
    );
}

/**
 * @return list<list<string>>
 */
function extractMoveSequencesFromComment(string $comment, string $fen, array $mainMoves, bool $allowFullParse = true): array
{
    $sequences = [];

    // Colon-delimited full lines: "... : 2.Qxd4 Nxd4 3.Rd1 ..."
    if (preg_match('/:\s*((?:\d+\.{1,3}\s*)?(?:[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O)\s*)+/i', $comment, $colonMatch)) {
        $parsed = parseNumberedSequence($colonMatch[0], $fen, $mainMoves);
        if ($parsed !== []) {
            $sequences[] = $parsed;
        }
    }

    // "among them 2.Rd6" / "another is 2.Bf3"
    if (preg_match_all('/(?:among them|another is|For example:?|e\.g\.:?)\s*(\d+\.{1,3}\s*[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O)/i', $comment, $singleMatches, PREG_SET_ORDER)) {
        foreach ($singleMatches as $sm) {
            $parsed = parseNumberedSequence($sm[1], $fen, $mainMoves);
            if ($parsed !== []) {
                $sequences[] = $parsed;
            }
        }
    }

    // "2...Bxf5 3.Qxf4 g5 also wins"
    if (preg_match('/((?:\d+\.{1,3}\s*)?(?:[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O)(?:\s+(?:\d+\.{1,3}\s*)?(?:[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O))*)\s*(?:also wins|also full points|also scores)/i', $comment, $winMatch)) {
        $parsed = parseNumberedSequence($winMatch[1], $fen, $mainMoves);
        if ($parsed !== []) {
            $sequences[] = $parsed;
        }
    }

    // "Instead 1.gxh4" first-move alternative
    if (preg_match('/Instead\s+(\d+\.{1,3}\s*[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O)/i', $comment, $insteadMatch)) {
        $parsed = parseNumberedSequence($insteadMatch[1], $fen, $mainMoves);
        if ($parsed !== []) {
            $sequences[] = $parsed;
        }
    }

    // "Or 8.Re1 fxe5" style
    if (preg_match_all('/\bOr\s+((?:\d+\.{1,3}\s*)?(?:[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O)(?:\s+(?:\d+\.{1,3}\s*)?(?:[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O))*)/i', $comment, $orMatches, PREG_SET_ORDER)) {
        foreach ($orMatches as $om) {
            $parsed = parseNumberedSequence($om[1], $fen, $mainMoves);
            if ($parsed !== []) {
                $sequences[] = $parsed;
            }
        }
    }

    if ($allowFullParse && $sequences === []) {
        $parsed = parseNumberedSequence($comment, $fen, $mainMoves);
        if ($parsed !== []) {
            $sequences[] = $parsed;
        }
    }

    return $sequences;
}

function plyIndexFromMoveNumber(int $moveNum, bool $isBlack, string $sideToMove): int
{
    if ($sideToMove === 'b') {
        return $isBlack ? 2 * ($moveNum - 1) : 2 * ($moveNum - 1) + 1;
    }
    return $isBlack ? 2 * ($moveNum - 1) + 1 : 2 * ($moveNum - 1);
}

/**
 * Parse SAN tokens from a comment fragment, using the first move number to find the start ply.
 *
 * @return list<string> full move list from game start (prefix + continuation)
 */
function parseNumberedSequence(string $text, string $fen, array $mainMoves): array
{
    $text = preg_replace('/1-0|0-1|1\/2-1\/2|\*|\+[-−–—]|✔|✓|\x{2714}/u', ' ', $text) ?? $text;
    $sideToMove = explode(' ', $fen)[1] ?? 'w';

    $startPly = 0;
    if (preg_match('/(\d+)\.(\.\.)?/', $text, $numMatch)) {
        $moveNum = (int) $numMatch[1];
        $isBlack = ($numMatch[2] ?? '') === '..';
        $startPly = plyIndexFromMoveNumber($moveNum, $isBlack, $sideToMove);
    } elseif (preg_match('/Instead\s+(\d+)\.(\.\.)?/', $text, $insteadNum)) {
        $moveNum = (int) $insteadNum[1];
        $isBlack = ($insteadNum[2] ?? '') === '..';
        $startPly = plyIndexFromMoveNumber($moveNum, $isBlack, $sideToMove);
    } elseif (preg_match('/Instead\s+([NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O|O-O)/i', $text, $insteadSan)) {
        $startPly = 0;
        $text = $insteadSan[1] . ' ' . $text;
    }

    if (!preg_match_all('/[NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O(?:\+|#)?|O-O(?:\+|#)?/', $text, $sanMatches)) {
        return [];
    }

    $prefix = array_slice($mainMoves, 0, $startPly);
    if (!isLegalContinuation($fen, $prefix, [])) {
        return [];
    }

    $chess = new Chess();
    $chess->load($fen);
    foreach ($prefix as $san) {
        if (!$chess->move($san)) {
            return [];
        }
    }

    $parsed = [];
    foreach ($sanMatches[0] as $token) {
        $mv = $chess->move($token);
        if (!$mv || !isset($mv['san'])) {
            break;
        }
        $parsed[] = $mv['san'];
    }

    if ($parsed === []) {
        return [];
    }

    return array_merge($prefix, $parsed);
}

/**
 * @return list<string>
 */
function parseSanTokensFromText(string $text, string $fen, int $startMoveIndex): array
{
    $text = preg_replace('/1-0|0-1|1\/2-1\/2|\*|\+[-−–—]|✔|✓|\x{2714}/u', ' ', $text) ?? $text;
    $pattern = '/(?:\d+\.{1,3}\s*)?([NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O(?:\+|#)?|O-O(?:\+|#)?)/';
    if (!preg_match_all($pattern, $text, $matches)) {
        return [];
    }

    $chess = new Chess();
    $chess->load($fen);
    for ($i = 0; $i < $startMoveIndex && $i < 1000; $i++) {
        // replay not needed when startMoveIndex=0
    }

    $moves = [];
    foreach ($matches[1] as $token) {
        try {
            $mv = $chess->move($token);
            if ($mv && isset($mv['san'])) {
                $moves[] = $mv['san'];
            } else {
                break;
            }
        } catch (Throwable) {
            break;
        }
    }

    return $moves;
}

/**
 * @return array{branchAt:int,moves:list<string>,keyMoveIndex:int,keyMoveIndices:list<int>,note:string}|null
 */
function buildAlternativeBranch(array $mainMoves, array $altMoves, string $fen, string $comment): ?array
{
    if ($altMoves === []) {
        return null;
    }

    // Try branch points near the fragment's move number first, then full scan.
    $hint = guessBranchAtFromFragment($fen, $comment);
    $branchCandidates = array_values(array_unique(array_filter([
        $hint,
        max(0, $hint - 1),
        min(count($mainMoves), $hint + 1),
    ], static fn (int $v) => $v >= 0)));

    $best = null;
    foreach ($branchCandidates as $branchAt) {
        $candidate = tryAlternativeBranchAt($mainMoves, $altMoves, $fen, $comment, $branchAt);
        if ($candidate !== null && ($best === null || count($candidate['moves']) > count($best['moves']))) {
            $best = $candidate;
        }
    }

    if ($best === null) {
        for ($branchAt = 0; $branchAt <= count($mainMoves); $branchAt++) {
            $candidate = tryAlternativeBranchAt($mainMoves, $altMoves, $fen, $comment, $branchAt);
            if ($candidate !== null && ($best === null || count($candidate['moves']) > count($best['moves']))) {
                $best = $candidate;
            }
        }
    }

    return $best;
}

/**
 * @return array{branchAt:int,moves:list<string>,keyMoveIndex:int,keyMoveIndices:list<int>,note:string}|null
 */
function tryAlternativeBranchAt(array $mainMoves, array $altMoves, string $fen, string $comment, int $branchAt): ?array
{
        $prefix = array_slice($mainMoves, 0, $branchAt);
        $prefixLen = count($prefix);

        $offset = 0;
        while ($offset < count($altMoves) && $offset < $prefixLen) {
            if (!sanEquivalentAt($fen, $mainMoves, $offset, $altMoves[$offset], $mainMoves[$offset])) {
                break;
            }
            $offset++;
        }

        if ($offset < $prefixLen) {
            return null;
        }

        $continuation = array_slice($altMoves, $offset);
        if ($continuation === []) {
            return null;
        }

        if ($branchAt < count($mainMoves) && sanEquivalentAt($fen, $mainMoves, $branchAt, $continuation[0], $mainMoves[$branchAt])) {
            return null;
        }

        if (!isLegalContinuation($fen, $prefix, $continuation)) {
            return null;
        }

        $fullLine = array_merge($prefix, $continuation);
        $keyIndices = parseKeyMoveIndicesFromComment($comment, $branchAt);
        $keyMoveIndex = $keyIndices !== [] ? max($keyIndices) : count($fullLine) - 1;

        return [
            'branchAt' => $branchAt,
            'moves' => $continuation,
            'keyMoveIndex' => min($keyMoveIndex, count($fullLine) - 1),
            'keyMoveIndices' => $keyIndices,
            'note' => summarizeNote($comment),
        ];
}

function guessBranchAtFromFragment(string $fen, string $comment): int
{
    $sideToMove = explode(' ', $fen)[1] ?? 'w';
    if (preg_match('/(\d+)\.(\.\.)?/', $comment, $m)) {
        return plyIndexFromMoveNumber((int) $m[1], ($m[2] ?? '') === '..', $sideToMove);
    }

    return 0;
}

function summarizeNote(string $comment): string
{
    if (preg_match('/also full points/i', $comment)) {
        return 'also full points';
    }
    if (preg_match('/another winning move/i', $comment)) {
        return 'another winning move';
    }
    if (preg_match('/also wins/i', $comment)) {
        return 'also wins';
    }
    if (preg_match('/worth one point/i', $comment)) {
        return 'worth one point';
    }
    if (preg_match('/\bOr\b/i', $comment)) {
        return 'alternative (Or)';
    }
    if (preg_match('/Easiest is/i', $comment)) {
        return 'easiest line';
    }
    if (preg_match('/For example/i', $comment)) {
        return 'example line';
    }
    if (preg_match('/transposes/i', $comment)) {
        return 'transposes';
    }
    if (preg_match('/another winning line/i', $comment)) {
        return 'another winning line';
    }
    return 'alternative';
}

/**
 * Book commentary after each move — shown in the study panel after solve/reveal.
 *
 * @return list<array{afterPly:int,label:string,text:string}>
 */
function extractStudyNotes(string $gameText, array $mainMoves): array
{
    $moveSection = extractMoveSection($gameText);
    $allComments = extractAllComments($moveSection);
    $intro = $allComments[0] ?? null;
    $body = preg_replace('/^\s*\{[^}]*\}\s*/', '', $moveSection) ?? $moveSection;
    $body = preg_replace('/\s*\*\s*$/', '', $body) ?? $body;

    $notes = [];

    if ($intro !== null) {
        $introText = cleanStudyComment($intro);
        if ($introText !== '') {
            $notes[] = [
                'afterPly' => 0,
                'label' => 'About this exercise',
                'text' => $introText,
            ];
        }
    }

    $ply = 0;
    $pattern = '/\{([^}]*)\}|(\d+\.(?:\.\.)?\s*)?([NBRQK]?[a-h]?[1-8]?x?[a-h][1-8](?:=[NBRQK])?[+#]?|O-O-O(?:\+|#)?|O-O(?:\+|#)?)/u';

    if (!preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
        return $notes;
    }

    foreach ($matches as $match) {
        if (!empty($match[3])) {
            $ply++;
            continue;
        }
        if (!str_starts_with($match[0], '{')) {
            continue;
        }
        $raw = $match[1] ?? '';
        $text = cleanStudyComment($raw);
        if (!shouldIncludeStudyNote($text, $raw)) {
            continue;
        }
        $notes[] = [
            'afterPly' => $ply,
            'label' => buildPlyLabel($ply, $mainMoves),
            'text' => $text,
        ];
    }

    return $notes;
}

function cleanStudyComment(string $text): string
{
    $text = preg_replace('/@@\w+@@/', '', $text) ?? $text;
    $text = str_replace(['@@StartBlockQuote@@', '@@EndBlockQuote@@'], '"', $text);
    if (preg_match('/^[\s✔✓\x{2714}]+$/u', $text)) {
        return '✓ Key move marked in the book';
    }
    $text = preg_replace('/✔|✓|\x{2714}/u', '✓', $text) ?? $text;
    $text = preg_replace('/\(\s*\)/', '', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return trim($text);
}

function shouldIncludeStudyNote(string $text, string $raw = ''): bool
{
    if ($text === '' && hasCheckmark($raw)) {
        return true;
    }
    if ($text === '✓' || $text === '') {
        return hasCheckmark($raw);
    }
    if (strlen($text) < 5 && !hasCheckmark($raw) && !preg_match('/[+#±]|\+-|wins|mate/i', $text)) {
        return false;
    }
    if (preg_match('/^[+\-±=#\s]+$/', $text)) {
        return false;
    }

    return true;
}

function buildPlyLabel(int $afterPly, array $mainMoves): string
{
    if ($afterPly <= 0) {
        return 'Before first move';
    }

    $lastMove = $mainMoves[$afterPly - 1] ?? null;
    $moveNum = (int) ceil($afterPly / 2);
    if ($lastMove) {
        return "After {$moveNum}. …{$lastMove}";
    }

    return "After move {$moveNum}";
}

function parseKeyMoveIndicesFromComment(string $comment, int $branchAt): array
{
    if (!hasCheckmark($comment)) {
        return [];
    }
    $fakeSection = $comment;
    $indices = parseKeyMoveIndices($fakeSection);

    return array_map(static fn (int $i) => $branchAt + $i, $indices);
}

function sanEquivalentAt(string $fen, array $moves, int $index, string $sanA, string $sanB): bool
{
    $c1 = new Chess();
    $c1->load($fen);
    $c2 = new Chess();
    $c2->load($fen);
    for ($i = 0; $i < $index; $i++) {
        if (!isset($moves[$i])) {
            return false;
        }
        if (!$c1->move($moves[$i]) || !$c2->move($moves[$i])) {
            return false;
        }
    }
    $m1 = $c1->move($sanA);
    $m2 = $c2->move($sanB);
    if (!$m1 || !$m2) {
        return preg_replace('/[+#]/', '', $sanA) === preg_replace('/[+#]/', '', $sanB);
    }
    return ($m1['from'] ?? '') === ($m2['from'] ?? '') && ($m1['to'] ?? '') === ($m2['to'] ?? '');
}

function isLegalContinuation(string $fen, array $prefix, array $continuation): bool
{
    $chess = new Chess();
    $chess->load($fen);
    foreach ($prefix as $san) {
        if (!$chess->move($san)) {
            return false;
        }
    }
    foreach ($continuation as $san) {
        if (!$chess->move($san)) {
            return false;
        }
    }
    return true;
}
