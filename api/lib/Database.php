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
    }

    private function migrateAcademyStudentActive(): void
    {
        $cols = array_column($this->all('PRAGMA table_info(academy_students)'), 'name');
        if (!in_array('is_active', $cols, true)) {
            $this->exec('ALTER TABLE academy_students ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        }
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
