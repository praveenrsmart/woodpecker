<?php

declare(strict_types=1);

use Ryanhs\Chess\Chess;

final class ChessService
{
    public function getStopIndex(array $puzzle, string $mode): int
    {
        if ($mode === 'all') {
            return count($puzzle['moves']) - 1;
        }
        return $puzzle['keyMoveIndex'] ?? count($puzzle['moves']) - 1;
    }

    public function getPositionFen(array $puzzle, int $moveIndex): string
    {
        $chess = new Chess();
        $chess->load($puzzle['fen']);
        for ($i = 0; $i < $moveIndex; $i++) {
            $chess->move($puzzle['moves'][$i]);
        }
        return $chess->fen();
    }

    public function moveColorAt(array $puzzle, int $moveIndex): string
    {
        $parts = explode(' ', $puzzle['fen']);
        $start = $parts[1] ?? 'w';
        if ($moveIndex % 2 === 0) {
            return $start;
        }
        return $start === 'w' ? 'b' : 'w';
    }

    public function isPlayerMove(array $puzzle, int $moveIndex): bool
    {
        return $this->moveColorAt($puzzle, $moveIndex) === $puzzle['sideToMove'];
    }

    public function autoPlayOpponentMoves(Chess $chess, array $puzzle, int $fromIndex, int $stopIndex): array
    {
        $index = $fromIndex;
        $lastMove = null;

        while ($index <= $stopIndex && !$this->isPlayerMove($puzzle, $index)) {
            $san = $puzzle['moves'][$index] ?? null;
            if (!$san) {
                break;
            }
            $played = $chess->move($san);
            if (!$played) {
                break;
            }
            $lastMove = ['from' => $played['from'], 'to' => $played['to']];
            $index++;
        }

        return ['nextIndex' => $index, 'lastMove' => $lastMove, 'fen' => $chess->fen()];
    }

    public function movesMatch(string $preFen, string $playedSan, string $expectedSan): bool
    {
        $c1 = new Chess();
        $c1->load($preFen);
        $c2 = new Chess();
        $c2->load($preFen);

        try {
            $m1 = $c1->move($playedSan);
            $m2 = $c2->move($expectedSan);
            if ($m1 && $m2) {
                return $m1['from'] === $m2['from'] && $m1['to'] === $m2['to'];
            }
        } catch (Throwable) {
            // fall through
        }

        return preg_replace('/[+#]/', '', $playedSan) === preg_replace('/[+#]/', '', $expectedSan);
    }

    public function syncAttemptToPlayerTurn(Database $db, array $puzzle, array $cycle, ?array $attempt): ?array
    {
        if (!$attempt || !empty($attempt['completed'])) {
            return $attempt;
        }

        $index = (int) ($attempt['current_move_index'] ?? 0);
        $stopIndex = $this->getStopIndex($puzzle, $cycle['mode']);

        if ($index > $stopIndex) {
            $db->run(
                "UPDATE puzzle_attempts SET completed = 1,
                 completed_at = COALESCE(completed_at, datetime('now'))
                 WHERE id = ?",
                [$attempt['id']]
            );
            return $db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attempt['id']]);
        }

        if ($this->isPlayerMove($puzzle, $index)) {
            return $attempt;
        }

        $chess = new Chess();
        $chess->load($this->getPositionFen($puzzle, $index));
        $auto = $this->autoPlayOpponentMoves($chess, $puzzle, $index, $stopIndex);
        if ($auto['nextIndex'] === $index) {
            return $attempt;
        }

        $completed = $auto['nextIndex'] > $stopIndex ? 1 : 0;
        $db->run(
            "UPDATE puzzle_attempts SET current_move_index = ?, completed = ?,
             completed_at = CASE WHEN ? = 1 THEN datetime('now') ELSE completed_at END
             WHERE id = ?",
            [$auto['nextIndex'], $completed, $completed, $attempt['id']]
        );

        return $db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attempt['id']]);
    }
}
