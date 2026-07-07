<?php

declare(strict_types=1);

$jsPath = dirname(__DIR__) . '/assets/index-sA8ZWdLG.js';
$cssPath = dirname(__DIR__) . '/assets/index-B2gsu7wV.css';

$js = file_get_contents($jsPath);

// Remove study panel from sidebar (between solution panel and reveal section).
$sidebarStudyStart = '(ue||Z)&&vt&&d.jsxs("div",{style:{marginTop:12,padding:14,background:"linear-gradient(135deg,#f0f9ff,#eef2ff)",borderRadius:10,border:"1px solid #bae6fd"},children:';
$revealMarker = '!Z&&!ue&&d.jsx("div",{className:ze.revealSection';

$start = strpos($js, $sidebarStudyStart);
if ($start === false) {
    fwrite(STDERR, "Study panel start not found in sidebar\n");
    exit(1);
}
$reveal = strpos($js, $revealMarker, $start);
if ($reveal === false) {
    fwrite(STDERR, "Reveal section marker not found\n");
    exit(1);
}

$studyBlock = substr($js, $start, $reveal - $start);
$js = substr($js, 0, $start) . substr($js, $reveal);

$belowBoardStudy = <<<'JS'
(ue||Z)&&vt&&d.jsxs("div",{className:"wp-study-below",children:[d.jsx("h4",{className:"wp-study-title",children:"Study more — book variations & notes"}),vt.fullLine&&vt.fullLine.moves&&vt.fullLine.moves.length>0&&d.jsxs("div",{className:"wp-study-section",children:[d.jsx("h5",{className:"wp-study-label",children:vt.fullLine.label||"Complete main line"}),d.jsx("div",{className:"wp-study-moves",children:vt.fullLine.moves.map((Oe,qe)=>d.jsx("span",{children:Oe},`f-${qe}`))})]}),(vt.studyNotes||[]).map((Oe,qe)=>d.jsxs("div",{className:"wp-study-section",children:[d.jsx("h5",{className:"wp-study-label",children:Oe.label}),d.jsx("p",{className:"wp-study-text",children:Oe.text})]},`n-${qe}`)),vt.playedLine&&vt.playedLine.moves&&vt.playedLine.moves.length>0&&vt.otherLines&&vt.otherLines.length>0&&d.jsxs("div",{className:"wp-study-section",children:[d.jsx("h5",{className:"wp-study-label",children:vt.playedLine.label}),d.jsx("div",{className:"wp-study-moves",children:vt.playedLine.moves.map((Oe,qe)=>d.jsx("span",{className:"wp-study-move-done",children:Oe},`p-${qe}`))})]}),(vt.otherLines||[]).map((Oe,qe)=>d.jsxs("div",{className:"wp-study-section",children:[d.jsxs("h5",{className:"wp-study-label",children:[Oe.label,Oe.branchMove?` (from ${Oe.branchMove})`:"",Oe.note&&Oe.label.toLowerCase()!==Oe.note.toLowerCase()?` — ${Oe.note}`:""]}),d.jsx("div",{className:"wp-study-moves",children:Oe.moves.map((Ot,st)=>d.jsx("span",{children:Ot},`o-${qe}-${st}`))})]},`alt-${qe}`)),(vt.variantExercises||[]).map((Oe,qe)=>d.jsxs("div",{className:"wp-study-section",children:[d.jsx("h5",{className:"wp-study-label wp-study-variant",children:Oe.label}),Oe.description&&d.jsx("p",{className:"wp-study-meta",children:Oe.description}),d.jsx("div",{className:"wp-study-moves",children:Oe.moves.map((Ot,st)=>d.jsx("span",{children:Ot},`v-${qe}-${st}`))})]},`var-${qe}`))]})
JS;

$anchor = ']})]}),d.jsxs("div",{className:ze.sidePanel,children:';
if (!str_contains($js, $anchor)) {
    fwrite(STDERR, "boardArea/sidePanel anchor not found\n");
    exit(1);
}

$js = str_replace($anchor, ']})]}),' . $belowBoardStudy . ',d.jsxs("div",{className:ze.sidePanel,children:', $js);

file_put_contents($jsPath, $js);

$css = file_get_contents($cssPath);
$marker = '/* wp-study-below */';
if (!str_contains($css, $marker)) {
    $css .= <<<'CSS'

/* wp-study-below — full-width study panel under the board */
.wp-study-below{grid-column:1/-1;margin-top:8px;padding:22px 26px;background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 6px 24px #0f172a0f;width:100%;box-sizing:border-box;position:relative;z-index:1}
.wp-study-title{margin:0 0 18px;font-size:1.125rem;font-weight:700;color:#0369a1;letter-spacing:-.01em}
.wp-study-section{margin-bottom:20px}
.wp-study-section:last-child{margin-bottom:0}
.wp-study-label{margin:0 0 10px;font-size:1rem;font-weight:600;color:#334155;line-height:1.4}
.wp-study-label.wp-study-variant{color:#6d28d9}
.wp-study-text{margin:0;font-size:1rem;line-height:1.65;color:#1e293b;white-space:pre-wrap}
.wp-study-meta{margin:0 0 8px;font-size:.9375rem;line-height:1.5;color:#64748b}
.wp-study-moves{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
.wp-study-moves span{display:inline-block;padding:9px 14px;border-radius:9px;font-size:1rem;font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0}
.wp-study-moves .wp-study-move-done{background:#ecfdf5;border-color:#86efac;color:#166534}
/* Sidebar scrolls with page on mobile/tablet and whenever study content is shown */
._sidePanel_e7hhc_147{position:static!important;top:auto!important}
._page_e7hhc_1:has(.wp-study-below) ._sidePanel_e7hhc_147{position:static!important;top:auto!important}
@media (min-width:1025px){._page_e7hhc_1:not(:has(.wp-study-below)) ._sidePanel_e7hhc_147{position:sticky!important;top:72px!important}}
@media (max-width:1024px){._page_e7hhc_1{grid-template-columns:1fr!important;padding:12px!important;gap:16px!important}._sidePanel_e7hhc_147{position:static!important;top:auto!important;align-self:stretch!important}.wp-study-below{padding:16px 18px;margin-top:8px}.wp-study-title{font-size:1.0625rem}.wp-study-label{font-size:.975rem}.wp-study-text{font-size:.975rem;line-height:1.6}.wp-study-moves span{font-size:.9375rem;padding:8px 12px}}
@media (max-width:480px){.wp-study-below{padding:14px 16px;border-radius:12px}.wp-study-moves span{font-size:.9rem;padding:7px 10px}}

CSS;
    file_put_contents($cssPath, $css);
}

echo "Moved study panel below board with larger readable text\n";
echo "Removed sidebar block: " . strlen($studyBlock) . " bytes\n";
