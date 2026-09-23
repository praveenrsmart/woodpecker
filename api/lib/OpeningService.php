<?php

declare(strict_types=1);

use Ryanhs\Chess\Chess;

final class OpeningService
{
    public const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public function parsePgn(string $pgn): array
    {
        $pgn = trim($pgn);
        if ($pgn === '') {
            throw new InvalidArgumentException('Paste a PGN for this chapter.');
        }

        $startFen = self::START_FEN;
        if (preg_match('/\[FEN\s+"([^"]+)"\]/i', $pgn, $fenMatch)) {
            $startFen = $fenMatch[1];
        }

        $chess = new Chess();
        if ($chess->loadPgn($pgn) === false) {
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
        }
        return $payload;
    }
}
