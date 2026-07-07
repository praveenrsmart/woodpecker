<?php

require __DIR__ . '/../api/bootstrap.php';

$puzzles = json_decode(file_get_contents(__DIR__ . '/../data/puzzles.json'), true);
$chess = new ChessService();

$p918 = null;
foreach ($puzzles as $p) {
    if (($p['number'] ?? 0) == 918 && empty($p['variant'])) {
        $p918 = $p;
        break;
    }
}
echo '918 alt lines: ' . count($p918['alternativeLines'] ?? []) . PHP_EOL;
$sw = $chess->trySwitchLine($p918, 2, 'Qxd4');
echo 'Switch Qxd4: ' . ($sw ? 'ok, len=' . count($sw['moves']) : 'fail') . PHP_EOL;
if ($sw) {
    echo '  ' . implode(' ', $sw['moves']) . PHP_EOL;
}

$p658 = null;
foreach ($puzzles as $p) {
    if (($p['number'] ?? 0) == 658) {
        $p658 = $p;
        break;
    }
}
$sw2 = $chess->trySwitchLine($p658, 2, 'Bxf5');
echo '658 Switch Bxf5: ' . ($sw2 ? 'ok: ' . implode(' ', array_slice($sw2['moves'], 2)) : 'fail') . PHP_EOL;

$p690 = null;
foreach ($puzzles as $p) {
    if (($p['number'] ?? 0) == 690 && empty($p['variant'])) {
        $p690 = $p;
        break;
    }
}
$sw3 = $chess->trySwitchLine($p690, 0, 'gxh4');
echo '690 Switch gxh4: ' . ($sw3 ? 'ok: ' . implode(' ', $sw3['moves']) : 'fail') . PHP_EOL;
