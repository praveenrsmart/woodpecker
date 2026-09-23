<?php

declare(strict_types=1);

use Ryanhs\Chess\Chess;

final class Api
{
    private array $puzzles = [];
    private const VALID_SECTIONS = ['Easy', 'Intermediate', 'Advanced'];

    public function __construct(
        private Database $db,
        private Auth $auth,
        private ChessService $chess,
        private array $config,
        private OpeningService $openings,
        private EndgameService $endgames
    ) {
        if (is_file($config['puzzles_path'])) {
            $decoded = json_decode((string) file_get_contents($config['puzzles_path']), true);
            $this->puzzles = is_array($decoded) ? $decoded : [];
        }
    }

    public function dispatch(string $method, string $path): void
    {
        $routes = [
            'POST /auth/register' => fn () => $this->authRegister(),
            'POST /auth/login' => fn () => $this->authLogin(),
            'POST /auth/reset-password' => fn () => $this->authResetPassword(),
            'GET /auth/me' => fn () => $this->authMe(),
            'POST /academy/register' => fn () => $this->academyRegister(),
            'POST /academy/login' => fn () => $this->academyLogin(),
            'GET /academy/me' => fn () => $this->academyMe(),
            'GET /academy/students' => fn () => $this->academyStudents(),
            'POST /academy/students' => fn () => $this->academyAddStudent(),
            'GET /academy/coaches' => fn () => $this->academyCoaches(),
            'POST /academy/coaches' => fn () => $this->academyAddCoach(),
            'POST /admin/login' => fn () => $this->adminLogin(),
            'GET /admin/me' => fn () => $this->adminMe(),
            'POST /admin/reset-password' => fn () => $this->adminResetPassword(),
            'POST /admin/change-password' => fn () => $this->adminChangePassword(),
            'GET /admin/admins' => fn () => $this->adminListAdmins(),
            'POST /admin/admins' => fn () => $this->adminCreateAdmin(),
            'GET /admin/academies' => fn () => $this->adminAcademies(),
            'POST /admin/academies' => fn () => $this->adminCreateAcademy(),
            'POST /academy/reset-password' => fn () => $this->academyResetPassword(),
            'POST /academy/change-password' => fn () => $this->academyChangePassword(),
            'GET /puzzles' => fn () => $this->getPuzzles(),
            'POST /cycles/start' => fn () => $this->startCycle(),
            'GET /me/cycles/active' => fn () => $this->activeCycles(),
            'GET /me/results' => fn () => $this->myResults(),
            'POST /attempts/start' => fn () => $this->startAttempt(),
            'GET /academy/openings' => fn () => $this->academyOpenings(),
            'POST /academy/openings' => fn () => $this->academyCreateOpening(),
            'POST /academy/openings/parse-pgn' => fn () => $this->academyParsePgn(),
            'GET /me/openings' => fn () => $this->studentOpenings(),
            'GET /me/opening-tests' => fn () => $this->studentOpeningTests(),
            'POST /opening-tests/start' => fn () => $this->startOpeningTest(),
            'GET /academy/endgames' => fn () => $this->academyEndgames(),
            'POST /academy/endgames' => fn () => $this->academyCreateEndgameCategory(),
            'POST /academy/endgames/analyze' => fn () => $this->academyAnalyzeEndgameFen(),
            'GET /me/endgames' => fn () => $this->studentEndgames(),
            'GET /endgame/levels' => fn () => $this->endgameLevels(),
            'POST /endgame/move' => fn () => $this->endgameMove(),
            'POST /endgame/engine-move' => fn () => $this->endgameEngineMove(),
            'POST /endgame-attempts/start' => fn () => $this->startEndgameAttempt(),
        ];

        $key = $method . ' ' . $path;
        if (isset($routes[$key])) {
            $routes[$key]();
            return;
        }

        if (preg_match('#^/academy/students/(\d+)$#', $path, $m) && $method === 'PATCH') {
            $this->academyPatchStudent((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/students/(\d+)$#', $path, $m) && $method === 'DELETE') {
            $this->academyRemoveStudent((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/students/(\d+)/stats$#', $path, $m) && $method === 'GET') {
            $this->academyStudentStats((int) $m[1]);
            return;
        }
        if (preg_match('#^/admin/academies/(\d+)$#', $path, $m) && $method === 'PATCH') {
            $this->adminPatchAcademy((int) $m[1]);
            return;
        }
        if (preg_match('#^/puzzles/(\d+)$#', $path, $m) && $method === 'GET') {
            $this->getPuzzle((int) $m[1]);
            return;
        }
        if (preg_match('#^/cycles/(\d+)/attempts$#', $path, $m) && $method === 'GET') {
            $this->cycleAttempts((int) $m[1]);
            return;
        }
        if (preg_match('#^/students/(\d+)/cycles/(\d+)/attempts$#', $path, $m) && $method === 'GET') {
            $this->studentCycleAttempts((int) $m[1], (int) $m[2]);
            return;
        }
        if (preg_match('#^/attempts/(\d+)/play$#', $path, $m) && $method === 'POST') {
            $this->playMove((int) $m[1]);
            return;
        }
        if (preg_match('#^/attempts/(\d+)/reveal$#', $path, $m) && $method === 'POST') {
            $this->revealSolution((int) $m[1]);
            return;
        }
        if (preg_match('#^/attempts/(\d+)/complete$#', $path, $m) && $method === 'POST') {
            $this->completeAttempt((int) $m[1]);
            return;
        }
        if (preg_match('#^/cycles/(\d+)/complete$#', $path, $m) && $method === 'POST') {
            $this->completeCycle((int) $m[1]);
            return;
        }
        if (preg_match('#^/cycles/(\d+)/progress$#', $path, $m) && $method === 'GET') {
            $this->cycleProgress((int) $m[1]);
            return;
        }
        if (preg_match('#^/cycles/(\d+)/puzzle/(\d+)$#', $path, $m) && $method === 'GET') {
            $this->cyclePuzzle((int) $m[1], (int) $m[2]);
            return;
        }
        if (preg_match('#^/cycles/(\d+)/results$#', $path, $m) && $method === 'GET') {
            $this->cycleResults((int) $m[1]);
            return;
        }
        if (preg_match('#^/students/(\d+)/results$#', $path, $m) && $method === 'GET') {
            $this->studentResults((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/openings/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'GET') {
                $this->academyGetOpening($id);
                return;
            }
            if ($method === 'PATCH') {
                $this->academyPatchOpening($id);
                return;
            }
            if ($method === 'DELETE') {
                $this->academyDeleteOpening($id);
                return;
            }
        }
        if (preg_match('#^/academy/openings/(\d+)/chapters$#', $path, $m) && $method === 'POST') {
            $this->academyAddChapter((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/openings/(\d+)/assign$#', $path, $m) && $method === 'POST') {
            $this->academyAssignOpening((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/openings/(\d+)/progress$#', $path, $m) && $method === 'GET') {
            $this->academyOpeningProgress((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/chapters/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PATCH') {
                $this->academyPatchChapter($id);
                return;
            }
            if ($method === 'DELETE') {
                $this->academyDeleteChapter($id);
                return;
            }
        }
        if (preg_match('#^/me/openings/(\d+)$#', $path, $m) && $method === 'GET') {
            $this->studentGetOpening((int) $m[1]);
            return;
        }
        if (preg_match('#^/opening-tests/(\d+)$#', $path, $m) && $method === 'GET') {
            $this->getOpeningTest((int) $m[1]);
            return;
        }
        if (preg_match('#^/opening-tests/(\d+)/play$#', $path, $m) && $method === 'POST') {
            $this->playOpeningTest((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/endgames/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'GET') {
                $this->academyGetEndgameCategory($id);
                return;
            }
            if ($method === 'PATCH') {
                $this->academyPatchEndgameCategory($id);
                return;
            }
            if ($method === 'DELETE') {
                $this->academyDeleteEndgameCategory($id);
                return;
            }
        }
        if (preg_match('#^/academy/endgames/(\d+)/subcategories$#', $path, $m) && $method === 'POST') {
            $this->academyAddEndgameSubcategory((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/endgames/(\d+)/assign$#', $path, $m) && $method === 'POST') {
            $this->academyAssignEndgame((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/endgames/(\d+)/progress$#', $path, $m) && $method === 'GET') {
            $this->academyEndgameProgress((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/endgame-subcategories/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PATCH') {
                $this->academyPatchEndgameSubcategory($id);
                return;
            }
            if ($method === 'DELETE') {
                $this->academyDeleteEndgameSubcategory($id);
                return;
            }
        }
        if (preg_match('#^/academy/endgame-subcategories/(\d+)/chapters$#', $path, $m) && $method === 'POST') {
            $this->academyAddEndgameChapter((int) $m[1]);
            return;
        }
        if (preg_match('#^/academy/endgame-chapters/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PATCH') {
                $this->academyPatchEndgameChapter($id);
                return;
            }
            if ($method === 'DELETE') {
                $this->academyDeleteEndgameChapter($id);
                return;
            }
        }
        if (preg_match('#^/me/endgames/(\d+)$#', $path, $m) && $method === 'GET') {
            $this->studentGetEndgame((int) $m[1]);
            return;
        }
        if (preg_match('#^/endgame-attempts/(\d+)$#', $path, $m) && $method === 'GET') {
            $this->getEndgameAttempt((int) $m[1]);
            return;
        }
        if (preg_match('#^/endgame-attempts/(\d+)/finish$#', $path, $m) && $method === 'POST') {
            $this->finishEndgameAttempt((int) $m[1]);
            return;
        }

        Http::error('Not found', 404);
    }

    private function sectionPuzzleCount(string $section): int
    {
        $count = count($this->sectionPuzzles($section));
        if ($count > 0) {
            return $count;
        }

        return match ($section) {
            'Easy' => 222,
            'Intermediate' => 762,
            'Advanced' => 144,
            default => 0,
        };
    }

    /** @return list<array<string, mixed>> */
    private function sectionPuzzles(string $section): array
    {
        return array_values(array_filter(
            $this->puzzles,
            static fn (array $p) => ($p['section'] ?? '') === $section
        ));
    }

    private function countCompletedDistinctPuzzles(int $cycleId): int
    {
        $row = $this->db->get(
            'SELECT COUNT(DISTINCT puzzle_id) as c FROM puzzle_attempts WHERE cycle_id = ? AND completed = 1',
            [$cycleId]
        );

        return (int) ($row['c'] ?? 0);
    }

    private function isCycleFullyComplete(array $cycle): bool
    {
        if (!empty($cycle['completed_at'])) {
            return true;
        }

        $section = (string) ($cycle['section_filter'] ?? '');
        if (!in_array($section, self::VALID_SECTIONS, true)) {
            return false;
        }

        $total = $this->sectionPuzzleCount($section);
        if ($total === 0) {
            return false;
        }

        return $this->countCompletedDistinctPuzzles((int) $cycle['id']) >= $total;
    }

    private function autoCompleteCycleIfDone(int $cycleId): ?array
    {
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle || !empty($cycle['completed_at'])) {
            return $cycle;
        }

        if (!$this->isCycleFullyComplete($cycle)) {
            return $cycle;
        }

        $this->finalizeCycleTime($cycleId);

        $this->db->run(
            "UPDATE cycles SET completed_at = datetime('now') WHERE id = ?",
            [$cycleId]
        );

        return $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
    }

    private function finalizeCycleTime(int $cycleId): int
    {
        $finalTimeMs = $this->sumCompletedPuzzleTimes($cycleId);
        $this->db->run(
            "UPDATE cycles SET total_time_ms = ?, cycle_accumulated_ms = ?, cycle_resumed_at = NULL, last_activity_at = NULL WHERE id = ?",
            [$finalTimeMs, $finalTimeMs, $cycleId]
        );

        return $finalTimeMs;
    }

    private function sumCompletedPuzzleTimes(int $cycleId): int
    {
        $row = $this->db->get(
            'SELECT COALESCE(SUM(time_ms), 0) as t FROM puzzle_attempts WHERE cycle_id = ? AND completed = 1',
            [$cycleId]
        );

        return (int) ($row['t'] ?? 0);
    }

    /** @return array<string, mixed> */
    private function mapAttemptToResult(array $attempt): array
    {
        return [
            'id' => $attempt['id'],
            'puzzleId' => $attempt['puzzle_id'],
            'name' => $attempt['puzzle_section'] . ' Exercise ' . $attempt['puzzle_number'],
            'section' => $attempt['puzzle_section'],
            'number' => $attempt['puzzle_number'],
            'timeMs' => (int) ($attempt['time_ms'] ?? 0),
            'completedAt' => $attempt['completed_at'],
            'wrongMoves' => (int) ($attempt['wrong_moves'] ?? 0),
            'solutionRevealed' => !empty($attempt['solution_revealed']),
        ];
    }

    /** @return array{cycle: array<string, mixed>, results: list<array<string, mixed>>, puzzlesCompleted: int, cycle_time_ms: int} */
    private function buildCycleResultsPayload(array $cycle): array
    {
        $attempts = $this->db->all(
            "SELECT * FROM puzzle_attempts WHERE cycle_id = ? AND completed = 1 ORDER BY completed_at ASC",
            [(int) $cycle['id']]
        );

        $results = array_map(fn (array $a) => $this->mapAttemptToResult($a), $attempts);

        return [
            'cycle' => $cycle,
            'results' => $results,
            'puzzlesCompleted' => count($results),
            'cycle_time_ms' => $this->getCycleTimeMs($cycle),
        ];
    }

    private function enrichCycle(?array $cycle): ?array
    {
        if (!$cycle) {
            return null;
        }
        $row = $this->db->get(
            'SELECT COUNT(*) as c FROM puzzle_attempts WHERE cycle_id = ? AND completed = 1',
            [$cycle['id']]
        );
        $puzzlesCompleted = (int) ($row['c'] ?? 0);
        $total = $this->sectionPuzzleCount($cycle['section_filter']) ?: $puzzlesCompleted;
        return array_merge($cycle, [
            'puzzles_completed' => $puzzlesCompleted,
            'puzzle_total' => $total,
        ]);
    }

    private function findPuzzle(int $id): ?array
    {
        foreach ($this->puzzles as $puzzle) {
            if ((int) $puzzle['id'] === $id) {
                return $puzzle;
            }
        }
        return null;
    }

  /** @return list<array{label:string,moves:list<string>,description?:string}> */
    private function findVariantSiblings(array $puzzle): array
    {
        $siblings = [];
        foreach ($this->puzzles as $candidate) {
            if ((int) $candidate['id'] === (int) $puzzle['id']) {
                continue;
            }
            if (($candidate['section'] ?? '') !== ($puzzle['section'] ?? '')) {
                continue;
            }
            if ((int) ($candidate['number'] ?? 0) !== (int) ($puzzle['number'] ?? 0)) {
                continue;
            }
            if (empty($candidate['variant'])) {
                continue;
            }
            $variant = (string) $candidate['variant'];
            $siblings[] = [
                'label' => 'Exercise ' . $candidate['number'] . $variant,
                'moves' => $candidate['moves'],
                'description' => $candidate['description'] ?? '',
            ];
        }

        return $siblings;
    }

    private function learningLinesPayload(?array $puzzle, ?array $attempt, array $cycle): ?array
    {
        if (!$puzzle || !$attempt) {
            return null;
        }

        $lines = $this->chess->getLearningLines($puzzle, $attempt, $cycle['mode']);
        if (!$lines) {
            return null;
        }

        $variants = $this->findVariantSiblings($puzzle);
        if ($variants !== []) {
            $lines['variantExercises'] = $variants;
        } elseif (isset($lines['variantExercises'])) {
            unset($lines['variantExercises']);
        }

        return $lines;
    }

    /**
     * Cycle time is the sum of each completed puzzle's solve time.
     */
    private function getCycleTimeMs(array $cycle): int
    {
        return $this->sumCompletedPuzzleTimes((int) $cycle['id']);
    }

    private function registerStudentAccount(array $body, ?int $createdByAcademyId = null): array
    {
        $name = trim((string) ($body['name'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($name === '') {
            throw new RuntimeException('Name required');
        }
        if ($username === '') {
            throw new RuntimeException('Username required');
        }
        if (strlen($password) < 4) {
            throw new RuntimeException('Password must be at least 4 characters');
        }

        $clean = cleanUsername($username);
        if ($clean === '') {
            throw new RuntimeException('Invalid username');
        }

        try {
            $id = $this->db->run(
                'INSERT INTO students (name, username, password_hash, created_by_academy_id, is_active) VALUES (?, ?, ?, ?, 1)',
                [$name, $clean, hashPassword($password), $createdByAcademyId]
            );
            $student = $this->db->get(
                'SELECT id, name, username, created_at FROM students WHERE id = ?',
                [$id]
            );
            if (!$student) {
                throw new RuntimeException('Failed to create student');
            }
            return $student;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new RuntimeException('Username already taken');
            }
            throw $e;
        }
    }

    private function studentOwnedByAcademy(int $academyId, int $studentId): bool
    {
        $row = $this->db->get(
            'SELECT created_by_academy_id FROM students WHERE id = ?',
            [$studentId]
        );
        return $row !== null && (int) ($row['created_by_academy_id'] ?? 0) === $academyId;
    }

    private function setOwnedStudentLoginActive(int $academyId, int $studentId, bool $active): void
    {
        if (!$this->studentOwnedByAcademy($academyId, $studentId)) {
            return;
        }
        $this->db->run(
            'UPDATE students SET is_active = ? WHERE id = ?',
            [$active ? 1 : 0, $studentId]
        );
    }

    private function academyHasStudent(int $academyId, int $studentId): bool
    {
        return (bool) $this->db->get(
            'SELECT 1 FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [$academyId, $studentId]
        );
    }

    private function ensureAcademyCoach(int $academyId, string $coachName): void
    {
        $name = trim($coachName);
        if ($name === '' || $name === 'Unassigned') {
            return;
        }
        $this->db->run(
            'INSERT OR IGNORE INTO academy_coaches (academy_id, name) VALUES (?, ?)',
            [$academyId, $name]
        );
    }

    private function canAccessStudentStats(array $session, int $studentId): bool
    {
        if ($session['type'] === 'student' && (int) $session['entity']['id'] === $studentId) {
            return true;
        }
        if ($session['type'] === 'academy') {
            return $this->academyHasStudent((int) $session['entity']['id'], $studentId);
        }
        return false;
    }

    private function buildStudentStats(int $studentId): ?array
    {
        $student = $this->db->get(
            'SELECT id, name, username, created_at FROM students WHERE id = ?',
            [$studentId]
        );
        if (!$student) {
            return null;
        }

        $cycles = $this->db->all(
            "SELECT c.*,
              (SELECT COUNT(*) FROM puzzle_attempts pa WHERE pa.cycle_id = c.id AND pa.completed = 1) as puzzles_completed,
              (SELECT SUM(wrong_moves) FROM puzzle_attempts pa WHERE pa.cycle_id = c.id) as total_wrong_moves,
              (SELECT COUNT(*) FROM puzzle_attempts pa WHERE pa.cycle_id = c.id AND pa.solution_revealed = 1) as solutions_revealed,
              (SELECT COUNT(*) FROM puzzle_attempts pa WHERE pa.cycle_id = c.id) as total_attempts
             FROM cycles c WHERE c.student_id = ?
             ORDER BY CASE c.section_filter
               WHEN 'Easy' THEN 0 WHEN 'Intermediate' THEN 1 WHEN 'Advanced' THEN 2 ELSE 3 END,
               c.cycle_number DESC",
            [$studentId]
        );

        $allAttempts = $this->db->all(
            "SELECT pa.* FROM puzzle_attempts pa
             JOIN cycles c ON c.id = pa.cycle_id
             WHERE c.student_id = ? ORDER BY pa.completed_at DESC",
            [$studentId]
        );

        $revealedAttempts = $this->db->all(
            "SELECT pa.puzzle_section, pa.puzzle_number, pa.puzzle_id, pa.revealed_at, pa.wrong_moves,
              c.section_filter, c.cycle_number
             FROM puzzle_attempts pa
             JOIN cycles c ON c.id = pa.cycle_id
             WHERE c.student_id = ? AND pa.solution_revealed = 1
             ORDER BY pa.revealed_at DESC",
            [$studentId]
        );

        $totalTime = 0;
        $totalWrong = 0;
        $enrichedCycles = [];
        foreach ($cycles as $c) {
            $cycleTimeMs = $this->getCycleTimeMs($c);
            $totalTime += $cycleTimeMs;
            $enrichedCycles[] = array_merge($c, ['cycle_time_ms' => $cycleTimeMs]);
        }
        foreach ($allAttempts as $a) {
            $totalWrong += (int) ($a['wrong_moves'] ?? 0);
        }

        return [
            'student' => $student,
            'cycles' => $enrichedCycles,
            'totalTime' => $totalTime,
            'totalWrong' => $totalWrong,
            'totalRevealed' => count($revealedAttempts),
            'revealedAttempts' => $revealedAttempts,
            'attemptCount' => count($allAttempts),
        ];
    }

    private function buildCycleResults(int $cycleId): ?array
    {
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle) {
            return null;
        }

        return $this->buildCycleResultsPayload($cycle);
    }

    private function authRegister(): void
    {
        try {
            $student = $this->registerStudentAccount(Http::body());
            $token = $this->auth->createToken('student', (int) $student['id']);
            Http::json(['token' => $token, 'student' => $student], 201);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'taken') ? 409 : 400;
            Http::error($e->getMessage(), $status);
        }
    }

    private function authLogin(): void
    {
        $body = Http::body();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            Http::error('Username and password required', 400);
        }

        $student = $this->db->get('SELECT * FROM students WHERE username = ?', [cleanUsername($username)]);
        if (!$student || !verifyPassword($password, $student['password_hash'])) {
            Http::error('Invalid username or password', 401);
        }
        if (isset($student['is_active']) && (int) $student['is_active'] === 0) {
            Http::error('This account has been disabled. Contact your academy.', 403);
        }

        Http::json([
            'token' => $this->auth->createToken('student', (int) $student['id']),
            'student' => sanitizeStudent($student),
        ]);
    }

    private function authMe(): void
    {
        $session = $this->auth->requireStudent();
        Http::json(['student' => $session['entity']]);
    }

    private function authResetPassword(): void
    {
        $body = Http::body();
        $username = trim((string) ($body['username'] ?? ''));
        $studentId = (int) ($body['studentId'] ?? 0);
        $newPassword = (string) ($body['newPassword'] ?? '');

        if ($username === '' || $studentId < 1) {
            Http::error('Username and Student ID are required', 400);
        }
        if (strlen($newPassword) < 4) {
            Http::error('Password must be at least 4 characters', 400);
        }

        $clean = cleanUsername($username);
        if ($clean === '') {
            Http::error('Invalid username', 400);
        }

        $student = $this->db->get(
            'SELECT id, is_active FROM students WHERE id = ? AND username = ?',
            [$studentId, $clean]
        );
        if (!$student) {
            Http::error('Username and Student ID do not match our records', 404);
        }
        if (isset($student['is_active']) && (int) $student['is_active'] === 0) {
            Http::error('This account has been disabled. Contact your academy.', 403);
        }

        $this->db->run(
            'UPDATE students SET password_hash = ? WHERE id = ?',
            [hashPassword($newPassword), $studentId]
        );

        Http::json([
            'ok' => true,
            'message' => 'Password updated successfully. You can sign in with your new password.',
        ]);
    }

    private function requireNewPassword(string $password): void
    {
        if (strlen($password) < 4) {
            Http::error('Password must be at least 4 characters', 400);
        }
    }

    private function academyResetPassword(): void
    {
        $body = Http::body();
        $username = trim((string) ($body['username'] ?? ''));
        $academyId = (int) ($body['academyId'] ?? 0);
        $newPassword = (string) ($body['newPassword'] ?? '');

        if ($username === '' || $academyId < 1) {
            Http::error('Username and Academy ID are required', 400);
        }
        $this->requireNewPassword($newPassword);

        $clean = cleanUsername($username);
        if ($clean === '') {
            Http::error('Invalid username', 400);
        }

        $academy = $this->db->get(
            'SELECT id, is_active FROM academies WHERE id = ? AND username = ?',
            [$academyId, $clean]
        );
        if (!$academy) {
            Http::error('Username and Academy ID do not match our records', 404);
        }
        if (isset($academy['is_active']) && (int) $academy['is_active'] === 0) {
            Http::error('This academy account has been disabled. Contact the super admin.', 403);
        }

        $this->db->run(
            'UPDATE academies SET password_hash = ? WHERE id = ?',
            [hashPassword($newPassword), $academyId]
        );

        Http::json([
            'ok' => true,
            'message' => 'Password updated successfully. You can sign in with your new password.',
        ]);
    }

    private function academyChangePassword(): void
    {
        $session = $this->auth->requireAcademy();
        $body = Http::body();
        $current = (string) ($body['currentPassword'] ?? '');
        $newPassword = (string) ($body['newPassword'] ?? '');
        $this->requireNewPassword($newPassword);

        $academy = $this->db->get(
            'SELECT password_hash FROM academies WHERE id = ?',
            [(int) $session['entity']['id']]
        );
        if (!$academy || !verifyPassword($current, $academy['password_hash'])) {
            Http::error('Current password is incorrect', 400);
        }

        $this->db->run(
            'UPDATE academies SET password_hash = ? WHERE id = ?',
            [hashPassword($newPassword), (int) $session['entity']['id']]
        );

        Http::json([
            'ok' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    private function academyRegister(): void
    {
        $body = Http::body();
        $name = trim((string) ($body['name'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($name === '') {
            Http::error('Academy name required', 400);
        }
        if ($username === '') {
            Http::error('Username required', 400);
        }
        if (strlen($password) < 4) {
            Http::error('Password must be at least 4 characters', 400);
        }

        $clean = cleanUsername($username);
        if ($clean === '') {
            Http::error('Invalid username', 400);
        }

        try {
            $id = $this->db->run(
                'INSERT INTO academies (name, username, password_hash) VALUES (?, ?, ?)',
                [$name, $clean, hashPassword($password)]
            );
            $academy = $this->db->get(
                'SELECT id, name, username, created_at FROM academies WHERE id = ?',
                [$id]
            );
            Http::json([
                'token' => $this->auth->createToken('academy', (int) $academy['id']),
                'academy' => $academy,
            ], 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                Http::error('Username already taken', 409);
            }
            throw $e;
        }
    }

    private function academyLogin(): void
    {
        $body = Http::body();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            Http::error('Username and password required', 400);
        }

        $academy = $this->db->get('SELECT * FROM academies WHERE username = ?', [cleanUsername($username)]);
        if (!$academy || !verifyPassword($password, $academy['password_hash'])) {
            Http::error('Invalid username or password', 401);
        }
        if (isset($academy['is_active']) && (int) $academy['is_active'] === 0) {
            Http::error('This academy account has been disabled. Contact the super admin.', 403);
        }

        Http::json([
            'token' => $this->auth->createToken('academy', (int) $academy['id']),
            'academy' => [
                'id' => $academy['id'],
                'name' => $academy['name'],
                'username' => $academy['username'],
                'created_at' => $academy['created_at'],
            ],
        ]);
    }

    private function academyMe(): void
    {
        $session = $this->auth->requireAcademy();
        Http::json(['academy' => $session['entity']]);
    }

    private function academyStudents(): void
    {
        $session = $this->auth->requireAcademy();
        $academyId = (int) $session['entity']['id'];
        $query = Http::query();
        $search = strtolower(trim((string) ($query['search'] ?? '')));
        $coach = trim((string) ($query['coach'] ?? ''));
        $status = (string) ($query['status'] ?? 'active');

        $sql = "
            SELECT s.id, s.name, s.username, s.created_at, a.coach_name, a.added_at, a.is_active,
              s.created_by_academy_id,
              COALESCE(s.is_active, 1) as login_active,
              (SELECT COUNT(*) FROM cycles c WHERE c.student_id = s.id AND c.completed_at IS NOT NULL) as completed_cycles,
              (SELECT COUNT(*) FROM puzzle_attempts pa
               JOIN cycles c ON c.id = pa.cycle_id
               WHERE c.student_id = s.id AND pa.solution_revealed = 1) as solutions_revealed,
              (SELECT COALESCE(SUM(pa.time_ms), 0)
               FROM puzzle_attempts pa
               JOIN cycles c ON c.id = pa.cycle_id
               WHERE c.student_id = s.id AND pa.completed = 1) as total_training_ms,
              (SELECT GROUP_CONCAT(c.section_filter || ' #' || c.cycle_number, ', ')
               FROM cycles c
               WHERE c.student_id = s.id AND c.completed_at IS NULL) as active_cycles_label,
              (SELECT COALESCE(SUM(pa.time_ms), 0)
               FROM puzzle_attempts pa
               JOIN cycles c ON c.id = pa.cycle_id
               WHERE c.student_id = s.id AND c.completed_at IS NULL AND pa.completed = 1) as active_cycles_time_ms
            FROM academy_students a
            JOIN students s ON s.id = a.student_id
            WHERE a.academy_id = ?
        ";
        $params = [$academyId];

        if ($status === 'active') {
            $sql .= ' AND a.is_active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND a.is_active = 0';
        }

        if ($coach !== '') {
            $sql .= ' AND a.coach_name = ?';
            $params[] = $coach;
        }

        if ($search !== '') {
            $sql .= ' AND (LOWER(s.name) LIKE ? OR LOWER(s.username) LIKE ? OR CAST(s.id AS TEXT) LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY a.is_active DESC, a.coach_name, s.name';
        $students = $this->db->all($sql, $params);

        $totalRow = $this->db->get('SELECT COUNT(*) as c FROM academy_students WHERE academy_id = ?', [$academyId]);
        $activeRow = $this->db->get(
            'SELECT COUNT(*) as c FROM academy_students WHERE academy_id = ? AND is_active = 1',
            [$academyId]
        );
        $inactiveRow = $this->db->get(
            'SELECT COUNT(*) as c FROM academy_students WHERE academy_id = ? AND is_active = 0',
            [$academyId]
        );

        Http::json([
            'students' => $students,
            'counts' => [
                'total' => (int) ($totalRow['c'] ?? 0),
                'active' => (int) ($activeRow['c'] ?? 0),
                'inactive' => (int) ($inactiveRow['c'] ?? 0),
                'showing' => count($students),
            ],
        ]);
    }

    private function academyCoaches(): void
    {
        $session = $this->auth->requireAcademy();
        $coaches = array_column(
            $this->db->all(
                'SELECT name FROM academy_coaches WHERE academy_id = ? ORDER BY name',
                [(int) $session['entity']['id']]
            ),
            'name'
        );
        Http::json(['coaches' => $coaches]);
    }

    private function academyAddCoach(): void
    {
        $session = $this->auth->requireAcademy();
        $name = trim((string) (Http::body()['name'] ?? ''));
        if ($name === '') {
            Http::error('Coach name required', 400);
        }

        try {
            $this->db->run(
                'INSERT INTO academy_coaches (academy_id, name) VALUES (?, ?)',
                [(int) $session['entity']['id'], $name]
            );
            Http::json(['name' => $name], 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                Http::error('Coach already exists', 409);
            }
            throw $e;
        }
    }

    private function academyAddStudent(): void
    {
        $session = $this->auth->requireAcademy();
        $body = Http::body();
        $academyId = (int) $session['entity']['id'];
        $tag = trim((string) ($body['coachName'] ?? '')) ?: 'Unassigned';
        $this->ensureAcademyCoach($academyId, $tag);

        $id = (int) ($body['studentId'] ?? 0);
        $created = false;
        $plainPassword = null;

        $createdStudent = null;
        if ($id <= 0 && trim((string) ($body['name'] ?? '')) !== '') {
            try {
                $createdStudent = $this->registerStudentAccount($body, $academyId);
            } catch (RuntimeException $e) {
                $status = str_contains($e->getMessage(), 'taken') ? 409 : 400;
                Http::error($e->getMessage(), $status);
            }
            $id = (int) $createdStudent['id'];
            $created = true;
            $plainPassword = (string) ($body['password'] ?? '');
        }

        if ($id <= 0) {
            Http::error('Provide a student name, username and password, or an existing student ID', 400);
        }

        $student = $this->db->get('SELECT id, name, username FROM students WHERE id = ?', [$id]);
        if (!$student) {
            Http::error('Student not found', 404);
        }

        try {
            $this->db->run(
                'INSERT INTO academy_students (academy_id, student_id, coach_name, is_active) VALUES (?, ?, ?, 1)',
                [$academyId, $id, $tag]
            );
            $payload = [
                'student' => $student,
                'coachName' => $tag,
                'created' => $created,
            ];
            if ($created) {
                $payload['username'] = $student['username'];
                $payload['password'] = $plainPassword;
                $payload['message'] = 'Student login created. Give the username and password to the player.';
            }
            Http::json($payload, 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $existing = $this->db->get(
                    'SELECT is_active FROM academy_students WHERE academy_id = ? AND student_id = ?',
                    [$academyId, $id]
                );
                if ($existing) {
                    $this->db->run(
                        'UPDATE academy_students SET coach_name = ?, is_active = 1 WHERE academy_id = ? AND student_id = ?',
                        [$tag, $academyId, $id]
                    );
                    $this->setOwnedStudentLoginActive($academyId, $id, true);
                    Http::json([
                        'student' => $student,
                        'coachName' => $tag,
                        'reactivated' => empty($existing['is_active']),
                    ]);
                    return;
                }
                Http::error('Student already added to your academy', 409);
            }
            throw $e;
        }
    }

    private function academyPatchStudent(int $studentId): void
    {
        $session = $this->auth->requireAcademy();
        $body = Http::body();
        $coachName = $body['coachName'] ?? null;
        $active = $body['active'] ?? null;
        $password = $body['password'] ?? null;

        if ($coachName === null && $active === null && $password === null) {
            Http::error('Nothing to update', 400);
        }

        $link = $this->db->get(
            'SELECT * FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [(int) $session['entity']['id'], $studentId]
        );
        if (!$link) {
            Http::error('Student not linked to academy', 404);
        }

        if ($coachName !== null) {
            $coach = trim((string) $coachName);
            if ($coach === '') {
                Http::error('Coach name required', 400);
            }
            $this->ensureAcademyCoach((int) $session['entity']['id'], $coach);
            $this->db->run(
                'UPDATE academy_students SET coach_name = ? WHERE academy_id = ? AND student_id = ?',
                [$coach, (int) $session['entity']['id'], $studentId]
            );
        }

        if ($active !== null) {
            $isActive = $active ? 1 : 0;
            $this->db->run(
                'UPDATE academy_students SET is_active = ? WHERE academy_id = ? AND student_id = ?',
                [$isActive, (int) $session['entity']['id'], $studentId]
            );
            $this->setOwnedStudentLoginActive((int) $session['entity']['id'], $studentId, (bool) $active);
        }

        if ($password !== null) {
            if (strlen((string) $password) < 4) {
                Http::error('Password must be at least 4 characters', 400);
            }
            $this->db->run(
                'UPDATE students SET password_hash = ? WHERE id = ?',
                [hashPassword((string) $password), $studentId]
            );
        }

        $updated = $this->db->get(
            'SELECT coach_name, is_active FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [(int) $session['entity']['id'], $studentId]
        );

        Http::json([
            'ok' => true,
            'coachName' => $updated['coach_name'],
            'isActive' => !empty($updated['is_active']),
            'passwordUpdated' => $password !== null,
        ]);
    }

    private function academyRemoveStudent(int $studentId): void
    {
        $session = $this->auth->requireAcademy();
        $academyId = (int) $session['entity']['id'];
        $link = $this->db->get(
            'SELECT id FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [$academyId, $studentId]
        );
        if (!$link) {
            Http::error('Student not linked to academy', 404);
        }

        $this->setOwnedStudentLoginActive($academyId, $studentId, false);
        $this->db->changes(
            'DELETE FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [$academyId, $studentId]
        );
        Http::json(['ok' => true]);
    }

    private function adminLogin(): void
    {
        $body = Http::body();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            Http::error('Username and password required', 400);
        }

        $admin = $this->db->get('SELECT * FROM super_admins WHERE username = ?', [cleanUsername($username)]);
        if (!$admin || !verifyPassword($password, $admin['password_hash'])) {
            Http::error('Invalid username or password', 401);
        }

        Http::json([
            'token' => $this->auth->createToken('admin', (int) $admin['id']),
            'admin' => sanitizeAdmin($admin),
        ]);
    }

    private function adminMe(): void
    {
        $session = $this->auth->requireAdmin();
        Http::json(['admin' => $session['entity']]);
    }

    private function adminResetPassword(): void
    {
        $body = Http::body();
        $username = trim((string) ($body['username'] ?? ''));
        $adminId = (int) ($body['adminId'] ?? 0);
        $newPassword = (string) ($body['newPassword'] ?? '');

        if ($username === '' || $adminId < 1) {
            Http::error('Username and Admin ID are required', 400);
        }
        $this->requireNewPassword($newPassword);

        $clean = cleanUsername($username);
        if ($clean === '') {
            Http::error('Invalid username', 400);
        }

        $admin = $this->db->get(
            'SELECT id FROM super_admins WHERE id = ? AND username = ?',
            [$adminId, $clean]
        );
        if (!$admin) {
            Http::error('Username and Admin ID do not match our records', 404);
        }

        $this->db->run(
            'UPDATE super_admins SET password_hash = ? WHERE id = ?',
            [hashPassword($newPassword), $adminId]
        );

        Http::json([
            'ok' => true,
            'message' => 'Password updated successfully. You can sign in with your new password.',
        ]);
    }

    private function adminChangePassword(): void
    {
        $session = $this->auth->requireAdmin();
        $body = Http::body();
        $current = (string) ($body['currentPassword'] ?? '');
        $newPassword = (string) ($body['newPassword'] ?? '');
        $this->requireNewPassword($newPassword);

        $admin = $this->db->get(
            'SELECT password_hash FROM super_admins WHERE id = ?',
            [(int) $session['entity']['id']]
        );
        if (!$admin || !verifyPassword($current, $admin['password_hash'])) {
            Http::error('Current password is incorrect', 400);
        }

        $this->db->run(
            'UPDATE super_admins SET password_hash = ? WHERE id = ?',
            [hashPassword($newPassword), (int) $session['entity']['id']]
        );

        Http::json([
            'ok' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    private function adminListAdmins(): void
    {
        $this->auth->requireAdmin();
        $admins = $this->db->all(
            'SELECT id, name, username, created_at FROM super_admins ORDER BY id ASC'
        );
        Http::json(['admins' => $admins]);
    }

    private function adminCreateAdmin(): void
    {
        $this->auth->requireAdmin();
        $body = Http::body();
        $name = trim((string) ($body['name'] ?? '')) ?: 'Super Admin';
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '') {
            Http::error('Username required', 400);
        }
        $this->requireNewPassword($password);

        $clean = cleanUsername($username);
        if ($clean === '') {
            Http::error('Invalid username', 400);
        }

        try {
            $id = $this->db->run(
                'INSERT INTO super_admins (name, username, password_hash) VALUES (?, ?, ?)',
                [$name, $clean, hashPassword($password)]
            );
            $admin = $this->db->get(
                'SELECT id, name, username, created_at FROM super_admins WHERE id = ?',
                [$id]
            );
            Http::json([
                'admin' => $admin,
                'username' => $clean,
                'password' => $password,
                'message' => 'Super admin login created. Save the username and password.',
            ], 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                Http::error('Username already taken', 409);
            }
            throw $e;
        }
    }

    private function adminAcademies(): void
    {
        $this->auth->requireAdmin();
        $academies = $this->db->all("
            SELECT a.id, a.name, a.username, a.created_at, COALESCE(a.is_active, 1) as is_active,
              (SELECT COUNT(*) FROM academy_students s WHERE s.academy_id = a.id) as student_count,
              (SELECT COUNT(*) FROM academy_students s WHERE s.academy_id = a.id AND s.is_active = 1) as active_students
            FROM academies a
            ORDER BY a.created_at DESC, a.id DESC
        ");
        Http::json(['academies' => $academies]);
    }

    private function adminCreateAcademy(): void
    {
        $this->auth->requireAdmin();
        $body = Http::body();
        $name = trim((string) ($body['name'] ?? ''));
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($name === '') {
            Http::error('Academy name required', 400);
        }
        if ($username === '') {
            Http::error('Username required', 400);
        }
        if (strlen($password) < 4) {
            Http::error('Password must be at least 4 characters', 400);
        }

        $clean = cleanUsername($username);
        if ($clean === '') {
            Http::error('Invalid username', 400);
        }

        try {
            $id = $this->db->run(
                'INSERT INTO academies (name, username, password_hash, is_active) VALUES (?, ?, ?, 1)',
                [$name, $clean, hashPassword($password)]
            );
            $academy = $this->db->get(
                'SELECT id, name, username, created_at, is_active FROM academies WHERE id = ?',
                [$id]
            );
            Http::json([
                'academy' => $academy,
                'username' => $clean,
                'password' => $password,
                'message' => 'Academy login created. Give the username and password to the academy.',
            ], 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                Http::error('Username already taken', 409);
            }
            throw $e;
        }
    }

    private function adminPatchAcademy(int $academyId): void
    {
        $this->auth->requireAdmin();
        $body = Http::body();
        $academy = $this->db->get('SELECT id FROM academies WHERE id = ?', [$academyId]);
        if (!$academy) {
            Http::error('Academy not found', 404);
        }

        $name = $body['name'] ?? null;
        $password = $body['password'] ?? null;
        $active = $body['active'] ?? null;

        if ($name === null && $password === null && $active === null) {
            Http::error('Nothing to update', 400);
        }

        if ($name !== null) {
            $cleanName = trim((string) $name);
            if ($cleanName === '') {
                Http::error('Academy name required', 400);
            }
            $this->db->run('UPDATE academies SET name = ? WHERE id = ?', [$cleanName, $academyId]);
        }

        if ($password !== null) {
            if (strlen((string) $password) < 4) {
                Http::error('Password must be at least 4 characters', 400);
            }
            $this->db->run(
                'UPDATE academies SET password_hash = ? WHERE id = ?',
                [hashPassword((string) $password), $academyId]
            );
        }

        if ($active !== null) {
            $this->db->run(
                'UPDATE academies SET is_active = ? WHERE id = ?',
                [$active ? 1 : 0, $academyId]
            );
        }

        $updated = $this->db->get(
            'SELECT id, name, username, created_at, COALESCE(is_active, 1) as is_active FROM academies WHERE id = ?',
            [$academyId]
        );
        Http::json([
            'ok' => true,
            'academy' => $updated,
            'passwordUpdated' => $password !== null,
        ]);
    }

    private function academyStudentStats(int $studentId): void
    {
        $session = $this->auth->requireAcademy();
        if (!$this->academyHasStudent((int) $session['entity']['id'], $studentId)) {
            Http::error('Student not linked to your academy', 403);
        }

        $stats = $this->buildStudentStats($studentId);
        if (!$stats) {
            Http::error('Student not found', 404);
        }

        $link = $this->db->get(
            'SELECT coach_name, added_at FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [(int) $session['entity']['id'], $studentId]
        );

        Http::json(array_merge($stats, [
            'coachName' => $link['coach_name'] ?? null,
            'addedAt' => $link['added_at'] ?? null,
        ]));
    }

    private function getPuzzles(): void
    {
        $section = Http::query()['section'] ?? null;
        $filtered = $this->puzzles;
        if ($section && $section !== 'all') {
            $filtered = array_values(array_filter(
                $this->puzzles,
                static fn (array $p) => $p['section'] === $section
            ));
        }

        Http::json([
            'total' => count($filtered),
            'puzzles' => array_map(static fn (array $p) => [
                'id' => $p['id'],
                'section' => $p['section'],
                'number' => $p['number'],
                'fen' => $p['fen'],
                'description' => $p['description'],
                'sideToMove' => $p['sideToMove'],
            ], $filtered),
        ]);
    }

    private function getPuzzle(int $id): void
    {
        $this->auth->requireStudent();
        $puzzle = $this->findPuzzle($id);
        if (!$puzzle) {
            Http::error('Puzzle not found', 404);
        }

        Http::json([
            'id' => $puzzle['id'],
            'section' => $puzzle['section'],
            'number' => $puzzle['number'],
            'fen' => $puzzle['fen'],
            'description' => $puzzle['description'],
            'sideToMove' => $puzzle['sideToMove'],
            'moves' => $puzzle['moves'],
            'keyMoveIndex' => $puzzle['keyMoveIndex'] ?? null,
            'keyMoveIndices' => $puzzle['keyMoveIndices'] ?? null,
            'variant' => $puzzle['variant'] ?? null,
        ]);
    }

    private function cycleAttempts(int $cycleId): void
    {
        $session = $this->auth->requireSession();
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle) {
            Http::error('Cycle not found', 404);
        }
        if (!$this->canAccessStudentStats($session, (int) $cycle['student_id'])) {
            Http::error('Access denied', 403);
        }

        $attempts = $this->db->all(
            'SELECT * FROM puzzle_attempts WHERE cycle_id = ? ORDER BY puzzle_id',
            [$cycleId]
        );
        Http::json(['cycle' => $cycle, 'attempts' => $attempts]);
    }

    private function studentCycleAttempts(int $studentId, int $cycleNum): void
    {
        $section = Http::query()['section'] ?? null;
        $cycle = null;

        if ($section) {
            $cycle = $this->db->get(
                'SELECT * FROM cycles WHERE student_id = ? AND section_filter = ? AND cycle_number = ?',
                [$studentId, $section, $cycleNum]
            );
        } else {
            $matches = $this->db->all(
                'SELECT * FROM cycles WHERE student_id = ? AND cycle_number = ?',
                [$studentId, $cycleNum]
            );
            if (count($matches) === 1) {
                $cycle = $matches[0];
            } elseif (count($matches) > 1) {
                Http::error('Section query parameter required', 400);
            }
        }

        if (!$cycle) {
            Http::error('Cycle not found', 404);
        }

        $attempts = $this->db->all(
            'SELECT * FROM puzzle_attempts WHERE cycle_id = ? ORDER BY puzzle_id',
            [$cycle['id']]
        );
        Http::json(['cycle' => $cycle, 'attempts' => $attempts]);
    }

    private function startCycle(): void
    {
        $session = $this->auth->requireStudent();
        $body = Http::body();
        $mode = (string) ($body['mode'] ?? 'all');
        $sectionFilter = (string) ($body['sectionFilter'] ?? '');
        $studentId = (int) $session['entity']['id'];

        if (!in_array($sectionFilter, self::VALID_SECTIONS, true)) {
            Http::error('Section must be Easy, Intermediate, or Advanced', 400);
        }

        $existing = $this->db->get(
            "SELECT * FROM cycles
             WHERE student_id = ? AND section_filter = ? AND completed_at IS NULL",
            [$studentId, $sectionFilter]
        );

        if ($existing) {
            $existing = $this->autoCompleteCycleIfDone((int) $existing['id']);
        }

        if ($existing && empty($existing['completed_at'])) {
            Http::json(array_merge($this->enrichCycle($existing), ['resumed' => true]));
            return;
        }

        $lastCycle = $this->db->get(
            'SELECT MAX(cycle_number) as max FROM cycles WHERE student_id = ? AND section_filter = ?',
            [$studentId, $sectionFilter]
        );
        $cycleNumber = ((int) ($lastCycle['max'] ?? 0)) + 1;

        try {
            $id = $this->db->run(
                'INSERT INTO cycles (student_id, cycle_number, mode, section_filter) VALUES (?, ?, ?, ?)',
                [$studentId, $cycleNumber, $mode, $sectionFilter]
            );
            $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$id]);
            Http::json(array_merge($this->enrichCycle($cycle), ['resumed' => false]), 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $active = $this->db->get(
                    "SELECT * FROM cycles
                     WHERE student_id = ? AND section_filter = ? AND completed_at IS NULL",
                    [$studentId, $sectionFilter]
                );
                if ($active) {
                    $active = $this->autoCompleteCycleIfDone((int) $active['id']);
                    if ($active && empty($active['completed_at'])) {
                        Http::json(array_merge($this->enrichCycle($active), ['resumed' => true]));
                        return;
                    }
                }
            }
            throw $e;
        }
    }

    private function activeCycles(): void
    {
        $session = $this->auth->requireStudent();
        $cycles = $this->db->all(
            "SELECT c.*,
              (SELECT COUNT(*) FROM puzzle_attempts pa WHERE pa.cycle_id = c.id AND pa.completed = 1) as puzzles_completed,
              (SELECT SUM(wrong_moves) FROM puzzle_attempts pa WHERE pa.cycle_id = c.id) as total_wrong_moves
             FROM cycles c
             WHERE c.student_id = ? AND c.completed_at IS NULL
               AND c.section_filter IN ('Easy', 'Intermediate', 'Advanced')
             ORDER BY CASE c.section_filter
               WHEN 'Easy' THEN 0 WHEN 'Intermediate' THEN 1 WHEN 'Advanced' THEN 2 ELSE 3 END",
            [(int) $session['entity']['id']]
        );

        $stillActive = [];
        foreach ($cycles as $cycle) {
            $updated = $this->autoCompleteCycleIfDone((int) $cycle['id']);
            if ($updated && empty($updated['completed_at'])) {
                $stillActive[] = array_merge($cycle, $updated);
            }
        }
        $cycles = $stillActive;

        $enriched = array_map(function (array $c) {
            return array_merge($c, [
                'puzzle_total' => $this->sectionPuzzleCount($c['section_filter']),
                'cycle_time_ms' => $this->getCycleTimeMs($c),
            ]);
        }, $cycles);

        Http::json(['cycles' => $enriched]);
    }

    private function startAttempt(): void
    {
        $session = $this->auth->requireStudent();
        $body = Http::body();
        $cycleId = (int) ($body['cycleId'] ?? 0);
        $puzzleId = (int) ($body['puzzleId'] ?? 0);
        $puzzleNumber = (int) ($body['puzzleNumber'] ?? 0);
        $puzzleSection = (string) ($body['puzzleSection'] ?? '');

        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle || (int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        $existing = $this->db->get(
            'SELECT * FROM puzzle_attempts WHERE cycle_id = ? AND puzzle_id = ? AND completed = 0',
            [$cycleId, $puzzleId]
        );

        if ($existing) {
            $this->db->run(
                "UPDATE puzzle_attempts SET started_at = datetime('now') WHERE id = ?",
                [(int) $existing['id']]
            );
            $existing['started_at'] = date('Y-m-d H:i:s');
            $puzzle = $this->findPuzzle($puzzleId);
            $synced = $puzzle
                ? $this->chess->syncAttemptToPlayerTurn($this->db, $puzzle, $cycle, $existing)
                : $existing;
            unset($synced['time_ms'], $synced['accumulated_time_ms'], $synced['last_resumed_at']);
            Http::json($synced);
            return;
        }

        $id = $this->db->run(
            "INSERT INTO puzzle_attempts (cycle_id, puzzle_id, puzzle_number, puzzle_section, started_at)
             VALUES (?, ?, ?, ?, datetime('now'))",
            [$cycleId, $puzzleId, $puzzleNumber, $puzzleSection]
        );
        $attempt = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$id]);
        unset($attempt['time_ms'], $attempt['accumulated_time_ms'], $attempt['last_resumed_at']);
        Http::json($attempt, 201);
    }

    private function playMove(int $attemptId): void
    {
        $session = $this->auth->requireStudent();
        $body = Http::body();
        $from = (string) ($body['from'] ?? '');
        $to = (string) ($body['to'] ?? '');
        $promotion = (string) ($body['promotion'] ?? 'q');

        $attempt = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
        if (!$attempt) {
            Http::error('Attempt not found', 404);
        }
        if (!empty($attempt['completed'])) {
            Http::error('Puzzle already completed', 400);
        }

        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$attempt['cycle_id']]);
        if ((int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        $puzzle = $this->findPuzzle((int) $attempt['puzzle_id']);
        if (!$puzzle) {
            Http::error('Puzzle not found', 404);
        }

        $attempt = $this->chess->syncAttemptToPlayerTurn($this->db, $puzzle, $cycle, $attempt);
        $line = $this->chess->getAttemptLine($puzzle, $attempt);
        $activePuzzle = $this->chess->puzzleFromLine($puzzle, $line);

        if (!empty($attempt['completed'])) {
            Http::json([
                'correct' => true,
                'fen' => $this->chess->getPositionFen($activePuzzle, (int) $attempt['current_move_index']),
                'completed' => true,
                'moveIndex' => (int) $attempt['current_move_index'],
            ]);
            return;
        }

        $moveIndex = (int) ($attempt['current_move_index'] ?? 0);
        $accepted = $this->chess->acceptedMovesAt($puzzle, $line, $moveIndex);
        if ($accepted === []) {
            Http::error('No more moves expected', 400);
        }

        if (!$this->chess->isPlayerMove($activePuzzle, $moveIndex)) {
            Http::json([
                'correct' => false,
                'fen' => $this->chess->getPositionFen($activePuzzle, $moveIndex),
                'moveIndex' => $moveIndex,
                'wrongMoves' => (int) $attempt['wrong_moves'],
                'opponentTurn' => true,
            ]);
            return;
        }

        $preFen = $this->chess->getPositionFen($activePuzzle, $moveIndex);
        $chess = new Chess();
        $chess->load($preFen);

        $played = $chess->move(['from' => $from, 'to' => $to, 'promotion' => $promotion]);
        if (!$played) {
            Http::json([
                'correct' => false,
                'fen' => $preFen,
                'wrongMoves' => (int) $attempt['wrong_moves'],
            ]);
            return;
        }

        $matched = $this->chess->movesMatchAny($preFen, $played['san'], $accepted);
        $switchedLine = null;
        if ($matched && isset($line['moves'][$moveIndex]) && !$this->chess->movesMatch($preFen, $played['san'], $line['moves'][$moveIndex])) {
            $switchedLine = $this->chess->trySwitchLine($puzzle, $moveIndex, $played['san']);
            if ($switchedLine) {
                $line = $switchedLine;
                $activePuzzle = $this->chess->puzzleFromLine($puzzle, $line);
            }
        } elseif (!$matched) {
            $switchedLine = $this->chess->trySwitchLine($puzzle, $moveIndex, $played['san']);
            if ($switchedLine) {
                $matched = true;
                $line = $switchedLine;
                $activePuzzle = $this->chess->puzzleFromLine($puzzle, $line);
            }
        }

        if (!$matched) {
            $wrongList = json_decode($attempt['wrong_move_list'] ?: '[]', true) ?: [];
            $wrongList[] = ['move' => $played['san'], 'at' => gmdate('c')];
            $this->db->run(
                'UPDATE puzzle_attempts SET wrong_moves = wrong_moves + 1, wrong_move_list = ? WHERE id = ?',
                [json_encode($wrongList), $attemptId]
            );
            $updated = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
            Http::json([
                'correct' => false,
                'fen' => $preFen,
                'wrongMoves' => (int) $updated['wrong_moves'],
                'wrongMoveList' => $wrongList,
                'canReveal' => (int) $updated['wrong_moves'] >= 3 && empty($updated['solution_revealed']),
            ]);
            return;
        }

        if ($switchedLine) {
            $this->db->run(
                'UPDATE puzzle_attempts SET active_moves = ? WHERE id = ?',
                [json_encode($line), $attemptId]
            );
        }

        $nextIndex = $moveIndex + 1;
        $stopIndex = $this->chess->getStopIndex($line, $cycle['mode']);
        $lastMove = ['from' => $played['from'], 'to' => $played['to']];
        $currentIndex = $nextIndex;

        if ($currentIndex <= $stopIndex && !$this->chess->isPlayerMove($activePuzzle, $currentIndex)) {
            $auto = $this->chess->autoPlayOpponentMoves($chess, $activePuzzle, $currentIndex, $stopIndex);
            $currentIndex = $auto['nextIndex'];
            if ($auto['lastMove']) {
                $lastMove = $auto['lastMove'];
            }
        }

        $newFen = $chess->fen();

        if ($currentIndex > $stopIndex) {
            $this->db->run(
                "UPDATE puzzle_attempts SET current_move_index = ?, completed = 1, completed_at = datetime('now') WHERE id = ?",
                [$currentIndex, $attemptId]
            );
            $completedAttempt = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
            $response = [
                'correct' => true,
                'fen' => $newFen,
                'lastMove' => $lastMove,
                'completed' => true,
                'moveIndex' => $currentIndex,
            ];
            $learningLines = $this->learningLinesPayload($puzzle, $completedAttempt, $cycle);
            if ($learningLines) {
                $response['learningLines'] = $learningLines;
            }
            $this->autoCompleteCycleIfDone((int) $cycle['id']);
            Http::json($response);
            return;
        }

        $this->db->run(
            'UPDATE puzzle_attempts SET current_move_index = ? WHERE id = ?',
            [$currentIndex, $attemptId]
        );
        Http::json([
            'correct' => true,
            'fen' => $newFen,
            'lastMove' => $lastMove,
            'completed' => false,
            'moveIndex' => $currentIndex,
        ]);
    }

    private function revealSolution(int $attemptId): void
    {
        $session = $this->auth->requireStudent();
        $attempt = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
        if (!$attempt) {
            Http::error('Attempt not found', 404);
        }

        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$attempt['cycle_id']]);
        if ((int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        if ((int) $attempt['wrong_moves'] < 3) {
            Http::error('Need at least 3 wrong attempts before revealing', 400);
        }

        $this->db->run(
            "UPDATE puzzle_attempts SET solution_revealed = 1, revealed_at = datetime('now') WHERE id = ?",
            [$attemptId]
        );

        $puzzle = $this->findPuzzle((int) $attempt['puzzle_id']);
        $updated = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
        $line = $this->chess->getAttemptLine($puzzle, $updated);
        $stopIndex = $this->chess->getStopIndex($line, $cycle['mode']);

        $response = [
            'attempt' => $updated,
            'solution' => array_slice($line['moves'], 0, $stopIndex + 1),
        ];
        $learningLines = $this->learningLinesPayload($puzzle, $updated, $cycle);
        if ($learningLines) {
            $response['learningLines'] = $learningLines;
        }
        Http::json($response);
    }

    private function completeAttempt(int $attemptId): void
    {
        $session = $this->auth->requireStudent();

        $attempt = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
        if (!$attempt) {
            Http::error('Attempt not found', 404);
        }

        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$attempt['cycle_id']]);
        if ((int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        $body = Http::body();
        $timeMs = max(0, (int) ($body['timeMs'] ?? 0));
        if ($timeMs < 1 && !empty($attempt['started_at'])) {
            $elapsed = $this->db->get(
                "SELECT CAST((julianday('now') - julianday(started_at)) * 86400000 AS INTEGER) as elapsed_ms
                 FROM puzzle_attempts WHERE id = ?",
                [$attemptId]
            );
            $timeMs = max(0, (int) ($elapsed['elapsed_ms'] ?? 0));
        }

        $this->db->run(
            "UPDATE puzzle_attempts SET completed = 1, completed_at = datetime('now'), time_ms = ? WHERE id = ?",
            [$timeMs, $attemptId]
        );

        $this->finalizeCycleTime((int) $cycle['id']);

        $completed = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);

        $this->autoCompleteCycleIfDone((int) $cycle['id']);

        Http::json($completed);
    }

    private function completeCycle(int $cycleId): void
    {
        $session = $this->auth->requireStudent();
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle || (int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        $finalTimeMs = $this->finalizeCycleTime($cycleId);

        $this->db->run(
            "UPDATE cycles SET completed_at = datetime('now') WHERE id = ?",
            [$cycleId]
        );

        $result = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        $result['cycle_time_ms'] = $finalTimeMs;
        Http::json($result);
    }

    private function cycleProgress(int $cycleId): void
    {
        $session = $this->auth->requireStudent();
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle) {
            Http::error('Cycle not found', 404);
        }
        if ((int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        $cycle = $this->autoCompleteCycleIfDone($cycleId) ?? $cycle;

        $attempts = $this->db->all(
            'SELECT * FROM puzzle_attempts WHERE cycle_id = ? ORDER BY puzzle_id',
            [$cycleId]
        );
        $completed = count(array_filter($attempts, static fn (array $a) => !empty($a['completed'])));

        Http::json([
            'cycle' => $cycle,
            'attempts' => $attempts,
            'completed' => $completed,
            'total' => count($attempts),
            'cycle_time_ms' => $this->getCycleTimeMs($cycle),
        ]);
    }

    private function cyclePuzzle(int $cycleId, int $puzzleId): void
    {
        $session = $this->auth->requireStudent();
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle || (int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        $puzzle = $this->findPuzzle($puzzleId);
        if (!$puzzle) {
            Http::error('Puzzle not found', 404);
        }

        $payload = [
            'id' => $puzzle['id'],
            'section' => $puzzle['section'],
            'number' => $puzzle['number'],
            'fen' => $puzzle['fen'],
            'description' => $puzzle['description'],
            'sideToMove' => $puzzle['sideToMove'],
        ];

        $attempt = $this->db->get(
            'SELECT * FROM puzzle_attempts WHERE cycle_id = ? AND puzzle_id = ? ORDER BY id DESC LIMIT 1',
            [$cycleId, $puzzleId]
        );

        if ($attempt && empty($attempt['completed'])) {
            $attempt = $this->chess->syncAttemptToPlayerTurn($this->db, $puzzle, $cycle, $attempt);
        }

        if ($attempt && (int) ($attempt['current_move_index'] ?? 0) > 0) {
            $line = $this->chess->getAttemptLine($puzzle, $attempt);
            $activePuzzle = $this->chess->puzzleFromLine($puzzle, $line);
            $payload['fen'] = $this->chess->getPositionFen($activePuzzle, (int) $attempt['current_move_index']);
        }

        if ($attempt && !empty($attempt['solution_revealed'])) {
            $line = $this->chess->getAttemptLine($puzzle, $attempt);
            $stopIndex = $this->chess->getStopIndex($line, $cycle['mode']);
            $payload['moves'] = array_slice($line['moves'], 0, $stopIndex + 1);
            $payload['keyMoveIndex'] = $line['keyMoveIndex'];
            $payload['keyMoveIndices'] = $line['keyMoveIndices'] ?? [];
        }

        $updatedCycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        unset($updatedCycle['total_time_ms']);
        unset($updatedCycle['cycle_accumulated_ms']);
        unset($updatedCycle['cycle_resumed_at']);
        unset($updatedCycle['last_activity_at']);

        if ($attempt) {
            unset($attempt['time_ms']);
            unset($attempt['accumulated_time_ms']);
            unset($attempt['last_resumed_at']);
        }

        $response = ['puzzle' => $payload, 'attempt' => $attempt, 'cycle' => $updatedCycle];
        $learningLines = $this->learningLinesPayload($puzzle, $attempt, $cycle);
        if ($learningLines) {
            $response['learningLines'] = $learningLines;
        }
        Http::json($response);
    }

    private function myResults(): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];

        $cycles = $this->db->all(
            "SELECT * FROM cycles WHERE student_id = ?
             ORDER BY CASE section_filter
               WHEN 'Easy' THEN 0 WHEN 'Intermediate' THEN 1 WHEN 'Advanced' THEN 2 ELSE 3 END,
               cycle_number DESC",
            [$studentId]
        );

        $cycleResults = array_map(function (array $c) {
            return $this->buildCycleResultsPayload($c);
        }, $cycles);

        $totalTime = 0;
        foreach ($cycleResults as $entry) {
            $totalTime += (int) ($entry['cycle_time_ms'] ?? 0);
        }

        Http::json([
            'student' => $session['entity'],
            'cycles' => $cycleResults,
            'totalTime' => $totalTime,
        ]);
    }

    private function cycleResults(int $cycleId): void
    {
        $session = $this->auth->requireStudent();
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle) {
            Http::error('Cycle not found', 404);
        }
        if ((int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        Http::json($this->buildCycleResults($cycleId));
    }

    private function studentResults(int $studentId): void
    {
        $session = $this->auth->requireSession();
        if (!$this->canAccessStudentStats($session, $studentId)) {
            Http::error('Access denied', 403);
        }

        $student = $this->db->get('SELECT id, name, username FROM students WHERE id = ?', [$studentId]);
        if (!$student) {
            Http::error('Student not found', 404);
        }

        $cycles = $this->db->all(
            "SELECT * FROM cycles WHERE student_id = ?
             ORDER BY CASE section_filter
               WHEN 'Easy' THEN 0 WHEN 'Intermediate' THEN 1 WHEN 'Advanced' THEN 2 ELSE 3 END,
               cycle_number DESC",
            [$studentId]
        );

        $cycleResults = array_map(function (array $c) {
            return $this->buildCycleResultsPayload($c);
        }, $cycles);

        $totalTime = 0;
        foreach ($cycleResults as $entry) {
            $totalTime += (int) ($entry['cycle_time_ms'] ?? 0);
        }

        Http::json(['student' => $student, 'cycles' => $cycleResults, 'totalTime' => $totalTime]);
    }

    private function requireOpeningColor(string $color): string
    {
        $color = strtolower(trim($color));
        if (!in_array($color, ['white', 'black'], true)) {
            Http::error('Choose White or Black for this opening.', 400);
        }
        return $color;
    }

    private function academyOpeningOrFail(int $openingId, int $academyId): array
    {
        $opening = $this->db->get(
            'SELECT * FROM openings WHERE id = ? AND academy_id = ?',
            [$openingId, $academyId]
        );
        if (!$opening) {
            Http::error('Opening not found', 404);
        }
        return $opening;
    }

    private function academyChapterOrFail(int $chapterId, int $academyId): array
    {
        $chapter = $this->db->get(
            'SELECT c.*, o.academy_id, o.color_group, o.name as opening_name
             FROM opening_chapters c
             JOIN openings o ON o.id = c.opening_id
             WHERE c.id = ? AND o.academy_id = ?',
            [$chapterId, $academyId]
        );
        if (!$chapter) {
            Http::error('Chapter not found', 404);
        }
        return $chapter;
    }

    private function studentAssignedOpening(int $studentId, int $openingId): ?array
    {
        return $this->db->get(
            'SELECT o.*
             FROM openings o
             JOIN opening_assignments a ON a.opening_id = o.id
             WHERE o.id = ? AND a.student_id = ?',
            [$openingId, $studentId]
        );
    }

    private function openingChapterCount(int $openingId): int
    {
        $row = $this->db->get(
            'SELECT COUNT(*) as c FROM opening_chapters WHERE opening_id = ?',
            [$openingId]
        );
        return (int) ($row['c'] ?? 0);
    }

    private function openingAssignedCount(int $openingId): int
    {
        $row = $this->db->get(
            'SELECT COUNT(*) as c FROM opening_assignments WHERE opening_id = ?',
            [$openingId]
        );
        return (int) ($row['c'] ?? 0);
    }

    private function openingChapters(int $openingId): array
    {
        return $this->db->all(
            'SELECT * FROM opening_chapters WHERE opening_id = ? ORDER BY sort_order, id',
            [$openingId]
        );
    }

    private function serializeOpening(array $opening, bool $includeChapters, bool $includePgn): array
    {
        $payload = [
            'id' => (int) $opening['id'],
            'academyId' => (int) $opening['academy_id'],
            'colorGroup' => $opening['color_group'],
            'name' => $opening['name'],
            'notes' => $opening['notes'] ?? '',
            'createdAt' => $opening['created_at'] ?? null,
            'chapterCount' => $this->openingChapterCount((int) $opening['id']),
            'assignedCount' => $this->openingAssignedCount((int) $opening['id']),
        ];
        if ($includeChapters) {
            $payload['chapters'] = array_map(
                fn (array $chapter) => $this->openings->publicChapter($chapter, $includePgn),
                $this->openingChapters((int) $opening['id'])
            );
        }
        return $payload;
    }

    private function studentOpeningStats(int $studentId, int $openingId): array
    {
        $chapterCount = $this->openingChapterCount($openingId);
        $testsTaken = $this->db->get(
            'SELECT COUNT(*) as c FROM opening_tests WHERE student_id = ? AND opening_id = ? AND completed_at IS NOT NULL',
            [$studentId, $openingId]
        );
        $chaptersTested = $this->db->get(
            'SELECT COUNT(DISTINCT chapter_id) as c FROM opening_tests
             WHERE student_id = ? AND opening_id = ? AND completed_at IS NOT NULL',
            [$studentId, $openingId]
        );
        $chaptersPassed = $this->db->get(
            'SELECT COUNT(DISTINCT chapter_id) as c FROM opening_tests
             WHERE student_id = ? AND opening_id = ? AND completed_at IS NOT NULL AND passed = 1',
            [$studentId, $openingId]
        );
        $wrong = $this->db->get(
            'SELECT COALESCE(SUM(wrong_moves), 0) as c FROM opening_tests
             WHERE student_id = ? AND opening_id = ? AND completed_at IS NOT NULL',
            [$studentId, $openingId]
        );
        $last = $this->db->get(
            'SELECT completed_at FROM opening_tests
             WHERE student_id = ? AND opening_id = ? AND completed_at IS NOT NULL
             ORDER BY completed_at DESC LIMIT 1',
            [$studentId, $openingId]
        );

        return [
            'chapterCount' => $chapterCount,
            'testsTaken' => (int) ($testsTaken['c'] ?? 0),
            'chaptersTested' => (int) ($chaptersTested['c'] ?? 0),
            'chaptersPassed' => (int) ($chaptersPassed['c'] ?? 0),
            'wrongMoves' => (int) ($wrong['c'] ?? 0),
            'testTaken' => (int) ($testsTaken['c'] ?? 0) > 0,
            'lastTestAt' => $last['completed_at'] ?? null,
        ];
    }

    private function academyParsePgn(): void
    {
        $this->auth->requireAcademy();
        $body = Http::body();
        try {
            $parsed = $this->openings->parsePgn((string) ($body['pgn'] ?? ''));
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        Http::json($parsed);
    }

    private function academyOpenings(): void
    {
        $session = $this->auth->requireAcademy();
        $academyId = (int) $session['entity']['id'];
        $rows = $this->db->all(
            "SELECT * FROM openings WHERE academy_id = ?
             ORDER BY CASE color_group WHEN 'white' THEN 0 ELSE 1 END, name",
            [$academyId]
        );
        Http::json([
            'openings' => array_map(fn (array $row) => $this->serializeOpening($row, true, true), $rows),
        ]);
    }

    private function academyCreateOpening(): void
    {
        $session = $this->auth->requireAcademy();
        $body = Http::body();
        $name = trim((string) ($body['name'] ?? ''));
        $color = $this->requireOpeningColor((string) ($body['colorGroup'] ?? $body['color'] ?? ''));
        $notes = trim((string) ($body['notes'] ?? ''));
        if ($name === '') {
            Http::error('Opening name is required', 400);
        }
        $id = $this->db->run(
            'INSERT INTO openings (academy_id, color_group, name, notes) VALUES (?, ?, ?, ?)',
            [(int) $session['entity']['id'], $color, $name, $notes]
        );
        $opening = $this->db->get('SELECT * FROM openings WHERE id = ?', [$id]);
        Http::json($this->serializeOpening($opening, true, true), 201);
    }

    private function academyGetOpening(int $openingId): void
    {
        $session = $this->auth->requireAcademy();
        $opening = $this->academyOpeningOrFail($openingId, (int) $session['entity']['id']);
        $assigned = $this->db->all(
            'SELECT s.id, s.name, s.username
             FROM opening_assignments a
             JOIN students s ON s.id = a.student_id
             WHERE a.opening_id = ?
             ORDER BY s.name',
            [$openingId]
        );
        Http::json(array_merge($this->serializeOpening($opening, true, true), [
            'assignedStudents' => $assigned,
        ]));
    }

    private function academyPatchOpening(int $openingId): void
    {
        $session = $this->auth->requireAcademy();
        $opening = $this->academyOpeningOrFail($openingId, (int) $session['entity']['id']);
        $body = Http::body();
        $name = array_key_exists('name', $body) ? trim((string) $body['name']) : (string) $opening['name'];
        $notes = array_key_exists('notes', $body) ? trim((string) $body['notes']) : (string) ($opening['notes'] ?? '');
        $color = array_key_exists('colorGroup', $body) || array_key_exists('color', $body)
            ? $this->requireOpeningColor((string) ($body['colorGroup'] ?? $body['color'] ?? ''))
            : (string) $opening['color_group'];
        if ($name === '') {
            Http::error('Opening name is required', 400);
        }
        $this->db->run(
            'UPDATE openings SET name = ?, notes = ?, color_group = ? WHERE id = ?',
            [$name, $notes, $color, $openingId]
        );
        $updated = $this->db->get('SELECT * FROM openings WHERE id = ?', [$openingId]);
        Http::json($this->serializeOpening($updated, true, true));
    }

    private function academyDeleteOpening(int $openingId): void
    {
        $session = $this->auth->requireAcademy();
        $this->academyOpeningOrFail($openingId, (int) $session['entity']['id']);
        $this->db->run('DELETE FROM opening_tests WHERE opening_id = ?', [$openingId]);
        $this->db->run('DELETE FROM opening_assignments WHERE opening_id = ?', [$openingId]);
        $this->db->run('DELETE FROM opening_chapters WHERE opening_id = ?', [$openingId]);
        $this->db->run('DELETE FROM openings WHERE id = ?', [$openingId]);
        Http::json(['ok' => true]);
    }

    private function academyAddChapter(int $openingId): void
    {
        $session = $this->auth->requireAcademy();
        $opening = $this->academyOpeningOrFail($openingId, (int) $session['entity']['id']);
        $body = Http::body();
        $title = trim((string) ($body['title'] ?? ''));
        $pgn = (string) ($body['pgn'] ?? '');
        try {
            $parsed = $this->openings->parsePgn($pgn);
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        $count = $this->openingChapterCount($openingId);
        if ($title === '') {
            $title = 'Chapter ' . ($count + 1);
        }
        $id = $this->db->run(
            'INSERT INTO opening_chapters (opening_id, title, pgn, start_fen, moves_json, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$openingId, $title, trim($pgn), $parsed['startFen'], json_encode($parsed['moves']), $count]
        );
        $chapter = $this->db->get('SELECT * FROM opening_chapters WHERE id = ?', [$id]);
        Http::json([
            'opening' => $this->serializeOpening($opening, true, true),
            'chapter' => $this->openings->publicChapter($chapter, true),
        ], 201);
    }

    private function academyPatchChapter(int $chapterId): void
    {
        $session = $this->auth->requireAcademy();
        $chapter = $this->academyChapterOrFail($chapterId, (int) $session['entity']['id']);
        $body = Http::body();
        $title = array_key_exists('title', $body) ? trim((string) $body['title']) : (string) $chapter['title'];
        $pgn = array_key_exists('pgn', $body) ? (string) $body['pgn'] : (string) $chapter['pgn'];
        $sortOrder = array_key_exists('sortOrder', $body) ? (int) $body['sortOrder'] : (int) $chapter['sort_order'];
        if ($title === '') {
            Http::error('Chapter name is required', 400);
        }
        try {
            $parsed = $this->openings->parsePgn($pgn);
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        $this->db->run(
            'UPDATE opening_chapters SET title = ?, pgn = ?, start_fen = ?, moves_json = ?, sort_order = ? WHERE id = ?',
            [$title, trim($pgn), $parsed['startFen'], json_encode($parsed['moves']), $sortOrder, $chapterId]
        );
        $updated = $this->db->get('SELECT * FROM opening_chapters WHERE id = ?', [$chapterId]);
        Http::json($this->openings->publicChapter($updated, true));
    }

    private function academyDeleteChapter(int $chapterId): void
    {
        $session = $this->auth->requireAcademy();
        $chapter = $this->academyChapterOrFail($chapterId, (int) $session['entity']['id']);
        $this->db->run('DELETE FROM opening_tests WHERE chapter_id = ?', [$chapterId]);
        $this->db->run('DELETE FROM opening_chapters WHERE id = ?', [$chapterId]);
        $opening = $this->db->get('SELECT * FROM openings WHERE id = ?', [$chapter['opening_id']]);
        Http::json($this->serializeOpening($opening, true, true));
    }

    private function academyAssignOpening(int $openingId): void
    {
        $session = $this->auth->requireAcademy();
        $academyId = (int) $session['entity']['id'];
        $this->academyOpeningOrFail($openingId, $academyId);
        $body = Http::body();
        $studentIds = $body['studentIds'] ?? [];
        if (!is_array($studentIds)) {
            Http::error('studentIds must be a list', 400);
        }
        $replace = !empty($body['replace']);
        if ($replace) {
            $this->db->run('DELETE FROM opening_assignments WHERE opening_id = ?', [$openingId]);
        }
        $assigned = [];
        foreach ($studentIds as $rawId) {
            $studentId = (int) $rawId;
            if ($studentId < 1 || !$this->academyHasStudent($academyId, $studentId)) {
                continue;
            }
            $this->db->run(
                'INSERT OR IGNORE INTO opening_assignments (opening_id, student_id, academy_id) VALUES (?, ?, ?)',
                [$openingId, $studentId, $academyId]
            );
            $assigned[] = $studentId;
        }
        if (isset($body['removeStudentIds']) && is_array($body['removeStudentIds'])) {
            foreach ($body['removeStudentIds'] as $rawId) {
                $studentId = (int) $rawId;
                $this->db->run(
                    'DELETE FROM opening_assignments WHERE opening_id = ? AND student_id = ? AND academy_id = ?',
                    [$openingId, $studentId, $academyId]
                );
            }
        }
        $students = $this->db->all(
            'SELECT s.id, s.name, s.username
             FROM opening_assignments a
             JOIN students s ON s.id = a.student_id
             WHERE a.opening_id = ?
             ORDER BY s.name',
            [$openingId]
        );
        Http::json(['assignedStudents' => $students, 'added' => $assigned]);
    }

    private function academyOpeningProgress(int $openingId): void
    {
        $session = $this->auth->requireAcademy();
        $this->academyOpeningOrFail($openingId, (int) $session['entity']['id']);
        $rows = $this->db->all(
            'SELECT s.id as student_id, s.name, s.username, a.assigned_at
             FROM opening_assignments a
             JOIN students s ON s.id = a.student_id
             WHERE a.opening_id = ?
             ORDER BY s.name',
            [$openingId]
        );
        $progress = [];
        foreach ($rows as $row) {
            $stats = $this->studentOpeningStats((int) $row['student_id'], $openingId);
            $progress[] = array_merge($row, $stats);
        }
        Http::json(['progress' => $progress]);
    }

    private function studentOpenings(): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];
        $rows = $this->db->all(
            "SELECT o.*
             FROM openings o
             JOIN opening_assignments a ON a.opening_id = o.id
             WHERE a.student_id = ?
             ORDER BY CASE o.color_group WHEN 'white' THEN 0 ELSE 1 END, o.name",
            [$studentId]
        );
        $openings = [];
        foreach ($rows as $row) {
            $openings[] = array_merge(
                $this->serializeOpening($row, false, false),
                $this->studentOpeningStats($studentId, (int) $row['id'])
            );
        }
        Http::json(['openings' => $openings]);
    }

    private function studentGetOpening(int $openingId): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];
        $opening = $this->studentAssignedOpening($studentId, $openingId);
        if (!$opening) {
            Http::error('This opening is not assigned to you', 403);
        }
        $chapters = [];
        foreach ($this->openingChapters($openingId) as $chapter) {
            $latest = $this->db->get(
                'SELECT * FROM opening_tests
                 WHERE student_id = ? AND chapter_id = ?
                 ORDER BY COALESCE(completed_at, started_at) DESC, id DESC
                 LIMIT 1',
                [$studentId, $chapter['id']]
            );
            $completedCount = $this->db->get(
                'SELECT COUNT(*) as c FROM opening_tests
                 WHERE student_id = ? AND chapter_id = ? AND completed_at IS NOT NULL',
                [$studentId, $chapter['id']]
            );
            $chapters[] = array_merge($this->openings->publicChapter($chapter, true), [
                'testTaken' => (int) ($completedCount['c'] ?? 0) > 0,
                'testsTaken' => (int) ($completedCount['c'] ?? 0),
                'lastPassed' => $latest ? (int) ($latest['passed'] ?? 0) === 1 && !empty($latest['completed_at']) : false,
                'lastWrongMoves' => $latest ? (int) ($latest['wrong_moves'] ?? 0) : 0,
                'lastCompletedAt' => $latest['completed_at'] ?? null,
            ]);
        }
        Http::json(array_merge($this->serializeOpening($opening, false, false), [
            'chapters' => $chapters,
        ], $this->studentOpeningStats($studentId, $openingId)));
    }

    private function studentOpeningTests(): void
    {
        $session = $this->auth->requireStudent();
        $rows = $this->db->all(
            'SELECT t.*, o.name as opening_name, o.color_group, c.title as chapter_title
             FROM opening_tests t
             JOIN openings o ON o.id = t.opening_id
             JOIN opening_chapters c ON c.id = t.chapter_id
             WHERE t.student_id = ?
             ORDER BY t.started_at DESC',
            [(int) $session['entity']['id']]
        );
        Http::json(['tests' => $rows]);
    }

    private function openingTestPayload(array $test, array $opening, array $chapter): array
    {
        $playerColor = $this->openings->playerColor((string) $opening['color_group']);
        $index = (int) $test['current_move_index'];
        $fen = $this->openings->fenAt($chapter, $index);
        $completed = !empty($test['completed_at']);
        return [
            'test' => [
                'id' => (int) $test['id'],
                'openingId' => (int) $test['opening_id'],
                'chapterId' => (int) $test['chapter_id'],
                'currentMoveIndex' => $index,
                'wrongMoves' => (int) $test['wrong_moves'],
                'correctMoves' => (int) $test['correct_moves'],
                'totalPlayerMoves' => (int) $test['total_player_moves'],
                'completed' => $completed,
                'passed' => (int) ($test['passed'] ?? 0) === 1,
                'startedAt' => $test['started_at'] ?? null,
                'completedAt' => $test['completed_at'] ?? null,
            ],
            'opening' => [
                'id' => (int) $opening['id'],
                'name' => $opening['name'],
                'colorGroup' => $opening['color_group'],
            ],
            'chapter' => [
                'id' => (int) $chapter['id'],
                'title' => $chapter['title'],
                'plyCount' => count($this->openings->chapterMoves($chapter)),
            ],
            'playerColor' => $playerColor,
            'fen' => $fen,
            'lastMove' => $this->openings->lastMoveAt($chapter, $index),
            'legalMoves' => $completed ? [] : $this->openings->legalMoves($fen),
            'turn' => explode(' ', $fen)[1] ?? 'w',
        ];
    }

    private function startOpeningTest(): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];
        $body = Http::body();
        $chapterId = (int) ($body['chapterId'] ?? 0);
        $chapter = $this->db->get('SELECT * FROM opening_chapters WHERE id = ?', [$chapterId]);
        if (!$chapter) {
            Http::error('Chapter not found', 404);
        }
        $opening = $this->studentAssignedOpening($studentId, (int) $chapter['opening_id']);
        if (!$opening) {
            Http::error('This opening is not assigned to you', 403);
        }

        $existing = $this->db->get(
            'SELECT * FROM opening_tests
             WHERE student_id = ? AND chapter_id = ? AND completed_at IS NULL
             ORDER BY id DESC LIMIT 1',
            [$studentId, $chapterId]
        );
        if ($existing) {
            Http::json($this->openingTestPayload($existing, $opening, $chapter));
            return;
        }

        $playerColor = $this->openings->playerColor((string) $opening['color_group']);
        $skipped = $this->openings->skipOpponentMoves($chapter, $playerColor, 0);
        $totalPlayer = $this->openings->countPlayerMoves($this->openings->chapterMoves($chapter), $playerColor);
        $id = $this->db->run(
            'INSERT INTO opening_tests
                (student_id, opening_id, chapter_id, current_move_index, total_player_moves)
             VALUES (?, ?, ?, ?, ?)',
            [$studentId, (int) $opening['id'], $chapterId, (int) $skipped['index'], $totalPlayer]
        );
        if (!empty($skipped['completed'])) {
            $this->completeOpeningTest($id);
        }
        $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$id]);
        Http::json($this->openingTestPayload($test, $opening, $chapter), 201);
    }

    private function getOpeningTest(int $testId): void
    {
        $session = $this->auth->requireStudent();
        $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$testId]);
        if (!$test || (int) $test['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Test not found', 404);
        }
        $opening = $this->db->get('SELECT * FROM openings WHERE id = ?', [$test['opening_id']]);
        $chapter = $this->db->get('SELECT * FROM opening_chapters WHERE id = ?', [$test['chapter_id']]);
        Http::json($this->openingTestPayload($test, $opening, $chapter));
    }

    private function playOpeningTest(int $testId): void
    {
        $session = $this->auth->requireStudent();
        $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$testId]);
        if (!$test || (int) $test['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Test not found', 404);
        }
        if (!empty($test['completed_at'])) {
            $opening = $this->db->get('SELECT * FROM openings WHERE id = ?', [$test['opening_id']]);
            $chapter = $this->db->get('SELECT * FROM opening_chapters WHERE id = ?', [$test['chapter_id']]);
            Http::json(array_merge($this->openingTestPayload($test, $opening, $chapter), [
                'correct' => true,
                'completed' => true,
            ]));
            return;
        }

        $opening = $this->db->get('SELECT * FROM openings WHERE id = ?', [$test['opening_id']]);
        $chapter = $this->db->get('SELECT * FROM opening_chapters WHERE id = ?', [$test['chapter_id']]);
        $moves = $this->openings->chapterMoves($chapter);
        $playerColor = $this->openings->playerColor((string) $opening['color_group']);
        $index = (int) $test['current_move_index'];
        $skipped = $this->openings->skipOpponentMoves($chapter, $playerColor, $index);
        $index = (int) $skipped['index'];
        if ($index !== (int) $test['current_move_index']) {
            $this->db->run('UPDATE opening_tests SET current_move_index = ? WHERE id = ?', [$index, $testId]);
            $test['current_move_index'] = $index;
        }

        if ($index >= count($moves)) {
            $this->completeOpeningTest($testId);
            $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$testId]);
            Http::json(array_merge($this->openingTestPayload($test, $opening, $chapter), [
                'correct' => true,
                'completed' => true,
            ]));
            return;
        }

        $expected = $moves[$index];
        if (($expected['color'] ?? '') !== $playerColor) {
            Http::json(array_merge($this->openingTestPayload($test, $opening, $chapter), [
                'correct' => false,
                'opponentTurn' => true,
            ]));
            return;
        }

        $body = Http::body();
        $from = strtolower((string) ($body['from'] ?? ''));
        $to = strtolower((string) ($body['to'] ?? ''));
        $promotion = strtolower((string) ($body['promotion'] ?? 'q'));
        $fen = $this->openings->fenAt($chapter, $index);
        $chess = new Chess();
        $chess->load($fen);
        $played = $chess->move(['from' => $from, 'to' => $to, 'promotion' => $promotion]);
        if (!$played) {
            Http::json(array_merge($this->openingTestPayload($test, $opening, $chapter), [
                'correct' => false,
                'illegal' => true,
            ]));
            return;
        }

        $expectedFrom = strtolower((string) ($expected['from'] ?? ''));
        $expectedTo = strtolower((string) ($expected['to'] ?? ''));
        $matched = $from === $expectedFrom && $to === $expectedTo;
        if (!$matched) {
            $this->db->run('UPDATE opening_tests SET wrong_moves = wrong_moves + 1 WHERE id = ?', [$testId]);
            $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$testId]);
            Http::json(array_merge($this->openingTestPayload($test, $opening, $chapter), [
                'correct' => false,
                'message' => 'Wrong move. Try again.',
            ]));
            return;
        }

        $next = $index + 1;
        $after = $this->openings->skipOpponentMoves($chapter, $playerColor, $next);
        $completed = !empty($after['completed']);
        $this->db->run(
            'UPDATE opening_tests SET current_move_index = ?, correct_moves = correct_moves + 1 WHERE id = ?',
            [(int) $after['index'], $testId]
        );
        if ($completed) {
            $this->completeOpeningTest($testId);
        }
        $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$testId]);
        Http::json(array_merge($this->openingTestPayload($test, $opening, $chapter), [
            'correct' => true,
            'completed' => $completed,
            'autoMove' => $after['lastMove'],
        ]));
    }

    private function completeOpeningTest(int $testId): void
    {
        $test = $this->db->get('SELECT * FROM opening_tests WHERE id = ?', [$testId]);
        if (!$test || !empty($test['completed_at'])) {
            return;
        }
        $passed = (int) $test['wrong_moves'] === 0 ? 1 : 0;
        $this->db->run(
            "UPDATE opening_tests
             SET completed_at = datetime('now'),
                 passed = ?,
                 time_ms = CAST((julianday('now') - julianday(started_at)) * 86400000 AS INTEGER)
             WHERE id = ?",
            [$passed, $testId]
        );
    }

    private function academyEndgameCategoryOrFail(int $id, int $academyId): array
    {
        $row = $this->db->get(
            'SELECT * FROM endgame_categories WHERE id = ? AND academy_id = ?',
            [$id, $academyId]
        );
        if (!$row) {
            Http::error('Endgame category not found', 404);
        }
        return $row;
    }

    private function serializeEndgameCategory(array $category, bool $deep): array
    {
        $id = (int) $category['id'];
        $subs = $this->db->all(
            'SELECT * FROM endgame_subcategories WHERE category_id = ? ORDER BY sort_order, id',
            [$id]
        );
        $chapterCount = 0;
        $payloadSubs = [];
        foreach ($subs as $sub) {
            $chapters = $this->db->all(
                'SELECT * FROM endgame_chapters WHERE subcategory_id = ? ORDER BY sort_order, id',
                [$sub['id']]
            );
            $chapterCount += count($chapters);
            $item = [
                'id' => (int) $sub['id'],
                'name' => $sub['name'],
                'chapterCount' => count($chapters),
            ];
            if ($deep) {
                $item['chapters'] = array_map(fn (array $c) => $this->serializeEndgameChapter($c), $chapters);
            }
            $payloadSubs[] = $item;
        }
        $assigned = $this->db->get(
            'SELECT COUNT(*) as c FROM endgame_assignments WHERE category_id = ?',
            [$id]
        );
        return [
            'id' => $id,
            'name' => $category['name'],
            'notes' => $category['notes'] ?? '',
            'subcategoryCount' => count($subs),
            'chapterCount' => $chapterCount,
            'assignedCount' => (int) ($assigned['c'] ?? 0),
            'subcategories' => $payloadSubs,
        ];
    }

    private function serializeEndgameChapter(array $chapter): array
    {
        return [
            'id' => (int) $chapter['id'],
            'subcategoryId' => (int) $chapter['subcategory_id'],
            'title' => $chapter['title'],
            'fen' => $chapter['fen'],
            'goal' => $chapter['goal'],
            'goalLabel' => $this->endgames->goalLabel((string) $chapter['goal']),
            'notes' => $chapter['notes'] ?? '',
            'playerColor' => $this->endgames->playerColor((string) $chapter['goal'], (string) $chapter['fen']),
        ];
    }

    private function studentEndgameStats(int $studentId, int $categoryId): array
    {
        $total = $this->db->get(
            'SELECT COUNT(*) as c FROM endgame_chapters ch
             JOIN endgame_subcategories s ON s.id = ch.subcategory_id
             WHERE s.category_id = ?',
            [$categoryId]
        );
        $passed = $this->db->get(
            'SELECT COUNT(DISTINCT chapter_id) as c FROM endgame_attempts
             WHERE student_id = ? AND category_id = ? AND mode = \'test\' AND passed = 1 AND completed_at IS NOT NULL',
            [$studentId, $categoryId]
        );
        $tested = $this->db->get(
            'SELECT COUNT(DISTINCT chapter_id) as c FROM endgame_attempts
             WHERE student_id = ? AND category_id = ? AND mode = \'test\' AND completed_at IS NOT NULL',
            [$studentId, $categoryId]
        );
        return [
            'chapterCount' => (int) ($total['c'] ?? 0),
            'chaptersPassed' => (int) ($passed['c'] ?? 0),
            'chaptersTested' => (int) ($tested['c'] ?? 0),
            'testTaken' => (int) ($tested['c'] ?? 0) > 0,
        ];
    }

    private function chapterPassed(int $studentId, int $chapterId): bool
    {
        $row = $this->db->get(
            'SELECT 1 FROM endgame_attempts
             WHERE student_id = ? AND chapter_id = ? AND mode = \'test\' AND passed = 1 AND completed_at IS NOT NULL
             LIMIT 1',
            [$studentId, $chapterId]
        );
        return $row !== null;
    }

    private function academyEndgames(): void
    {
        $session = $this->auth->requireAcademy();
        $rows = $this->db->all(
            'SELECT * FROM endgame_categories WHERE academy_id = ? ORDER BY sort_order, name',
            [(int) $session['entity']['id']]
        );
        Http::json([
            'categories' => array_map(fn (array $r) => $this->serializeEndgameCategory($r, true), $rows),
            'goals' => array_map(
                fn (string $g) => ['id' => $g, 'label' => $this->endgames->goalLabel($g)],
                EndgameService::GOALS
            ),
            'levels' => $this->endgames->levels(),
        ]);
    }

    private function academyCreateEndgameCategory(): void
    {
        $session = $this->auth->requireAcademy();
        $name = trim((string) (Http::body()['name'] ?? ''));
        if ($name === '') {
            Http::error('Category name is required', 400);
        }
        $id = $this->db->run(
            'INSERT INTO endgame_categories (academy_id, name, notes) VALUES (?, ?, ?)',
            [(int) $session['entity']['id'], $name, trim((string) (Http::body()['notes'] ?? ''))]
        );
        Http::json($this->serializeEndgameCategory(
            $this->db->get('SELECT * FROM endgame_categories WHERE id = ?', [$id]),
            true
        ), 201);
    }

    private function academyGetEndgameCategory(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $category = $this->academyEndgameCategoryOrFail($id, (int) $session['entity']['id']);
        $assigned = $this->db->all(
            'SELECT s.id, s.name, s.username FROM endgame_assignments a
             JOIN students s ON s.id = a.student_id WHERE a.category_id = ? ORDER BY s.name',
            [$id]
        );
        Http::json(array_merge($this->serializeEndgameCategory($category, true), [
            'assignedStudents' => $assigned,
        ]));
    }

    private function academyPatchEndgameCategory(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $category = $this->academyEndgameCategoryOrFail($id, (int) $session['entity']['id']);
        $body = Http::body();
        $name = array_key_exists('name', $body) ? trim((string) $body['name']) : (string) $category['name'];
        $notes = array_key_exists('notes', $body) ? trim((string) $body['notes']) : (string) ($category['notes'] ?? '');
        if ($name === '') {
            Http::error('Category name is required', 400);
        }
        $this->db->run('UPDATE endgame_categories SET name = ?, notes = ? WHERE id = ?', [$name, $notes, $id]);
        Http::json($this->serializeEndgameCategory($this->db->get('SELECT * FROM endgame_categories WHERE id = ?', [$id]), true));
    }

    private function academyDeleteEndgameCategory(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $this->academyEndgameCategoryOrFail($id, (int) $session['entity']['id']);
        $subs = $this->db->all('SELECT id FROM endgame_subcategories WHERE category_id = ?', [$id]);
        foreach ($subs as $sub) {
            $this->db->run('DELETE FROM endgame_chapters WHERE subcategory_id = ?', [$sub['id']]);
        }
        $this->db->run('DELETE FROM endgame_attempts WHERE category_id = ?', [$id]);
        $this->db->run('DELETE FROM endgame_assignments WHERE category_id = ?', [$id]);
        $this->db->run('DELETE FROM endgame_subcategories WHERE category_id = ?', [$id]);
        $this->db->run('DELETE FROM endgame_categories WHERE id = ?', [$id]);
        Http::json(['ok' => true]);
    }

    private function academyAddEndgameSubcategory(int $categoryId): void
    {
        $session = $this->auth->requireAcademy();
        $this->academyEndgameCategoryOrFail($categoryId, (int) $session['entity']['id']);
        $name = trim((string) (Http::body()['name'] ?? ''));
        if ($name === '') {
            Http::error('Subcategory name is required', 400);
        }
        $count = $this->db->get('SELECT COUNT(*) as c FROM endgame_subcategories WHERE category_id = ?', [$categoryId]);
        $id = $this->db->run(
            'INSERT INTO endgame_subcategories (category_id, name, sort_order) VALUES (?, ?, ?)',
            [$categoryId, $name, (int) ($count['c'] ?? 0)]
        );
        Http::json(['id' => $id, 'name' => $name, 'chapterCount' => 0, 'chapters' => []], 201);
    }

    private function academyPatchEndgameSubcategory(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $sub = $this->db->get(
            'SELECT s.*, c.academy_id FROM endgame_subcategories s
             JOIN endgame_categories c ON c.id = s.category_id WHERE s.id = ?',
            [$id]
        );
        if (!$sub || (int) $sub['academy_id'] !== (int) $session['entity']['id']) {
            Http::error('Subcategory not found', 404);
        }
        $name = trim((string) (Http::body()['name'] ?? $sub['name']));
        if ($name === '') {
            Http::error('Subcategory name is required', 400);
        }
        $this->db->run('UPDATE endgame_subcategories SET name = ? WHERE id = ?', [$name, $id]);
        Http::json(['id' => $id, 'name' => $name]);
    }

    private function academyDeleteEndgameSubcategory(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $sub = $this->db->get(
            'SELECT s.*, c.academy_id FROM endgame_subcategories s
             JOIN endgame_categories c ON c.id = s.category_id WHERE s.id = ?',
            [$id]
        );
        if (!$sub || (int) $sub['academy_id'] !== (int) $session['entity']['id']) {
            Http::error('Subcategory not found', 404);
        }
        $this->db->run(
            'DELETE FROM endgame_attempts WHERE chapter_id IN (SELECT id FROM endgame_chapters WHERE subcategory_id = ?)',
            [$id]
        );
        $this->db->run('DELETE FROM endgame_chapters WHERE subcategory_id = ?', [$id]);
        $this->db->run('DELETE FROM endgame_subcategories WHERE id = ?', [$id]);
        Http::json(['ok' => true]);
    }

    private function academyAnalyzeEndgameFen(): void
    {
        $this->auth->requireAcademy();
        try {
            Http::json($this->endgames->analyzeFen((string) (Http::body()['fen'] ?? '')));
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
    }

    private function academyAddEndgameChapter(int $subId): void
    {
        $session = $this->auth->requireAcademy();
        $sub = $this->db->get(
            'SELECT s.*, c.academy_id FROM endgame_subcategories s
             JOIN endgame_categories c ON c.id = s.category_id WHERE s.id = ?',
            [$subId]
        );
        if (!$sub || (int) $sub['academy_id'] !== (int) $session['entity']['id']) {
            Http::error('Subcategory not found', 404);
        }
        $body = Http::body();
        $title = trim((string) ($body['title'] ?? ''));
        try {
            $goal = $this->endgames->normalizeGoal((string) ($body['goal'] ?? ''));
            $this->endgames->analyzeFen((string) ($body['fen'] ?? ''));
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        $count = $this->db->get('SELECT COUNT(*) as c FROM endgame_chapters WHERE subcategory_id = ?', [$subId]);
        if ($title === '') {
            $title = 'Position ' . ((int) ($count['c'] ?? 0) + 1);
        }
        $id = $this->db->run(
            'INSERT INTO endgame_chapters (subcategory_id, title, fen, goal, notes, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
            [$subId, $title, trim((string) $body['fen']), $goal, trim((string) ($body['notes'] ?? '')), (int) ($count['c'] ?? 0)]
        );
        Http::json($this->serializeEndgameChapter($this->db->get('SELECT * FROM endgame_chapters WHERE id = ?', [$id])), 201);
    }

    private function academyPatchEndgameChapter(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $chapter = $this->db->get(
            'SELECT ch.*, c.academy_id FROM endgame_chapters ch
             JOIN endgame_subcategories s ON s.id = ch.subcategory_id
             JOIN endgame_categories c ON c.id = s.category_id WHERE ch.id = ?',
            [$id]
        );
        if (!$chapter || (int) $chapter['academy_id'] !== (int) $session['entity']['id']) {
            Http::error('Chapter not found', 404);
        }
        $body = Http::body();
        $title = array_key_exists('title', $body) ? trim((string) $body['title']) : (string) $chapter['title'];
        $fen = array_key_exists('fen', $body) ? trim((string) $body['fen']) : (string) $chapter['fen'];
        $notes = array_key_exists('notes', $body) ? trim((string) $body['notes']) : (string) ($chapter['notes'] ?? '');
        try {
            $goal = array_key_exists('goal', $body)
                ? $this->endgames->normalizeGoal((string) $body['goal'])
                : (string) $chapter['goal'];
            $this->endgames->analyzeFen($fen);
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        if ($title === '') {
            Http::error('Chapter name is required', 400);
        }
        $this->db->run(
            'UPDATE endgame_chapters SET title = ?, fen = ?, goal = ?, notes = ? WHERE id = ?',
            [$title, $fen, $goal, $notes, $id]
        );
        Http::json($this->serializeEndgameChapter($this->db->get('SELECT * FROM endgame_chapters WHERE id = ?', [$id])));
    }

    private function academyDeleteEndgameChapter(int $id): void
    {
        $session = $this->auth->requireAcademy();
        $chapter = $this->db->get(
            'SELECT ch.*, c.academy_id FROM endgame_chapters ch
             JOIN endgame_subcategories s ON s.id = ch.subcategory_id
             JOIN endgame_categories c ON c.id = s.category_id WHERE ch.id = ?',
            [$id]
        );
        if (!$chapter || (int) $chapter['academy_id'] !== (int) $session['entity']['id']) {
            Http::error('Chapter not found', 404);
        }
        $this->db->run('DELETE FROM endgame_attempts WHERE chapter_id = ?', [$id]);
        $this->db->run('DELETE FROM endgame_chapters WHERE id = ?', [$id]);
        Http::json(['ok' => true]);
    }

    private function academyAssignEndgame(int $categoryId): void
    {
        $session = $this->auth->requireAcademy();
        $academyId = (int) $session['entity']['id'];
        $this->academyEndgameCategoryOrFail($categoryId, $academyId);
        $body = Http::body();
        $studentIds = is_array($body['studentIds'] ?? null) ? $body['studentIds'] : [];
        if (!empty($body['replace'])) {
            $this->db->run('DELETE FROM endgame_assignments WHERE category_id = ?', [$categoryId]);
        }
        foreach ($studentIds as $rawId) {
            $studentId = (int) $rawId;
            if ($studentId < 1 || !$this->academyHasStudent($academyId, $studentId)) {
                continue;
            }
            $this->db->run(
                'INSERT OR IGNORE INTO endgame_assignments (category_id, student_id, academy_id) VALUES (?, ?, ?)',
                [$categoryId, $studentId, $academyId]
            );
        }
        $students = $this->db->all(
            'SELECT s.id, s.name, s.username FROM endgame_assignments a
             JOIN students s ON s.id = a.student_id WHERE a.category_id = ? ORDER BY s.name',
            [$categoryId]
        );
        Http::json(['assignedStudents' => $students]);
    }

    private function academyEndgameProgress(int $categoryId): void
    {
        $session = $this->auth->requireAcademy();
        $this->academyEndgameCategoryOrFail($categoryId, (int) $session['entity']['id']);
        $rows = $this->db->all(
            'SELECT s.id as student_id, s.name, s.username FROM endgame_assignments a
             JOIN students s ON s.id = a.student_id WHERE a.category_id = ? ORDER BY s.name',
            [$categoryId]
        );
        $progress = [];
        foreach ($rows as $row) {
            $stats = $this->studentEndgameStats((int) $row['student_id'], $categoryId);
            $progress[] = array_merge($row, $stats, [
                'green' => $stats['chaptersPassed'],
            ]);
        }
        Http::json(['progress' => $progress]);
    }

    private function studentEndgames(): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];
        $rows = $this->db->all(
            'SELECT c.* FROM endgame_categories c
             JOIN endgame_assignments a ON a.category_id = c.id
             WHERE a.student_id = ? ORDER BY c.name',
            [$studentId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = array_merge(
                $this->serializeEndgameCategory($row, false),
                $this->studentEndgameStats($studentId, (int) $row['id'])
            );
        }
        Http::json(['categories' => $out, 'levels' => $this->endgames->levels()]);
    }

    private function studentGetEndgame(int $categoryId): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];
        $assigned = $this->db->get(
            'SELECT 1 FROM endgame_assignments WHERE category_id = ? AND student_id = ?',
            [$categoryId, $studentId]
        );
        if (!$assigned) {
            Http::error('This endgame is not assigned to you', 403);
        }
        $category = $this->db->get('SELECT * FROM endgame_categories WHERE id = ?', [$categoryId]);
        $payload = $this->serializeEndgameCategory($category, true);
        foreach ($payload['subcategories'] as &$sub) {
            foreach ($sub['chapters'] as &$chapter) {
                $chapter['passed'] = $this->chapterPassed($studentId, (int) $chapter['id']);
                $latest = $this->db->get(
                    'SELECT * FROM endgame_attempts WHERE student_id = ? AND chapter_id = ? AND completed_at IS NOT NULL
                     ORDER BY completed_at DESC LIMIT 1',
                    [$studentId, $chapter['id']]
                );
                $chapter['lastResult'] = $latest['result'] ?? null;
                $chapter['practiceTaken'] = $this->db->get(
                    'SELECT 1 FROM endgame_attempts WHERE student_id = ? AND chapter_id = ? AND mode = \'practice\' AND passed = 1 AND completed_at IS NOT NULL LIMIT 1',
                    [$studentId, $chapter['id']]
                ) !== null;
                $chapter['testTaken'] = $this->db->get(
                    'SELECT 1 FROM endgame_attempts WHERE student_id = ? AND chapter_id = ? AND mode = \'test\' AND completed_at IS NOT NULL LIMIT 1',
                    [$studentId, $chapter['id']]
                ) !== null;
            }
        }
        Http::json(array_merge($payload, $this->studentEndgameStats($studentId, $categoryId)));
    }

    private function endgameLevels(): void
    {
        $this->auth->requireStudent();
        Http::json(['levels' => $this->endgames->levels()]);
    }

    private function endgameMove(): void
    {
        $this->auth->requireStudent();
        $body = Http::body();
        try {
            $pos = $this->endgames->applyMove(
                (string) ($body['fen'] ?? ''),
                (string) ($body['from'] ?? ''),
                (string) ($body['to'] ?? ''),
                (string) ($body['promotion'] ?? 'q')
            );
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        $goal = (string) ($body['goal'] ?? '');
        $player = (string) ($body['playerColor'] ?? 'w');
        $judge = $goal !== '' ? $this->endgames->judge($goal, $pos, $player) : ['over' => false];
        Http::json(array_merge($pos, ['judge' => $judge]));
    }

    private function endgameEngineMove(): void
    {
        $this->auth->requireStudent();
        $body = Http::body();
        try {
            $pos = $this->endgames->engineMove((string) ($body['fen'] ?? ''), (string) ($body['level'] ?? 'intermediate'));
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        $goal = (string) ($body['goal'] ?? '');
        $player = (string) ($body['playerColor'] ?? 'w');
        $judge = $goal !== '' ? $this->endgames->judge($goal, $pos, $player) : ['over' => false];
        Http::json(array_merge($pos, ['judge' => $judge]));
    }

    private function startEndgameAttempt(): void
    {
        $session = $this->auth->requireStudent();
        $studentId = (int) $session['entity']['id'];
        $body = Http::body();
        $chapterId = (int) ($body['chapterId'] ?? 0);
        $mode = ($body['mode'] ?? '') === 'test' ? 'test' : 'practice';
        $level = (string) ($body['level'] ?? 'intermediate');
        if (!isset(EndgameService::LEVELS[$level])) {
            $level = 'intermediate';
        }
        $chapter = $this->db->get(
            'SELECT ch.*, s.category_id FROM endgame_chapters ch
             JOIN endgame_subcategories s ON s.id = ch.subcategory_id WHERE ch.id = ?',
            [$chapterId]
        );
        if (!$chapter) {
            Http::error('Position not found', 404);
        }
        $assigned = $this->db->get(
            'SELECT 1 FROM endgame_assignments WHERE category_id = ? AND student_id = ?',
            [$chapter['category_id'], $studentId]
        );
        if (!$assigned) {
            Http::error('This endgame is not assigned to you', 403);
        }
        try {
            $pos = $this->endgames->analyzeFen((string) $chapter['fen']);
        } catch (InvalidArgumentException $e) {
            Http::error($e->getMessage(), 400);
        }
        $player = $this->endgames->playerColor((string) $chapter['goal'], (string) $chapter['fen']);
        $id = $this->db->run(
            'INSERT INTO endgame_attempts (student_id, category_id, chapter_id, mode, engine_level) VALUES (?, ?, ?, ?, ?)',
            [$studentId, (int) $chapter['category_id'], $chapterId, $mode, $level]
        );
        Http::json([
            'attempt' => ['id' => $id, 'mode' => $mode, 'engineLevel' => $level],
            'chapter' => $this->serializeEndgameChapter($chapter),
            'position' => $pos,
            'playerColor' => $player,
            'levels' => $this->endgames->levels(),
        ], 201);
    }

    private function getEndgameAttempt(int $id): void
    {
        $session = $this->auth->requireStudent();
        $attempt = $this->db->get('SELECT * FROM endgame_attempts WHERE id = ?', [$id]);
        if (!$attempt || (int) $attempt['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Attempt not found', 404);
        }
        Http::json(['attempt' => $attempt]);
    }

    private function finishEndgameAttempt(int $id): void
    {
        $session = $this->auth->requireStudent();
        $attempt = $this->db->get('SELECT * FROM endgame_attempts WHERE id = ?', [$id]);
        if (!$attempt || (int) $attempt['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Attempt not found', 404);
        }
        $body = Http::body();
        $passed = !empty($body['passed']) ? 1 : 0;
        $failed = (int) ($body['failedRestarts'] ?? $attempt['failed_restarts']);
        $result = (string) ($body['result'] ?? '*');
        if ($attempt['mode'] === 'test') {
            $passed = $passed === 1 && $failed === 0 ? 1 : 0;
        }
        $this->db->run(
            "UPDATE endgame_attempts
             SET completed_at = datetime('now'), passed = ?, failed_restarts = ?, result = ?
             WHERE id = ?",
            [$passed, $failed, $result, $id]
        );
        Http::json([
            'attempt' => $this->db->get('SELECT * FROM endgame_attempts WHERE id = ?', [$id]),
            'green' => $passed === 1 && $attempt['mode'] === 'test',
        ]);
    }
}
