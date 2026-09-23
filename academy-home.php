<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Academy home — Chess Training</title>
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
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1>Academy<span id="name"></span></h1>
        <p class="intro">Choose a module to manage.</p>
      </div>
      <button class="logout" type="button" id="logout">Log out</button>
    </div>
    <div class="grid">
      <a class="card" data-href="/academy/openings" href="/wood/academy/openings">
        <div class="icon">♟️</div>
        <h2>Opening library</h2>
        <p>Upload PGN openings, organize chapters, and assign them to students.</p>
        <div class="go">Open Opening library →</div>
      </a>
      <a class="card" data-href="/academy.php" href="/wood/academy.php">
        <div class="icon">🪶</div>
        <h2>Students</h2>
        <p>Create student logins, track Woodpecker progress, and manage active players.</p>
        <div class="go">Open Students →</div>
      </a>
      <a class="card" data-href="/academy/endgames" href="/wood/academy/endgames">
        <div class="icon">♔</div>
        <h2>Endgame library</h2>
        <p>Build endgame categories and chapters, then assign them for engine practice.</p>
        <div class="go">Open Endgame library →</div>
      </a>
    </div>
  </div>
  <script>
    const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
    const role = localStorage.getItem("woodpecker_role");
    const token = localStorage.getItem("woodpecker_token");
    if (!token || role !== "academy") {
      window.location.replace(BASE + "/login.php?role=academy");
    } else {
      try {
        const academy = JSON.parse(localStorage.getItem("woodpecker_academy") || "null");
        if (academy && academy.name) {
          document.getElementById("name").textContent = " — " + academy.name;
        }
      } catch (e) {}
      document.querySelectorAll("a.card").forEach((a) => {
        const path = a.getAttribute("data-href") || "";
        a.setAttribute("href", BASE + path);
      });
    }
    document.getElementById("logout").addEventListener("click", () => {
      localStorage.removeItem("woodpecker_token");
      localStorage.removeItem("woodpecker_role");
      localStorage.removeItem("woodpecker_student");
      localStorage.removeItem("woodpecker_academy");
      window.location.href = BASE + "/login.php?role=academy";
    });
  </script>
</body>
</html>
