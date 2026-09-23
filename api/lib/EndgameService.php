<?php

declare(strict_types=1);

use Ryanhs\Chess\Chess;

final class EndgameService
{
    public const GOALS = ['white_win', 'white_draw', 'draw', 'black_win', 'black_draw'];
    public const LEVELS = [
        'beginner' => ['label' => 'Beginner', 'rating' => 800, 'depth' => 1, 'skill' => 0],
        'intermediate' => ['label' => 'Intermediate', 'rating' => 1400, 'depth' => 2, 'skill' => 7],
        'advanced' => ['label' => 'Advanced', 'rating' => 2000, 'depth' => 3, 'skill' => 14],
        'super' => ['label' => 'Super advanced', 'rating' => 2600, 'depth' => 4, 'skill' => 20],
    ];

    public function normalizeGoal(string $goal): string
    {
        $goal = strtolower(trim($goal));
        if (!in_array($goal, self::GOALS, true)) {
            throw new InvalidArgumentException('Choose a result: White to win, White to draw, Draw, Black to win, or Black to draw.');
        }
        return $goal;
    }

    public function goalLabel(string $goal): string
    {
        return match ($goal) {
            'white_win' => 'White to win',
            'white_draw' => 'White to draw',
            'draw' => 'Draw',
            'black_win' => 'Black to win',
            'black_draw' => 'Black to draw',
            default => $goal,
        };
    }

    public function playerColor(string $goal, string $fen): string
    {
        if (str_starts_with($goal, 'black')) {
            return 'b';
        }
        if (str_starts_with($goal, 'white') || $goal === 'draw') {
            $turn = explode(' ', $fen)[1] ?? 'w';
            return $goal === 'draw' ? ($turn === 'b' ? 'b' : 'w') : 'w';
        }
        return 'w';
    }

    public function analyzeFen(string $fen): array
    {
        $chess = new Chess();
        if (!$chess->load($fen)) {
            throw new InvalidArgumentException('That board position is not a valid FEN.');
        }
        $kings = 0;
        $blackKings = 0;
        foreach (str_split(explode(' ', $fen)[0]) as $ch) {
            if ($ch === 'K') {
                $kings++;
            }
            if ($ch === 'k') {
                $blackKings++;
            }
        }
        if ($kings !== 1 || $blackKings !== 1) {
            throw new InvalidArgumentException('Place exactly one white king and one black king.');
        }
        return $this->positionPayload($chess);
    }

    public function applyMove(string $fen, string $from, string $to, string $promotion = 'q'): array
    {
        $chess = new Chess();
        if (!$chess->load($fen)) {
            throw new InvalidArgumentException('Invalid position.');
        }
        $played = $chess->move([
            'from' => strtolower($from),
            'to' => strtolower($to),
            'promotion' => $promotion ?: 'q',
        ]);
        if (!$played) {
            throw new InvalidArgumentException('That move is not legal.');
        }
        $payload = $this->positionPayload($chess);
        $payload['lastMove'] = [
            'from' => (string) ($played['from'] ?? $from),
            'to' => (string) ($played['to'] ?? $to),
            'san' => (string) ($played['san'] ?? ''),
        ];
        return $payload;
    }

    public function engineMove(string $fen, string $level): array
    {
        $chess = new Chess();
        if (!$chess->load($fen)) {
            throw new InvalidArgumentException('Invalid position.');
        }
        $levelKey = isset(self::LEVELS[$level]) ? $level : 'intermediate';
        $depth = (int) self::LEVELS[$levelKey]['depth'];
        $moves = $chess->moves(['verbose' => true]);
        if ($moves === []) {
            return $this->positionPayload($chess);
        }
        if ($levelKey === 'beginner' && count($moves) > 1 && random_int(0, 100) < 35) {
            $pick = $moves[array_rand($moves)];
        } else {
            $pick = $this->searchBest($chess, $depth);
        }
        if (!$pick) {
            $pick = $moves[0];
        }
        return $this->applyMove($fen, (string) $pick['from'], (string) $pick['to'], (string) ($pick['promotion'] ?? 'q'));
    }

    public function judge(string $goal, array $position, string $playerColor): array
    {
        $result = (string) ($position['result'] ?? '*');
        $over = !empty($position['gameOver']);
        if (!$over) {
            return ['over' => false, 'passed' => false, 'failed' => false, 'result' => '*'];
        }
        $passed = $this->goalPassed($goal, $result, $playerColor);
        return [
            'over' => true,
            'passed' => $passed,
            'failed' => !$passed,
            'result' => $result,
        ];
    }

