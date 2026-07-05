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
        private array $config
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
            'GET /auth/me' => fn () => $this->authMe(),
            'POST /academy/register' => fn () => $this->academyRegister(),
            'POST /academy/login' => fn () => $this->academyLogin(),
            'GET /academy/me' => fn () => $this->academyMe(),
            'GET /academy/students' => fn () => $this->academyStudents(),
            'POST /academy/students' => fn () => $this->academyAddStudent(),
            'GET /academy/coaches' => fn () => $this->academyCoaches(),
            'POST /academy/coaches' => fn () => $this->academyAddCoach(),
            'GET /puzzles' => fn () => $this->getPuzzles(),
            'POST /cycles/start' => fn () => $this->startCycle(),
            'GET /me/cycles/active' => fn () => $this->activeCycles(),
            'GET /me/results' => fn () => $this->myResults(),
            'POST /attempts/start' => fn () => $this->startAttempt(),
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

        Http::error('Not found', 404);
    }

    private function sectionPuzzleCount(string $section): int
    {
        return match ($section) {
            'Easy' => 222,
            'Intermediate' => 762,
            'Advanced' => 144,
            default => 0,
        };
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

    /**
     * Resume the cycle timer. Called when player enters a puzzle screen.
     * If already running, just updates last_activity_at.
     */
    private function tickCycleTimer(int $cycleId): void
    {
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle) {
            return;
        }

        $resumedAt = $cycle['cycle_resumed_at'] ?? null;

        if (!$resumedAt) {
            // Timer was paused — start a new session
            $this->db->run(
                "UPDATE cycles SET cycle_resumed_at = datetime('now'), last_activity_at = datetime('now') WHERE id = ?",
                [$cycleId]
            );
        } else {
            // Timer already running — update last activity
            $this->db->run(
                "UPDATE cycles SET last_activity_at = datetime('now') WHERE id = ?",
                [$cycleId]
            );
        }
    }

    /**
     * Pause the cycle timer. Called when player leaves puzzle screen (clicks back).
     * Saves the active session time into cycle_accumulated_ms.
     */
    private function pauseCycleTimer(int $cycleId): void
    {
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle) {
            return;
        }

        $resumedAt = $cycle['cycle_resumed_at'] ?? null;
        if (!$resumedAt) {
            return; // Already paused
        }

        $resumeTs = strtotime($resumedAt);
        $nowTs = time();
        $accumulated = (int) ($cycle['cycle_accumulated_ms'] ?? 0);

        if ($resumeTs && $nowTs > $resumeTs) {
            $accumulated += ($nowTs - $resumeTs) * 1000;
        }

        // Save accumulated and clear resumed_at (= paused state)
        $this->db->run(
            "UPDATE cycles SET cycle_accumulated_ms = ?, cycle_resumed_at = NULL, last_activity_at = NULL WHERE id = ?",
            [$accumulated, $cycleId]
        );
    }

    /**
     * Get the current cycle time in milliseconds.
     * If running: accumulated + current session. If paused: just accumulated.
     */
    private function getCycleTimeMs(array $cycle): int
    {
        $accumulated = (int) ($cycle['cycle_accumulated_ms'] ?? 0);
        $resumedAt = $cycle['cycle_resumed_at'] ?? null;

        if (!$resumedAt) {
            return $accumulated;
        }

        $resumeTs = strtotime($resumedAt);
        $nowTs = time();

        if ($resumeTs && $nowTs > $resumeTs) {
            return $accumulated + ($nowTs - $resumeTs) * 1000;
        }

        return $accumulated;
    }

    private function registerStudentAccount(array $body): array
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
                'INSERT INTO students (name, username, password_hash) VALUES (?, ?, ?)',
                [$name, $clean, hashPassword($password)]
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
        foreach ($cycles as $c) {
            $totalTime += (int) ($c['cycle_accumulated_ms'] ?? 0);
        }
        foreach ($allAttempts as $a) {
            $totalWrong += (int) ($a['wrong_moves'] ?? 0);
        }

        return [
            'student' => $student,
            'cycles' => $cycles,
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

        $attempts = $this->db->all(
            "SELECT * FROM puzzle_attempts WHERE cycle_id = ? AND completed = 1 ORDER BY completed_at ASC",
            [$cycleId]
        );

        $results = array_map(static fn (array $a) => [
            'id' => $a['id'],
            'puzzleId' => $a['puzzle_id'],
            'name' => $a['puzzle_section'] . ' Exercise ' . $a['puzzle_number'],
            'section' => $a['puzzle_section'],
            'number' => $a['puzzle_number'],
            'timeMs' => 0,
            'completedAt' => $a['completed_at'],
            'wrongMoves' => (int) ($a['wrong_moves'] ?? 0),
            'solutionRevealed' => !empty($a['solution_revealed']),
        ], $attempts);

        return [
            'cycle' => $cycle,
            'results' => $results,
            'puzzlesCompleted' => count($results),
            'cycle_time_ms' => $this->getCycleTimeMs($cycle),
        ];
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
              (SELECT COUNT(*) FROM cycles c WHERE c.student_id = s.id AND c.completed_at IS NOT NULL) as completed_cycles,
              (SELECT COUNT(*) FROM puzzle_attempts pa
               JOIN cycles c ON c.id = pa.cycle_id
               WHERE c.student_id = s.id AND pa.solution_revealed = 1) as solutions_revealed,
              (SELECT COALESCE(SUM(cycle_accumulated_ms), 0) FROM cycles c WHERE c.student_id = s.id) as total_training_ms,
              (SELECT GROUP_CONCAT(c.section_filter || ' #' || c.cycle_number, ', ')
               FROM cycles c
               WHERE c.student_id = s.id AND c.completed_at IS NULL) as active_cycles_label,
              (SELECT COALESCE(SUM(cycle_accumulated_ms), 0) FROM cycles c
               WHERE c.student_id = s.id AND c.completed_at IS NULL) as active_cycles_time_ms
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
        $id = (int) ($body['studentId'] ?? 0);
        if ($id <= 0) {
            Http::error('Valid student ID required', 400);
        }

        $student = $this->db->get('SELECT id, name, username FROM students WHERE id = ?', [$id]);
        if (!$student) {
            Http::error('Student not found', 404);
        }

        $tag = trim((string) ($body['coachName'] ?? '')) ?: 'Unassigned';
        $this->ensureAcademyCoach((int) $session['entity']['id'], $tag);

        try {
            $this->db->run(
                'INSERT INTO academy_students (academy_id, student_id, coach_name, is_active) VALUES (?, ?, ?, 1)',
                [(int) $session['entity']['id'], $id, $tag]
            );
            Http::json(['student' => $student, 'coachName' => $tag], 201);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $existing = $this->db->get(
                    'SELECT is_active FROM academy_students WHERE academy_id = ? AND student_id = ?',
                    [(int) $session['entity']['id'], $id]
                );
                if ($existing) {
                    $this->db->run(
                        'UPDATE academy_students SET coach_name = ?, is_active = 1 WHERE academy_id = ? AND student_id = ?',
                        [$tag, (int) $session['entity']['id'], $id]
                    );
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

        if ($coachName === null && $active === null) {
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
            $this->db->run(
                'UPDATE academy_students SET is_active = ? WHERE academy_id = ? AND student_id = ?',
                [$active ? 1 : 0, (int) $session['entity']['id'], $studentId]
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
        ]);
    }

    private function academyRemoveStudent(int $studentId): void
    {
        $session = $this->auth->requireAcademy();
        $changes = $this->db->changes(
            'DELETE FROM academy_students WHERE academy_id = ? AND student_id = ?',
            [(int) $session['entity']['id'], $studentId]
        );
        if ($changes === 0) {
            Http::error('Student not linked to academy', 404);
        }
        Http::json(['ok' => true]);
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
                    Http::json(array_merge($this->enrichCycle($active), ['resumed' => true]));
                    return;
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

        // Pause all running timers (player left puzzle screen)
        foreach ($cycles as $c) {
            if (!empty($c['cycle_resumed_at'])) {
                $this->pauseCycleTimer((int) $c['id']);
            }
        }

        // Re-fetch after pausing to get updated accumulated values
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

        $this->tickCycleTimer($cycleId);

        $existing = $this->db->get(
            'SELECT * FROM puzzle_attempts WHERE cycle_id = ? AND puzzle_id = ? AND completed = 0',
            [$cycleId, $puzzleId]
        );

        if ($existing) {
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

        $this->tickCycleTimer((int) $cycle['id']);

        $puzzle = $this->findPuzzle((int) $attempt['puzzle_id']);
        if (!$puzzle) {
            Http::error('Puzzle not found', 404);
        }

        $attempt = $this->chess->syncAttemptToPlayerTurn($this->db, $puzzle, $cycle, $attempt);
        if (!empty($attempt['completed'])) {
            Http::json([
                'correct' => true,
                'fen' => $this->chess->getPositionFen($puzzle, (int) $attempt['current_move_index']),
                'completed' => true,
                'moveIndex' => (int) $attempt['current_move_index'],
            ]);
            return;
        }

        $moveIndex = (int) ($attempt['current_move_index'] ?? 0);
        $expectedSan = $puzzle['moves'][$moveIndex] ?? null;
        if (!$expectedSan) {
            Http::error('No more moves expected', 400);
        }

        if (!$this->chess->isPlayerMove($puzzle, $moveIndex)) {
            Http::json([
                'correct' => false,
                'fen' => $this->chess->getPositionFen($puzzle, $moveIndex),
                'moveIndex' => $moveIndex,
                'wrongMoves' => (int) $attempt['wrong_moves'],
                'opponentTurn' => true,
            ]);
            return;
        }

        $preFen = $this->chess->getPositionFen($puzzle, $moveIndex);
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

        if (!$this->chess->movesMatch($preFen, $played['san'], $expectedSan)) {
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

        $nextIndex = $moveIndex + 1;
        $stopIndex = $this->chess->getStopIndex($puzzle, $cycle['mode']);
        $lastMove = ['from' => $played['from'], 'to' => $played['to']];
        $currentIndex = $nextIndex;

        if ($currentIndex <= $stopIndex && !$this->chess->isPlayerMove($puzzle, $currentIndex)) {
            $auto = $this->chess->autoPlayOpponentMoves($chess, $puzzle, $currentIndex, $stopIndex);
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
            Http::json([
                'correct' => true,
                'fen' => $newFen,
                'lastMove' => $lastMove,
                'completed' => true,
                'moveIndex' => $currentIndex,
            ]);
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
        $stopIndex = $cycle['mode'] === 'all'
            ? count($puzzle['moves']) - 1
            : ($puzzle['keyMoveIndex'] ?? count($puzzle['moves']) - 1);

        Http::json([
            'attempt' => $updated,
            'solution' => array_slice($puzzle['moves'], 0, $stopIndex + 1),
        ]);
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

        $this->tickCycleTimer((int) $cycle['id']);

        $this->db->run(
            "UPDATE puzzle_attempts SET completed = 1, completed_at = datetime('now') WHERE id = ?",
            [$attemptId]
        );

        $completed = $this->db->get('SELECT * FROM puzzle_attempts WHERE id = ?', [$attemptId]);
        unset($completed['time_ms'], $completed['accumulated_time_ms'], $completed['last_resumed_at']);

        Http::json($completed);
    }

    private function completeCycle(int $cycleId): void
    {
        $session = $this->auth->requireStudent();
        $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        if (!$cycle || (int) $cycle['student_id'] !== (int) $session['entity']['id']) {
            Http::error('Access denied', 403);
        }

        // Finalize cycle time: flush any running session into accumulated
        $this->tickCycleTimer($cycleId);
        $finalCycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        $finalTimeMs = $this->getCycleTimeMs($finalCycle);

        $this->db->run(
            "UPDATE cycles SET completed_at = datetime('now'), total_time_ms = ?, cycle_accumulated_ms = ? WHERE id = ?",
            [$finalTimeMs, $finalTimeMs, $cycleId]
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

        // Pause timer (player left puzzle screen)
        if (!empty($cycle['cycle_resumed_at'])) {
            $this->pauseCycleTimer($cycleId);
            $cycle = $this->db->get('SELECT * FROM cycles WHERE id = ?', [$cycleId]);
        }

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

        $this->tickCycleTimer($cycleId);

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
            $payload['fen'] = $this->chess->getPositionFen($puzzle, (int) $attempt['current_move_index']);
        }

        if ($attempt && !empty($attempt['solution_revealed'])) {
            $stopIndex = $cycle['mode'] === 'all'
                ? count($puzzle['moves']) - 1
                : ($puzzle['keyMoveIndex'] ?? count($puzzle['moves']) - 1);
            $payload['moves'] = array_slice($puzzle['moves'], 0, $stopIndex + 1);
            $payload['keyMoveIndex'] = $puzzle['keyMoveIndex'] ?? null;
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

        Http::json(['puzzle' => $payload, 'attempt' => $attempt, 'cycle' => $updatedCycle]);
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
            $attempts = $this->db->all(
                "SELECT * FROM puzzle_attempts WHERE cycle_id = ? AND completed = 1 ORDER BY completed_at ASC",
                [$c['id']]
            );

            $results = array_map(static fn (array $a) => [
                'id' => $a['id'],
                'puzzleId' => $a['puzzle_id'],
                'name' => $a['puzzle_section'] . ' Exercise ' . $a['puzzle_number'],
                'section' => $a['puzzle_section'],
                'number' => $a['puzzle_number'],
                'timeMs' => 0,
                'completedAt' => $a['completed_at'],
                'wrongMoves' => (int) ($a['wrong_moves'] ?? 0),
                'solutionRevealed' => !empty($a['solution_revealed']),
            ], $attempts);

            return [
                'cycle' => $c,
                'results' => $results,
                'puzzlesCompleted' => count($results),
                'cycle_time_ms' => $this->getCycleTimeMs($c),
            ];
        }, $cycles);

        Http::json([
            'student' => $session['entity'],
            'cycles' => $cycleResults,
            'totalTime' => 0,
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
            $data = $this->buildCycleResults((int) $c['id']);
            return $data ?: ['cycle' => $c, 'results' => [], 'puzzlesCompleted' => 0, 'cycle_time_ms' => 0];
        }, $cycles);

        Http::json(['student' => $student, 'cycles' => $cycleResults]);
    }
}
