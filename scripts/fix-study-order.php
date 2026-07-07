<?php

$jsPath = dirname(__DIR__) . '/assets/index-sA8ZWdLG.js';
$js = file_get_contents($jsPath);

$studyStartMarker = '(ue||Z)&&vt&&d.jsxs("div",{className:"wp-study-below"';
$sideStartMarker = ',d.jsxs("div",{className:ze.sidePanel,children:';

$studyStart = strpos($js, $studyStartMarker);
$sideStart = strpos($js, $sideStartMarker, $studyStart);
if ($studyStart === false || $sideStart === false) {
    fwrite(STDERR, "Markers not found\n");
    exit(1);
}

// Study block ends right before sidePanel marker
$studyBlock = substr($js, $studyStart, $sideStart - $studyStart);
$js = substr($js, 0, $studyStart) . substr($js, $sideStart);

// sidePanel now starts immediately after boardArea - find end of sidePanel (before closing page)
$sideStart = strpos($js, $sideStartMarker);
$hintEnd = strpos($js, 'Drag pieces to play moves. Only correct moves advance."})]})]})}function Qi', $sideStart);
if ($hintEnd === false) {
    fwrite(STDERR, "sidePanel end not found\n");
    exit(1);
}
// Insert study after sidePanel: before `]})]})}function Qi`
$insertAt = $hintEnd + strlen('Drag pieces to play moves. Only correct moves advance."})]})');
$js = substr($js, 0, $insertAt) . ',' . $studyBlock . substr($js, $insertAt);

file_put_contents($jsPath, $js);
echo "Reordered: board -> sidebar -> study below (full width)\n";