    public function levels(): array
    {
        $out = [];
        foreach (self::LEVELS as $id => $row) {
            $out[] = array_merge(['id' => $id], $row);
        }
        return $out;
    }

    private function goalPassed(string $goal, string $result, string $playerColor): bool
    {
        $playerWon = ($playerColor === 'w' && $result === '1-0') || ($playerColor === 'b' && $result === '0-1');
        $playerLost = ($playerColor === 'w' && $result === '0-1') || ($playerColor === 'b' && $result === '1-0');
        $draw = $result === '1/2-1/2';
        return match ($goal) {
            'white_win' => $result === '1-0',
            'black_win' => $result === '0-1',
            'white_draw', 'black_draw', 'draw' => $draw || $playerWon,
            default => $playerWon,
        } && !$playerLost;
    }

    private function positionPayload(Chess $chess): array
    {
        $over = $chess->gameOver();
        $result = '*';
        if ($chess->inCheckmate()) {
            $result = $chess->turn() === 'w' ? '0-1' : '1-0';
        } elseif ($over || $chess->inDraw() || $chess->inStalemate() || $chess->insufficientMaterial()) {
            $result = '1/2-1/2';
        }
        $fen = $chess->fen();
        return [
            'fen' => $fen,
            'turn' => $chess->turn(),
            'legalMoves' => $this->legalMoves($fen),
            'gameOver' => $over || $result !== '*',
            'checkmate' => $chess->inCheckmate(),
            'stalemate' => $chess->inStalemate(),
            'draw' => $chess->inDraw() || $chess->insufficientMaterial(),
            'result' => $result,
        ];
    }

    public function legalMoves(string $fen): array
    {
        $chess = new Chess();
        if (!$chess->load($fen)) {
            return [];
        }
        $out = [];
        foreach ($chess->moves(['verbose' => true]) as $move) {
            $out[] = [
                'from' => (string) ($move['from'] ?? ''),
                'to' => (string) ($move['to'] ?? ''),
                'san' => (string) ($move['san'] ?? ''),
                'promotion' => $move['promotion'] ?? null,
            ];
        }
        return $out;
    }

    private function searchBest(Chess $root, int $depth): ?array
    {
        $best = null;
        $bestScore = -99999;
        $us = $root->turn();
        foreach ($root->moves(['verbose' => true]) as $move) {
            $child = new Chess();
            $child->load($root->fen());
            $child->move(['from' => $move['from'], 'to' => $move['to'], 'promotion' => $move['promotion'] ?? 'q']);
            $score = -$this->negamax($child, $depth - 1, -99999, 99999, $us === 'w' ? 'b' : 'w');
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $move;
            }
        }
        return $best;
    }

    private function negamax(Chess $chess, int $depth, int $alpha, int $beta, string $side): int
    {
        if ($chess->inCheckmate()) {
            return -20000 + (4 - $depth);
        }
        if ($chess->inDraw() || $chess->inStalemate() || $chess->insufficientMaterial()) {
            return 0;
        }
        if ($depth <= 0) {
            return $this->evaluate($chess, $side);
        }
        $best = -99999;
        foreach ($chess->moves(['verbose' => true]) as $move) {
            $child = new Chess();
            $child->load($chess->fen());
            $child->move(['from' => $move['from'], 'to' => $move['to'], 'promotion' => $move['promotion'] ?? 'q']);
            $score = -$this->negamax($child, $depth - 1, -$beta, -$alpha, $side === 'w' ? 'b' : 'w');
            if ($score > $best) {
                $best = $score;
            }
            if ($score > $alpha) {
                $alpha = $score;
            }
            if ($alpha >= $beta) {
                break;
            }
        }
        return $best;
    }

    private function evaluate(Chess $chess, string $side): int
    {
        $values = ['p' => 100, 'n' => 320, 'b' => 330, 'r' => 500, 'q' => 900, 'k' => 0];
        $score = 0;
        $fen = explode(' ', $chess->fen())[0];
        for ($i = 0, $n = strlen($fen); $i < $n; $i++) {
            $ch = $fen[$i];
            if (!isset($values[strtolower($ch)])) {
                continue;
            }
            $val = $values[strtolower($ch)];
            $score += ctype_upper($ch) ? $val : -$val;
        }
        return $side === 'w' ? $score : -$score;
    }
}
