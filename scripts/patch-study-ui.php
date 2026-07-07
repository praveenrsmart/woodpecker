<?php

$path = dirname(__DIR__) . '/assets/index-sA8ZWdLG.js';
$text = file_get_contents($path);

$old = '(ue||Z)&&vt&&(vt.studyNotes&&vt.studyNotes.length>0||vt.fullLine&&vt.fullLine.moves&&vt.fullLine.moves.length>0||vt.otherLines&&vt.otherLines.length>0||vt.variantExercises&&vt.variantExercises.length>0)&&';
$new = '(ue||Z)&&vt&&';

if (!str_contains($text, $old)) {
    fwrite(STDERR, "Panel condition not found\n");
    exit(1);
}

file_put_contents($path, str_replace($old, $new, $text));
echo "Patched panel visibility\n";
