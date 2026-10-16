<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Choose training — Chess Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --accent: #6366f1;
      --accent-2: #ec4899;
      --text: #475569;
      --text-bright: #1e1b4b;
      --border: #e9d5ff;
      --radius: 16px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Plus Jakarta Sans", system-ui, sans-serif;
      min-height: 100vh;
      color: var(--text);
      background:
        radial-gradient(circle at 8% 12%, rgba(167,139,250,.45) 0%, transparent 42%),
        radial-gradient(circle at 92% 8%, rgba(34,211,238,.35) 0%, transparent 38%),
        linear-gradient(155deg, #faf5ff, #eff6ff 45%, #fff7ed);
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 36px 20px 64px; }
    .top { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 28px; }
    h1 {
      font-size: 1.7rem;
      background: linear-gradient(135deg, #6366f1, #ec4899);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .intro { margin-top: 6px; line-height: 1.5; }
    .logout {
      border: 2px solid var(--border);
      background: #fff;
      color: var(--accent);
      font: inherit;
      font-weight: 800;
      padding: 10px 14px;
      border-radius: 12px;
      cursor: pointer;
    }
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 20px;
    }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    a.card {
      display: block;
      text-decoration: none;
      color: inherit;
      background: #fffffff2;
      border-radius: 24px;
      padding: 32px 28px;
      box-shadow: 0 16px 40px rgba(99,102,241,.16);
      border: 2px solid transparent;
      min-height: 220px;
      transition: transform .15s ease, border-color .15s ease;
    }
    a.card:hover { transform: translateY(-3px); border-color: #c7d2fe; }
    .icon { font-size: 42px; margin-bottom: 12px; }
    a.card h2 { color: var(--text-bright); font-size: 1.4rem; margin-bottom: 8px; }
    a.card p { line-height: 1.55; }
    .go { margin-top: 18px; font-weight: 800; color: var(--accent); }
    .progress { margin-top: 28px; }
    .progress h2 { color: var(--text-bright); font-size: 1.25rem; margin-bottom: 6px; }
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
    .stat-card h3 { color: var(--text-bright); font-size: 1.15rem; margin-bottom: 4px; }
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
    .progress-msg { margin-top: 8px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1>Welcome<span id="name"></span></h1>
        <p class="intro">Choose a module to continue.</p>
      </div>
      <button class="logout" type="button" id="logout">Log out</button>
    </div>
    <div class="grid">
      <a class="card" data-href="/opening.php" href="/wood/opening.php">
        <div class="icon">♟️</div>
        <h2>Opening</h2>
        <p>Study assigned openings on a chess board, then take a test for each chapter.</p>
        <div class="go">Open Opening →</div>
      </a>
      <a class="card" data-href="/" href="/wood/">
        <div class="icon">🪶</div>
        <h2>Woodpecker</h2>
        <p>The existing puzzle trainer — Easy, Intermediate, and Advanced cycles.</p>
        <div class="go">Open Woodpecker →</div>
      </a>
      <a class="card" data-href="/endgame.php" href="/wood/endgame.php">
        <div class="icon">♔</div>
        <h2>Endgame</h2>
        <p>Practice assigned endgame positions against the engine, then pass a clean test.</p>
        <div class="go">Open Endgame →</div>
      </a>
    </div>
    <section class="progress" id="progress">
      <h2>Progress dashboard</h2>
      <p class="intro">Amber is what you have studied. Green is tests you have taken. Both are measured against what is assigned.</p>
      <div class="grid" id="progress-grid" style="margin-top:16px">
        <p class="progress-msg">Loading progress...</p>
      </div>
    </section>
  </div>
  <script>
    const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
    const role = localStorage.getItem("woodpecker_role");
    const token = localStorage.getItem("woodpecker_token");
    if (!token || role !== "student") {
      window.location.replace(BASE + "/login.php?role=student");
    } else {
      try {
        const student = JSON.parse(localStorage.getItem("woodpecker_student") || "null");
        if (student && student.name) {
          document.getElementById("name").textContent = ", " + student.name;
        }
      } catch (e) {}
      document.querySelectorAll("a.card").forEach((a) => {
        const path = a.getAttribute("data-href") || "";
        a.setAttribute("href", BASE + path);
      });
    }
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

    if (token && role === "student") {
      fetch(BASE + "/api/me/progress", { headers: { Authorization: "Bearer " + token } })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          const box = document.getElementById("progress-grid");
          if (!ok) throw new Error(data.error || "Could not load progress");
          const opening = data.opening || {};
          const wood = data.woodpecker || {};
          const endgame = data.endgame || {};
          box.innerHTML =
            progressCard(
              "opening",
              "Opening",
              countLabel(opening.assignedOpenings || 0, "opening", "openings") + " · " + countLabel(opening.assigned || 0, "chapter", "chapters"),
              opening.studied || 0,
              opening.assigned || 0,
              opening.tested || 0,
              opening.assigned || 0,
              "Tests taken"
            ) +
            progressCard(
              "wood",
              "Woodpecker",
              countLabel(wood.assigned || 0, "puzzle", "puzzles"),
              wood.studied || 0,
              wood.assigned || 0,
              wood.tested || 0,
              null,
              "Cycles finished"
            ) +
            progressCard(
              "endgame",
              "Endgame",
              countLabel(endgame.assigned || 0, "position", "positions"),
              endgame.studied || 0,
              endgame.assigned || 0,
              endgame.tested || 0,
              endgame.assigned || 0,
              "Tests taken"
            );
        })
        .catch((err) => {
          const box = document.getElementById("progress-grid");
          if (box) box.innerHTML = '<p class="progress-msg">' + (err.message || "Could not load progress") + "</p>";
        });
    }

    document.getElementById("logout").addEventListener("click", () => {
      localStorage.removeItem("woodpecker_token");
      localStorage.removeItem("woodpecker_role");
      localStorage.removeItem("woodpecker_student");
      localStorage.removeItem("woodpecker_academy");
      window.location.href = BASE + "/login.php?role=student";
    });
  </script>
</body>
</html>
