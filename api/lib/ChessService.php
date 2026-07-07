<?php

declare(strict_types=1);

use Ryanhs\Chess\Chess;

final class ChessService
{
    public function mainLine(array $puzzle): array
    {
        return [
            'moves' => $puzzle['moves'],
            'keyMoveIndex' => $puzzle['keyMoveIndex'] ?? count($puzzle['moves']) - 1,
            'keyMoveIndices' => $puzzle['keyMoveIndices'] ?? [],
        ];
    }

    public function getAttemptLine(array $puzzle, ?array $attempt): array
    {
        if ($attempt && !empty($attempt['active_moves'])) {
            $decoded = json_decode((string) $attempt['active_moves'], true);
            if (is_array($decoded) && !empty($decoded['moves']) && is_array($decoded['moves'])) {
                return [
                    'moves' => $decoded['moves'],
                    'keyMoveIndex' => (int) ($decoded['keyMoveIndex'] ?? count($decoded['moves']) - 1),
                    'keyMoveIndices' => $decoded['keyMoveIndices'] ?? [],
                ];
            }
        }

        return $this->mainLine($puzzle);
    }

    public function puzzleFromLine(array $puzzle, array $line): array
    {
        return array_merge($puzzle, [
            'moves' => $line['moves'],
            'keyMoveIndex' => $line['keyMoveIndex'],
            'keyMoveIndices' => $line['keyMoveIndices'] ?? [],
        ]);
    }

    public function getStopIndex(array $line, string $mode): int
    {
        if ($mode === 'all') {
            return count($line['moves']) - 1;
        }

        return $line['keyMoveIndex'] ?? count($line['moves']) - 1;
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

    public function movesMatchAny(string $preFen, string $playedSan, array $expectedSans): bool
    {
        foreach ($expectedSans as $expectedSan) {
            if ($this->movesMatch($preFen, $playedSan, (string) $expectedSan)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function acceptedMovesAt(array $puzzle, array $line, int $moveIndex): array
    {
        $accepted = [];
        if (isset($line['moves'][$moveIndex])) {
            $accepted[] = $line['moves'][$moveIndex];
        }

        foreach ($puzzle['alternativeLines'] ?? [] as $alt) {
            if ((int) ($alt['branchAt'] ?? -1) !== $moveIndex) {
                continue;
            }
            if (!empty($alt['moves'][0])) {
                $accepted[] = $alt['moves'][0];
            }
        }

        return array_values(array_unique($accepted));
    }

    public function trySwitchLine(array $puzzle, int $moveIndex, string $playedSan): ?array
    {
        $prefix = array_slice($puzzle['moves'], 0, $moveIndex);
        $preFen = $this->getPositionFen($this->puzzleFromLine($puzzle, $this->mainLine($puzzle)), $moveIndex);

        foreach ($puzzle['alternativeLines'] ?? [] as $alt) {
            if ((int) ($alt['branchAt'] ?? -1) !== $moveIndex) {
                continue;
            }
            $firstAlt = $alt['moves'][0] ?? null;
            if (!$firstAlt || !$this->movesMatch($preFen, $playedSan, $firstAlt)) {
                continue;
            }

            $moves = array_merge($prefix, $alt['moves']);
            return [
                'moves' => $moves,
                'keyMoveIndex' => (int) ($alt['keyMoveIndex'] ?? count($moves) - 1),
                'keyMoveIndices' => $alt['keyMoveIndices'] ?? [],
            ];
        }

        return null;
    }

  /** @return array{playedLine:array{label:string,moves:list<string>},otherLines:list<array{label:string,moves:list<string>,note?:string,branchMove?:string}>,variantExercises:list<array{label:string,moves:list<string>,description?:string}>}|null */
    public function getLearningLines(array $puzzle, ?array $attempt, string $mode): ?array
    {
        if (!$attempt || (empty($attempt['completed']) && empty($attempt['solution_revealed']))) {
            return null;
        }

        $playedLine = $this->getAttemptLine($puzzle, $attempt);
        $mainLine = $this->mainLine($puzzle);
        $playedStop = $this->getStopIndex($playedLine, $mode);
        $mainStop = $this->getStopIndex($mainLine, $mode);
        $playedMoves = array_slice($playedLine['moves'], 0, $playedStop + 1);
        $mainMoves = array_slice($mainLine['moves'], 0, $mainStop + 1);

        $out = [
            'playedLine' => [
                'label' => 'Your line',
                'moves' => $playedMoves,
            ],
            'otherLines' => [],
            'variantExercises' => [],
        ];

        if (!$this->linesMatch($playedMoves, $mainMoves)) {
            $out['otherLines'][] = [
                'label' => 'Main line',
                'moves' => $mainMoves,
                'note' => '',
            ];
        }

        foreach ($puzzle['alternativeLines'] ?? [] as $alt) {
            $branchAt = (int) ($alt['branchAt'] ?? -1);
            $altFull = array_merge(
                array_slice($mainLine['moves'], 0, $branchAt),
                $alt['moves']
            );
            $altLine = [
                'moves' => $altFull,
                'keyMoveIndex' => (int) ($alt['keyMoveIndex'] ?? count($altFull) - 1),
            ];
            $altMoves = array_slice($altFull, 0, $this->getStopIndex($altLine, $mode) + 1);
            if ($this->linesMatch($playedMoves, $altMoves)) {
                continue;
            }

            $note = trim((string) ($alt['note'] ?? ''));
            $out['otherLines'][] = [
                'label' => $note !== '' ? ucfirst($note) : 'Alternative line',
                'moves' => $altMoves,
                'note' => $note,
                'branchMove' => $alt['moves'][0] ?? null,
            ];
        }

        if ($out['otherLines'] === []) {
            unset($out['otherLines']);
        }

        $out['fullLine'] = [
            'label' => 'Complete main line',
            'moves' => $mainLine['moves'],
        ];

        if (!empty($puzzle['studyNotes']) && is_array($puzzle['studyNotes'])) {
            $out['studyNotes'] = $puzzle['studyNotes'];
        }

        return $out;
    }

    /** @param list<string> $a @param list<string> $b */
    private function linesMatch(array $a, array $b): bool
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return $a === $b;
        }

        return array_slice($a, 0, $len) === array_slice($b, 0, $len);
    }

    public function syncAttemptToPlayerTurn(Database $db, array $puzzle, array $cycle, ?array $attempt): ?array
    {
        if (!$attempt || !empty($attempt['completed'])) {
            return $attempt;
        }

        $line = $this->getAttemptLine($puzzle, $attempt);
        $activePuzzle = $this->puzzleFromLine($puzzle, $line);
        $index = (int) ($attempt['current_move_index'] ?? 0);
        $stopIndex = $this->getStopIndex($line, $cycle['mode']);

        if ($index > $stopIndex) {
            $db->run(
                "UPDATE puzzle_attempts SET completed = 1,
                 completed_at = COALESCE(completed_at, datetime('now'))
                 WHERE id = ?",
                [$attempt['id']]
            );
            return $db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attempt['id']]);
        }

        if ($this->isPlayerMove($activePuzzle, $index)) {
            return $attempt;
        }

        $chess = new Chess();
        $chess->load($this->getPositionFen($activePuzzle, $index));
        $auto = $this->autoPlayOpponentMoves($chess, $activePuzzle, $index, $stopIndex);
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
