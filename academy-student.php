<?php
declare(strict_types=1);
$studentId = (int) ($_GET['id'] ?? 0);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Student dashboard — Chess Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/wood/assets/opening.css?v=5" />
  <style>
    .dash-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 16px; }
    @media (max-width: 900px) { .dash-grid { grid-template-columns: 1fr; } }
    .stat-card {
      background: #fff;
      border-radius: 24px;
      padding: 20px 20px 16px;
      box-shadow: 0 16px 40px rgba(99,102,241,.12);
      border-top: 6px solid var(--tone, #6366f1);
    }
    .stat-card.opening { --tone: #6366f1; background: linear-gradient(180deg, #eef2ff, #fff 42%); }
    .stat-card.wood { --tone: #ec4899; background: linear-gradient(180deg, #fdf2f8, #fff 42%); }
    .stat-card.endgame { --tone: #059669; background: linear-gradient(180deg, #ecfdf5, #fff 42%); }
    .stat-card h3 { color: var(--text-bright); font-size: 1.15rem; margin: 0 0 4px; }
    .assigned { color: var(--tone); font-weight: 800; margin-bottom: 14px; }
    .meter { margin-top: 12px; }
    .meter-top { display: flex; justify-content: space-between; gap: 10px; font-size: 0.92rem; margin-bottom: 6px; }
    .meter-top strong { color: var(--text-bright); }
    .track { height: 12px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
    .fill { height: 100%; border-radius: 999px; width: 0; }
    .fill.studied { background: #f59e0b; }
    .fill.tested { background: #059669; }
    .legend { display: flex; gap: 14px; margin-top: 14px; font-size: 12px; font-weight: 800; color: #64748b; }
    .swatch { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; vertical-align: -1px; }
  </style>
</head>
<body class="opening-app">
  <header class="wp-header">
    <a class="wp-logo" id="logo" href="/wood/academy/home">
      <span class="wp-logo-icon">🏫</span>
      <span class="wp-logo-text">Chess <em>Training</em></span>
    </a>
    <nav class="wp-nav">
      <a id="nav-home" href="/wood/academy/home">Home</a>
      <a class="on" id="nav-students" href="/wood/academy.php">Students</a>
      <a id="nav-openings" href="/wood/academy/openings">♟️ Opening library</a>
      <a id="nav-endgames" href="/wood/academy/endgames">♔ Endgame library</a>
      <button class="logout" type="button" id="logout">Log out</button>
    </nav>
  </header>
  <div class="wrap">
    <div class="hero">
      <a id="back" href="/wood/academy.php">← Students</a>
      <h1 id="title">Student dashboard</h1>
      <p class="sub" id="subtitle">Amber is what they have studied. Green is tests they have taken.</p>
    </div>
    <div class="dash-grid" id="progress-grid">
      <p class="empty">Loading dashboard...</p>
    </div>
  </div>
  <script src="/wood/assets/opening-chrome.js?v=4"></script>
  <script>
    const STUDENT_ID = <?= $studentId ?>;
    const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
    const token = localStorage.getItem("woodpecker_token") || "";
    const role = localStorage.getItem("woodpecker_role");
    if (!token || role !== "academy") {
      location.replace(BASE + "/login.php?role=academy");
    }
    document.getElementById("back").setAttribute("href", BASE + "/academy.php");

    function countLabel(n, one, many) {
      return n + " " + (n === 1 ? one : many);
    }
    function percent(done, total) {
      if (!total) return 0;
      return Math.max(0, Math.min(100, Math.round((done / total) * 100)));
    }
    function meter(label, done, total, kind) {
      if (total == null) {
        return '<div class="meter"><div class="meter-top"><span>' + label + '</span><strong>' + done + '</strong></div></div>';
      }
      const width = percent(done, total);
      return '<div class="meter"><div class="meter-top"><span>' + label + '</span><strong>' +
        done + " / " + total + " · " + width + "%</strong></div>" +
        '<div class="track"><div class="fill ' + kind + '" style="width:' + width + '%"></div></div></div>';
    }
    function progressCard(tone, title, assignedText, studied, studiedTotal, tested, testedTotal, testLabel) {
      return '<div class="stat-card ' + tone + '"><h3>' + title + '</h3>' +
        '<div class="assigned">Assigned · ' + assignedText + '</div>' +
        meter("Studied", studied, studiedTotal, "studied") +
        meter(testLabel, tested, testedTotal, "tested") +
        '<div class="legend"><span><i class="swatch" style="background:#f59e0b"></i>Studied</span>' +
        '<span><i class="swatch" style="background:#059669"></i>Tests</span></div></div>';
    }

    fetch(BASE + "/api/academy/students/" + STUDENT_ID + "/stats", {
      headers: { Authorization: "Bearer " + token },
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (!ok) throw new Error(data.error || "Could not load this student");
        const student = data.student || {};
        document.getElementById("title").textContent = student.name || "Student";
        document.getElementById("subtitle").textContent =
          "@" + (student.username || "") + " · Amber is studied. Green is tests taken.";
        const progress = data.progress || {};
        const opening = progress.opening || {};
        const wood = progress.woodpecker || {};
        const endgame = progress.endgame || {};
        document.getElementById("progress-grid").innerHTML =
          progressCard("opening", "Opening",
            countLabel(opening.assignedOpenings || 0, "opening", "openings") + " · " + countLabel(opening.assigned || 0, "chapter", "chapters"),
            opening.studied || 0, opening.assigned || 0, opening.tested || 0, opening.assigned || 0, "Tests taken") +
          progressCard("wood", "Woodpecker",
            countLabel(wood.assigned || 0, "puzzle", "puzzles"),
            wood.studied || 0, wood.assigned || 0, wood.tested || 0, null, "Cycles finished") +
          progressCard("endgame", "Endgame",
            countLabel(endgame.assigned || 0, "position", "positions"),
            endgame.studied || 0, endgame.assigned || 0, endgame.tested || 0, endgame.assigned || 0, "Tests taken");
      })
      .catch((err) => {
        document.getElementById("progress-grid").innerHTML = '<p class="msg error">' + (err.message || "Could not load this student") + "</p>";
      });
  </script>
</body>
</html>
