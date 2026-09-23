<?php

declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->init();
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function get(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function run(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function changes(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function init(): void
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS students (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                username TEXT UNIQUE,
                password_hash TEXT,
                created_at TEXT DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS cycles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NOT NULL,
                cycle_number INTEGER NOT NULL,
                mode TEXT DEFAULT 'all',
                section_filter TEXT DEFAULT 'all',
                started_at TEXT DEFAULT (datetime('now')),
                completed_at TEXT,
                total_time_ms INTEGER DEFAULT 0,
                FOREIGN KEY (student_id) REFERENCES students(id),
                UNIQUE(student_id, section_filter, cycle_number)
            );

            CREATE TABLE IF NOT EXISTS puzzle_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cycle_id INTEGER NOT NULL,
                puzzle_id INTEGER NOT NULL,
                puzzle_number INTEGER NOT NULL,
                puzzle_section TEXT NOT NULL,
                started_at TEXT,
                completed_at TEXT,
                time_ms INTEGER DEFAULT 0,
                wrong_moves INTEGER DEFAULT 0,
                wrong_move_list TEXT DEFAULT '[]',
                completed INTEGER DEFAULT 0,
                solution_revealed INTEGER DEFAULT 0,
                revealed_at TEXT,
                FOREIGN KEY (cycle_id) REFERENCES cycles(id)
            );

            CREATE INDEX IF NOT EXISTS idx_attempts_cycle ON puzzle_attempts(cycle_id);
            CREATE INDEX IF NOT EXISTS idx_cycles_student ON cycles(student_id);

            CREATE TABLE IF NOT EXISTS academies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS super_admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT 'Super Admin',
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS academy_students (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                academy_id INTEGER NOT NULL,
                student_id INTEGER NOT NULL,
                coach_name TEXT NOT NULL DEFAULT '',
                added_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                UNIQUE(academy_id, student_id)
            );

            CREATE INDEX IF NOT EXISTS idx_academy_students_academy ON academy_students(academy_id);
            CREATE INDEX IF NOT EXISTS idx_academy_students_coach ON academy_students(coach_name);

            CREATE TABLE IF NOT EXISTS academy_coaches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                academy_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE,
                UNIQUE(academy_id, name)
            );

            CREATE INDEX IF NOT EXISTS idx_academy_coaches_academy ON academy_coaches(academy_id);
        ");
    }

    private function migrate(): void
    {
        $cols = array_column($this->all('PRAGMA table_info(students)'), 'name');
        if (!in_array('username', $cols, true)) {
            $this->exec('ALTER TABLE students ADD COLUMN username TEXT');
            $this->exec('ALTER TABLE students ADD COLUMN password_hash TEXT');
            foreach ($this->all('SELECT id, name FROM students') as $s) {
                $username = preg_replace('/[^a-z0-9]/', '', strtolower($s['name'])) . $s['id'];
                $this->run('UPDATE students SET username = ? WHERE id = ?', [$username, $s['id']]);
            }
            $this->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_students_username ON students(username)');
        }

        $attemptCols = array_column($this->all('PRAGMA table_info(puzzle_attempts)'), 'name');
        if (!in_array('solution_revealed', $attemptCols, true)) {
            $this->exec('ALTER TABLE puzzle_attempts ADD COLUMN solution_revealed INTEGER DEFAULT 0');
            $this->exec('ALTER TABLE puzzle_attempts ADD COLUMN revealed_at TEXT');
        }
        $attemptCols2 = array_column($this->all('PRAGMA table_info(puzzle_attempts)'), 'name');
        if (!in_array('current_move_index', $attemptCols2, true)) {
            $this->exec('ALTER TABLE puzzle_attempts ADD COLUMN current_move_index INTEGER DEFAULT 0');
        }

        $attemptCols3 = array_column($this->all('PRAGMA table_info(puzzle_attempts)'), 'name');
        if (!in_array('accumulated_time_ms', $attemptCols3, true)) {
            $this->exec('ALTER TABLE puzzle_attempts ADD COLUMN accumulated_time_ms INTEGER DEFAULT 0');
            $this->exec('ALTER TABLE puzzle_attempts ADD COLUMN last_resumed_at TEXT');
        }

        $attemptCols4 = array_column($this->all('PRAGMA table_info(puzzle_attempts)'), 'name');
        if (!in_array('active_moves', $attemptCols4, true)) {
            $this->exec('ALTER TABLE puzzle_attempts ADD COLUMN active_moves TEXT');
        }

        $cycleCols = array_column($this->all('PRAGMA table_info(cycles)'), 'name');
        if (!in_array('cycle_accumulated_ms', $cycleCols, true)) {
            $this->exec('ALTER TABLE cycles ADD COLUMN cycle_accumulated_ms INTEGER DEFAULT 0');
            $this->exec('ALTER TABLE cycles ADD COLUMN cycle_resumed_at TEXT');
            $this->exec('ALTER TABLE cycles ADD COLUMN last_activity_at TEXT');
        }

        $this->migrateSectionScopedCycleNumbers();
        $this->dedupeActiveCycles();
        $this->dropPartialActiveCycleIndex();
        $this->migrateAcademyCoaches();
        $this->migrateAcademyStudentActive();
        $this->migrateAccessControl();
        $this->migrateDefaultSuperAdmin();
        $this->migrateOpenings();
        $this->migrateEndgames();
        $this->migrateFeathersAcademy();
    }

    public function ensureSuperAdmin(string $username, string $password): void
    {
        $existing = $this->get('SELECT id FROM super_admins LIMIT 1');
        if ($existing) {
            return;
        }

        $clean = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($username))) ?? '';
        if ($clean === '' || strlen($password) < 4) {
            return;
        }

        if (!function_exists('hashPassword')) {
            require_once __DIR__ . '/Password.php';
        }

        $this->run(
            'INSERT INTO super_admins (name, username, password_hash) VALUES (?, ?, ?)',
            ['Super Admin', $clean, hashPassword($password)]
        );
    }

    private function migrateAcademyStudentActive(): void
    {
        $cols = array_column($this->all('PRAGMA table_info(academy_students)'), 'name');
        if (!in_array('is_active', $cols, true)) {
            $this->exec('ALTER TABLE academy_students ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        }
    }

    private function migrateAccessControl(): void
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS super_admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT 'Super Admin',
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");

        $academyCols = array_column($this->all('PRAGMA table_info(academies)'), 'name');
        if (!in_array('is_active', $academyCols, true)) {
            $this->exec('ALTER TABLE academies ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        }

        $studentCols = array_column($this->all('PRAGMA table_info(students)'), 'name');
        if (!in_array('is_active', $studentCols, true)) {
            $this->exec('ALTER TABLE students ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        }
        if (!in_array('created_by_academy_id', $studentCols, true)) {
            $this->exec('ALTER TABLE students ADD COLUMN created_by_academy_id INTEGER');
        }
    }

    /**
     * One-time: ensure a known default super admin exists (no CLI required).
     * Username: superadmin
     * Password: Woodpecker#Admin1
     * Change this password after first login.
     */
    private function migrateDefaultSuperAdmin(): void
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS app_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ");

        $done = $this->get("SELECT value FROM app_meta WHERE key = 'default_super_admin_seeded'");
        if ($done) {
            return;
        }

        if (!function_exists('hashPassword')) {
            require_once __DIR__ . '/Password.php';
        }

        $username = 'superadmin';
        $password = 'Woodpecker#Admin1';
        $hash = hashPassword($password);

        $existing = $this->get('SELECT id FROM super_admins WHERE username = ?', [$username]);
        if ($existing) {
            $this->run(
                'UPDATE super_admins SET password_hash = ? WHERE id = ?',
                [$hash, (int) $existing['id']]
            );
        } else {
            $this->run(
                'INSERT INTO super_admins (name, username, password_hash) VALUES (?, ?, ?)',
                ['Super Admin', $username, $hash]
            );
        }

        $this->run(
            "INSERT OR REPLACE INTO app_meta (key, value) VALUES ('default_super_admin_seeded', ?)",
            ['1']
        );
    }

    private function migrateOpenings(): void
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS openings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                academy_id INTEGER NOT NULL,
                color_group TEXT NOT NULL,
                name TEXT NOT NULL,
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_openings_academy ON openings(academy_id);

            CREATE TABLE IF NOT EXISTS opening_chapters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                opening_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                pgn TEXT NOT NULL,
                start_fen TEXT NOT NULL,
                moves_json TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (opening_id) REFERENCES openings(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_opening_chapters_opening ON opening_chapters(opening_id);

            CREATE TABLE IF NOT EXISTS opening_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                opening_id INTEGER NOT NULL,
                student_id INTEGER NOT NULL,
                academy_id INTEGER NOT NULL,
                assigned_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (opening_id) REFERENCES openings(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                UNIQUE(opening_id, student_id)
            );
            CREATE INDEX IF NOT EXISTS idx_opening_assignments_student ON opening_assignments(student_id);
            CREATE INDEX IF NOT EXISTS idx_opening_assignments_opening ON opening_assignments(opening_id);

            CREATE TABLE IF NOT EXISTS opening_tests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NOT NULL,
                opening_id INTEGER NOT NULL,
                chapter_id INTEGER NOT NULL,
                started_at TEXT DEFAULT (datetime('now')),
                completed_at TEXT,
                passed INTEGER NOT NULL DEFAULT 0,
                wrong_moves INTEGER NOT NULL DEFAULT 0,
                correct_moves INTEGER NOT NULL DEFAULT 0,
                total_player_moves INTEGER NOT NULL DEFAULT 0,
                time_ms INTEGER NOT NULL DEFAULT 0,
                current_move_index INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (opening_id) REFERENCES openings(id) ON DELETE CASCADE,
                FOREIGN KEY (chapter_id) REFERENCES opening_chapters(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_opening_tests_student ON opening_tests(student_id, opening_id);
            CREATE INDEX IF NOT EXISTS idx_opening_tests_chapter ON opening_tests(chapter_id, student_id);
        ");
    }

    private function migrateEndgames(): void
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS endgame_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                academy_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                notes TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (academy_id) REFERENCES academies(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_endgame_categories_academy ON endgame_categories(academy_id);

            CREATE TABLE IF NOT EXISTS endgame_subcategories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (category_id) REFERENCES endgame_categories(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_endgame_subcategories_category ON endgame_subcategories(category_id);

            CREATE TABLE IF NOT EXISTS endgame_chapters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                subcategory_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                fen TEXT NOT NULL,
                goal TEXT NOT NULL,
                notes TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (subcategory_id) REFERENCES endgame_subcategories(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_endgame_chapters_sub ON endgame_chapters(subcategory_id);

            CREATE TABLE IF NOT EXISTS endgame_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                student_id INTEGER NOT NULL,
                academy_id INTEGER NOT NULL,
                assigned_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (category_id) REFERENCES endgame_categories(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                UNIQUE(category_id, student_id)
            );
            CREATE INDEX IF NOT EXISTS idx_endgame_assignments_student ON endgame_assignments(student_id);

            CREATE TABLE IF NOT EXISTS endgame_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                chapter_id INTEGER NOT NULL,
                mode TEXT NOT NULL DEFAULT 'practice',
                engine_level TEXT NOT NULL DEFAULT 'intermediate',
                started_at TEXT DEFAULT (datetime('now')),
                completed_at TEXT,
                passed INTEGER NOT NULL DEFAULT 0,
                failed_restarts INTEGER NOT NULL DEFAULT 0,
                result TEXT NOT NULL DEFAULT '*',
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES endgame_categories(id) ON DELETE CASCADE,
                FOREIGN KEY (chapter_id) REFERENCES endgame_chapters(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_endgame_attempts_student ON endgame_attempts(student_id, chapter_id);
        ");
    }

    private function migrateFeathersAcademy(): void
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS app_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        ");
        $done = $this->get("SELECT value FROM app_meta WHERE key = 'feathers_students_moved'");
        if ($done) {
            return;
        }

        $academy = $this->get(
            "SELECT * FROM academies
             WHERE LOWER(name) LIKE '%feather%' OR LOWER(username) LIKE '%feather%'
             ORDER BY id ASC LIMIT 1"
        );
        if (!$academy) {
            if (!function_exists('hashPassword')) {
                require_once __DIR__ . '/Password.php';
            }
            $id = $this->run(
                'INSERT INTO academies (name, username, password_hash, is_active) VALUES (?, ?, ?, 1)',
                ['Feathers Academy', 'feathers', hashPassword('feathers1234')]
            );
            $academy = $this->get('SELECT * FROM academies WHERE id = ?', [$id]);
        }
        if (!$academy) {
            return;
        }

        $academyId = (int) $academy['id'];
        $this->run('UPDATE academies SET is_active = 1 WHERE id = ?', [$academyId]);

        foreach ($this->all('SELECT id FROM students') as $student) {
            $studentId = (int) $student['id'];
            $this->run(
                'INSERT OR IGNORE INTO academy_students (academy_id, student_id, coach_name, is_active) VALUES (?, ?, ?, 1)',
                [$academyId, $studentId, '']
            );
            $this->run(
                'UPDATE academy_students SET is_active = 1 WHERE academy_id = ? AND student_id = ?',
                [$academyId, $studentId]
            );
            $this->run(
                'UPDATE students SET created_by_academy_id = ? WHERE id = ?',
                [$academyId, $studentId]
            );
        }
        $this->run('DELETE FROM academy_students WHERE academy_id != ?', [$academyId]);
        $this->run(
            "INSERT OR REPLACE INTO app_meta (key, value) VALUES ('feathers_students_moved', ?)",
            [(string) $academyId]
        );
    }

    private function migrateAcademyCoaches(): void
    {
        $table = $this->get("SELECT name FROM sqlite_master WHERE type='table' AND name='academy_coaches'");
        if (!$table) {
            return;
        }
        $this->exec("
            INSERT OR IGNORE INTO academy_coaches (academy_id, name)
            SELECT DISTINCT academy_id, coach_name FROM academy_students
            WHERE coach_name != '' AND coach_name != 'Unassigned'
        ");
    }

    private function migrateSectionScopedCycleNumbers(): void
    {
        $tables = array_column(
            $this->all("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('cycles', 'cycles_new')"),
            'name'
        );

        if (in_array('cycles_new', $tables, true)) {
            $this->exec('PRAGMA foreign_keys = OFF');
            if (in_array('cycles', $tables, true)) {
                $this->exec('DROP TABLE cycles');
            }
            $this->exec('ALTER TABLE cycles_new RENAME TO cycles');
            $this->exec('CREATE INDEX IF NOT EXISTS idx_cycles_student ON cycles(student_id)');
            $this->exec('PRAGMA foreign_keys = ON');
            return;
        }

        $row = $this->get("SELECT sql FROM sqlite_master WHERE type='table' AND name='cycles'");
        if (!$row || !isset($row['sql'])) {
            return;
        }
        if (str_contains($row['sql'], 'section_filter, cycle_number')) {
            return;
        }

        $this->exec('PRAGMA foreign_keys = OFF');
        $this->exec("
            CREATE TABLE cycles_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER NOT NULL,
                cycle_number INTEGER NOT NULL,
                mode TEXT DEFAULT 'all',
                section_filter TEXT DEFAULT 'all',
                started_at TEXT DEFAULT (datetime('now')),
                completed_at TEXT,
                total_time_ms INTEGER DEFAULT 0,
                FOREIGN KEY (student_id) REFERENCES students(id),
                UNIQUE(student_id, section_filter, cycle_number)
            );

            INSERT INTO cycles_new (id, student_id, cycle_number, mode, section_filter, started_at, completed_at, total_time_ms)
            SELECT
                id,
                student_id,
                ROW_NUMBER() OVER (PARTITION BY student_id, section_filter ORDER BY started_at ASC, id ASC),
                mode,
                section_filter,
                started_at,
                completed_at,
                total_time_ms
            FROM cycles;

            DROP TABLE cycles;
            ALTER TABLE cycles_new RENAME TO cycles;
            CREATE INDEX IF NOT EXISTS idx_cycles_student ON cycles(student_id);
        ");
        $this->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Partial indexes (CREATE INDEX ... WHERE) require SQLite 3.8+ and break on
     * older shared-hosting builds. Uniqueness is enforced in application code.
     */
    private function dropPartialActiveCycleIndex(): void
    {
        $this->exec('DROP INDEX IF EXISTS idx_one_active_cycle_per_section');
    }

    private function dedupeActiveCycles(): void
    {
        $dupes = $this->all("
            SELECT student_id, section_filter, COUNT(*) as cnt
            FROM cycles
            WHERE completed_at IS NULL
              AND section_filter IN ('Easy', 'Intermediate', 'Advanced')
            GROUP BY student_id, section_filter
            HAVING cnt > 1
        ");

        foreach ($dupes as $row) {
            $active = $this->all("
                SELECT id FROM cycles
                WHERE student_id = ? AND section_filter = ? AND completed_at IS NULL
                ORDER BY started_at DESC
            ", [$row['student_id'], $row['section_filter']]);
            for ($i = 1, $n = count($active); $i < $n; $i++) {
                $this->run("UPDATE cycles SET completed_at = datetime('now') WHERE id = ?", [$active[$i]['id']]);
            }
        }
    }
}
