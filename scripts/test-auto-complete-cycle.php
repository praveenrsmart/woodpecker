<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

$dupes = $db->all("
    SELECT student_id, section_filter, COUNT(*) as cnt
    FROM cycles
    WHERE completed_at IS NULL
      AND section_filter IN ('Easy', 'Intermediate', 'Advanced')
    GROUP BY student_id, section_filter
    HAVING cnt > 1
");

echo "Duplicate active cycles:\n";
print_r($dupes);

$completed = $db->all("
    SELECT c.id, c.student_id, c.section_filter, c.cycle_number, c.completed_at,
           (SELECT COUNT(DISTINCT puzzle_id) FROM puzzle_attempts pa
            WHERE pa.cycle_id = c.id AND pa.completed = 1) as done_count
    FROM cycles c
    WHERE c.completed_at IS NOT NULL
    ORDER BY c.id DESC
    LIMIT 5
");

echo "\nRecently completed cycles:\n";
foreach ($completed as $row) {
    echo "cycle #{$row['id']} student={$row['student_id']} {$row['section_filter']} #{$row['cycle_number']} done={$row['done_count']}\n";
}
