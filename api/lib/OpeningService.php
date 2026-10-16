<?php

declare(strict_types=1);

use Ryanhs\Chess\Chess;

final class OpeningService
{
    public const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    private const BRUSH_BY_LETTER = ['G' => 'green', 'R' => 'red', 'B' => 'blue', 'Y' => 'yellow'];

    /**
     * Splits a PGN into header lines, main-line SAN moves and comments keyed by ply
     * (0 = before the first move). Variations and ";" comments are dropped.
     */
    public function splitPgn(string $pgn): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $pgn));
        $headers = [];
        $index = 0;
        while ($index < count($lines)) {
            $line = trim($lines[$index]);
            if ($line === '' || preg_match('/^\[\w+\s+".*"\]$/', $line)) {
                if ($line !== '') {
                    $headers[] = $line;
                }
                $index++;
                continue;
            }
            break;
        }
        $text = implode("\n", array_slice($lines, $index));

        $sans = [];
        $comments = [];
        $token = '';
        $depth = 0;
        $length = strlen($text);
        $flush = function () use (&$token, &$sans): void {
            $t = preg_replace('/^\d+\.+/', '', $token);
            $token = '';
            $t = preg_replace('/[!?]+$/', '', (string) $t);
            if ($t === '' || $t[0] === '$' || in_array($t, ['*', '1-0', '0-1', '1/2-1/2', '--'], true)) {
                return;
            }
            $sans[] = $t;
        };
        for ($i = 0; $i < $length; $i++) {
            $ch = $text[$i];
            if ($ch === '{') {
                $end = strpos($text, '}', $i + 1);
                if ($end === false) {
                    $end = $length;
                }
                if ($depth === 0) {
                    $flush();
                    $ply = count($sans);
                    $comments[$ply] = trim(($comments[$ply] ?? '') . ' ' . substr($text, $i + 1, $end - $i - 1));
                }
                $i = $end;
                continue;
            }
            if ($ch === ';') {
                $end = strpos($text, "\n", $i);
                $end = $end === false ? $length : $end;
                if ($depth === 0) {
                    $flush();
                    $note = substr($text, $i + 1, $end - ($i + 1));
                    if (preg_match('/%c(?:al|sl)|%c_(?:arrow|highlight)/i', $note)) {
                        $ply = count($sans);
                        $comments[$ply] = trim(($comments[$ply] ?? '') . ' ' . $note);
                    }
                }
                $i = $end;
                continue;
            }
            if ($ch === '[' && $depth === 0) {
                $rest = substr($text, $i);
                if (preg_match('/^\[%(?:cal|csl|c_arrow|c_highlight)\b[^\]]*\]/i', $rest, $tag)) {
                    $flush();
                    $ply = count($sans);
                    $comments[$ply] = trim(($comments[$ply] ?? '') . ' ' . $tag[0]);
                    $i += strlen($tag[0]) - 1;
                    continue;
                }
                if (preg_match('/^\[[A-Za-z0-9]+\s+"[^"]*"\]/', $rest, $headerTag)) {
                    $i += strlen($headerTag[0]) - 1;
                    continue;
                }
            }
            if ($ch === '(' || $ch === ')') {
                if ($depth === 0) {
                    $flush();
                }
                $depth = $ch === '(' ? $depth + 1 : max(0, $depth - 1);
                continue;
            }
            if (ctype_space($ch)) {
                if ($depth === 0) {
                    $flush();
                }
                continue;
            }
            if ($depth === 0) {
                $token .= $ch;
            }
        }
        $flush();

        return ['headers' => $headers, 'sans' => $sans, 'comments' => $comments];
    }

    public function shapesFromComment(string $comment): array
    {
        $shapes = [];
        if (preg_match_all('/\[%csl\s+([^\]]*)\]/i', $comment, $m)) {
            foreach ($m[1] as $list) {
                if (!preg_match_all('/([GRBY])\s*([a-h][1-8])(?!\s*[a-h])/i', $list, $p, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($p as $hit) {
                    $shapes[] = [
                        'orig' => strtolower($hit[2]),
                        'brush' => self::BRUSH_BY_LETTER[strtoupper($hit[1])],
                    ];
                }
            }
        }
        if (preg_match_all('/\[%cal\s+([^\]]*)\]/i', $comment, $m)) {
            foreach ($m[1] as $list) {
                if (!preg_match_all('/([GRBY])\s*([a-h][1-8])\s*([a-h][1-8])/i', $list, $p, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($p as $hit) {
                    $shapes[] = [
                        'orig' => strtolower($hit[2]),
                        'dest' => strtolower($hit[3]),
                        'brush' => self::BRUSH_BY_LETTER[strtoupper($hit[1])],
                    ];
                }
            }
        }
        if (preg_match_all('/\[%c_arrow\s+([^\]]*)\]/i', $comment, $m)) {
            foreach ($m[1] as $body) {
                $shape = $this->chessComShape($body, true);
                if ($shape) {
                    $shapes[] = $shape;
                }
            }
        }
        if (preg_match_all('/\[%c_highlight\s+([^\]]*)\]/i', $comment, $m)) {
            foreach ($m[1] as $body) {
                $shape = $this->chessComShape($body, false);
                if ($shape) {
                    $shapes[] = $shape;
                }
            }
        }
        return $shapes;
    }

    /** Chess.com analysis arrows: [%c_arrow g1f3;from;g1;to;f3;color;green] */
    private function chessComShape(string $body, bool $arrow): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode(';', str_replace(["\r", "\n"], '', $body))), static fn ($part) => $part !== ''));
        $fields = [];
        $start = 0;
        if (isset($parts[0]) && preg_match('/^[a-h][1-8](?:[a-h][1-8])?$/i', $parts[0])) {
            $sq = strtolower($parts[0]);
            if (strlen($sq) === 4) {
                $fields['from'] = substr($sq, 0, 2);
                $fields['to'] = substr($sq, 2, 2);
            } else {
                $fields['square'] = $sq;
            }
            $start = 1;
        }
        for ($i = $start; $i + 1 < count($parts); $i += 2) {
            $fields[strtolower($parts[$i])] = strtolower($parts[$i + 1]);
        }
        $brush = match ($fields['color'] ?? 'green') {
            'red' => 'red',
            'blue' => 'blue',
            'yellow', 'orange' => 'yellow',
            default => 'green',
        };
        $from = $fields['from'] ?? '';
        $to = $fields['to'] ?? '';
        $square = $fields['square'] ?? '';
        if ($arrow && preg_match('/^[a-h][1-8]$/', $from) && preg_match('/^[a-h][1-8]$/', $to)) {
            return ['orig' => $from, 'dest' => $to, 'brush' => $brush];
        }
        if (!$arrow && preg_match('/^[a-h][1-8]$/', $square)) {
            return ['orig' => $square, 'brush' => $brush];
        }
        return null;
    }

    public function pgnHasShapes(array $shapesByPly): bool
    {
        foreach ($shapesByPly as $list) {
            if (is_array($list) && $list !== []) {
                return true;
            }
        }
        return false;
    }

    /** Reads a pasted PGN and rewrites arrow marks into the stored main line. */
    public function importPgn(string $pgn): array
    {
        $parsed = $this->parsePgn($pgn);
        $shapes = $this->chapterShapes($pgn, $parsed['plyCount']);
        $parsed['shapes'] = $shapes;
        $parsed['pgn'] = $this->pgnHasShapes($shapes) ? $this->pgnWithShapes($pgn, $shapes) : trim($pgn);
        return $parsed;
    }

    /** Shapes for every position of the chapter, index = ply (0 = start position). */
    public function chapterShapes(string $pgn, int $plyCount): array
    {
        $comments = $this->splitPgn($pgn)['comments'];
        $out = [];
        for ($ply = 0; $ply <= $plyCount; $ply++) {
            $out[] = $this->shapesFromComment((string) ($comments[$ply] ?? ''));
        }
        return $out;
    }

    /** Rewrites the PGN main line with [%csl]/[%cal] comments for the given shapes (index = ply). */
    public function pgnWithShapes(string $pgn, array $shapesByPly): string
    {
        $parsed = $this->parsePgn($pgn);
        $split = $this->splitPgn($pgn);
        $fenParts = explode(' ', $parsed['startFen']);
        $number = max(1, (int) ($fenParts[5] ?? 1));
        $letters = array_flip(self::BRUSH_BY_LETTER);

        $comment = function (int $ply) use ($split, $shapesByPly, $letters): string {
            $text = trim((string) preg_replace('/\[%(?:cal|csl|c_arrow|c_highlight)\b[^\]]*\]/i', '', (string) ($split['comments'][$ply] ?? '')));
            $circles = [];
            $arrows = [];
            $list = $shapesByPly[$ply] ?? $shapesByPly[(string) $ply] ?? [];
            foreach (is_array($list) ? $list : [] as $shape) {
                $orig = (string) ($shape['orig'] ?? '');
                $dest = (string) ($shape['dest'] ?? '');
                $letter = $letters[(string) ($shape['brush'] ?? 'green')] ?? 'G';
                if (!preg_match('/^[a-h][1-8]$/', $orig)) {
                    continue;
                }
                if ($dest !== '' && $dest !== $orig && preg_match('/^[a-h][1-8]$/', $dest)) {
                    $arrows[] = $letter . $orig . $dest;
                } else {
                    $circles[] = $letter . $orig;
                }
            }
            $body = $text;
            if ($circles) {
                $body .= ' [%csl ' . implode(',', array_unique($circles)) . ']';
            }
            if ($arrows) {
                $body .= ' [%cal ' . implode(',', array_unique($arrows)) . ']';
            }
            $body = trim($body);
            return $body === '' ? '' : '{ ' . $body . ' }';
        };

        $out = [];
        $start = $comment(0);
        if ($start !== '') {
            $out[] = $start;
        }
        $needNumber = true;
        foreach ($parsed['moves'] as $i => $move) {
            if ($move['color'] === 'w') {
                $out[] = $number . '. ' . $move['san'];
            } else {
                $out[] = ($needNumber ? $number . '... ' : '') . $move['san'];
                $number++;
            }
            $needNumber = false;
            $after = $comment($i + 1);
            if ($after !== '') {
                $out[] = $after;
                $needNumber = true;
            }
        }
        $result = '*';
        foreach ($split['headers'] as $header) {
            if (preg_match('/^\[Result\s+"([^"]*)"\]$/', $header, $m) && $m[1] !== '') {
                $result = $m[1];
            }
        }
        $out[] = $result;

        $head = $split['headers'] ? implode("\n", $split['headers']) . "\n\n" : '';
        return $head . implode(' ', $out);
    }

    public function parsePgn(string $pgn): array
    {
        $pgn = trim($pgn);
        $pgn = preg_replace('/^\xEF\xBB\xBF/', '', $pgn) ?? $pgn;
        if ($pgn === '') {
            throw new InvalidArgumentException('Paste a PGN for this chapter.');
        }

        $startFen = self::START_FEN;
        if (preg_match('/\[FEN\s+"([^"]+)"\]/i', $pgn, $fenMatch)) {
            $startFen = $fenMatch[1];
        }

        $split = $this->splitPgn($pgn);
        if ($split['sans'] === []) {
            throw new InvalidArgumentException('That PGN has no moves.');
        }
        $cleanPgn = ($split['headers'] ? implode("\n", $split['headers']) . "\n\n" : '') . implode(' ', $split['sans']);

        $chess = new Chess();
        if ($chess->loadPgn($cleanPgn) === false) {
            throw new InvalidArgumentException('Could not read that PGN. Check the moves and try again.');
        }

        $history = $chess->history(['verbose' => true]);
        if (!is_array($history) || $history === []) {
            throw new InvalidArgumentException('That PGN has no moves.');
        }

        $replay = new Chess();
        if (!$replay->load($startFen)) {
            throw new InvalidArgumentException('The PGN start position is not a valid FEN.');
        }

        $moves = [];
        foreach ($history as $item) {
            $from = (string) ($item['from'] ?? '');
            $to = (string) ($item['to'] ?? '');
            $promotion = $item['promotion'] ?? null;
            $played = $replay->move([
                'from' => $from,
                'to' => $to,
                'promotion' => $promotion ?: 'q',
            ]);
            if (!$played && !empty($item['san'])) {
                $played = $replay->move((string) $item['san']);
            }
            if (!$played) {
                throw new InvalidArgumentException('Could not play move ' . ((string) ($item['san'] ?? ($from . $to))) . ' from the PGN.');
            }
            $color = (string) ($played['color'] ?? $item['color'] ?? $item['turn'] ?? '');
            $moves[] = [
                'san' => (string) ($played['san'] ?? $item['san'] ?? ''),
                'from' => (string) ($played['from'] ?? $from),
                'to' => (string) ($played['to'] ?? $to),
                'color' => $color === 'b' ? 'b' : 'w',
                'promotion' => $played['promotion'] ?? $promotion,
                'fen' => $replay->fen(),
            ];
        }

        return [
            'startFen' => $startFen,
            'moves' => $moves,
            'plyCount' => count($moves),
        ];
    }

    public function playerColor(string $colorGroup): string
    {
        return strtolower($colorGroup) === 'black' ? 'b' : 'w';
    }

    public function fenAt(array $chapter, int $moveIndex): string
    {
        $moves = $this->chapterMoves($chapter);
        if ($moveIndex <= 0) {
            return (string) ($chapter['start_fen'] ?: self::START_FEN);
        }
        $idx = min($moveIndex, count($moves)) - 1;
        return (string) ($moves[$idx]['fen'] ?? $chapter['start_fen'] ?: self::START_FEN);
    }

    public function lastMoveAt(array $chapter, int $moveIndex): ?array
    {
        $moves = $this->chapterMoves($chapter);
        if ($moveIndex <= 0 || $moves === []) {
            return null;
        }
        $idx = min($moveIndex, count($moves)) - 1;
        $move = $moves[$idx];
        return [
            'from' => (string) ($move['from'] ?? ''),
            'to' => (string) ($move['to'] ?? ''),
            'san' => (string) ($move['san'] ?? ''),
        ];
    }

    public function chapterMoves(array $chapter): array
    {
        $decoded = json_decode((string) ($chapter['moves_json'] ?? '[]'), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function skipOpponentMoves(array $chapter, string $playerColor, int $fromIndex): array
    {
        $moves = $this->chapterMoves($chapter);
        $index = $fromIndex;
        $lastMove = $this->lastMoveAt($chapter, $fromIndex);
        while ($index < count($moves)) {
            $color = (string) ($moves[$index]['color'] ?? 'w');
            if ($color === $playerColor) {
                break;
            }
            $lastMove = [
                'from' => (string) ($moves[$index]['from'] ?? ''),
                'to' => (string) ($moves[$index]['to'] ?? ''),
                'san' => (string) ($moves[$index]['san'] ?? ''),
            ];
            $index++;
        }

        return [
            'index' => $index,
            'fen' => $this->fenAt($chapter, $index),
            'lastMove' => $lastMove,
            'completed' => $index >= count($moves),
        ];
    }

    public function legalMoves(string $fen): array
    {
        $chess = new Chess();
        if (!$chess->load($fen)) {
            return [];
        }
        $moves = $chess->moves(['verbose' => true]);
        $out = [];
        foreach ($moves as $move) {
            $out[] = [
                'from' => (string) ($move['from'] ?? ''),
                'to' => (string) ($move['to'] ?? ''),
                'san' => (string) ($move['san'] ?? ''),
                'promotion' => $move['promotion'] ?? null,
            ];
        }
        return $out;
    }

    public function countPlayerMoves(array $moves, string $playerColor): int
    {
        $count = 0;
        foreach ($moves as $move) {
            if (($move['color'] ?? '') === $playerColor) {
                $count++;
            }
        }
        return $count;
    }

    public function publicChapter(array $chapter, bool $includeMoves): array
    {
        $moves = $this->chapterMoves($chapter);
        $payload = [
            'id' => (int) $chapter['id'],
            'openingId' => (int) $chapter['opening_id'],
            'title' => $chapter['title'],
            'sortOrder' => (int) $chapter['sort_order'],
            'plyCount' => count($moves),
            'startFen' => $chapter['start_fen'],
            'createdAt' => $chapter['created_at'] ?? null,
        ];
        if ($includeMoves) {
            $payload['pgn'] = $chapter['pgn'] ?? '';
            $payload['moves'] = $moves;
            $payload['shapes'] = $this->chapterShapes((string) ($chapter['pgn'] ?? ''), count($moves));
        }
        return $payload;
    }
}
