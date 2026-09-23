<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Opening library — Academy</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/wood/assets/opening.css?v=4" />
  <link rel="stylesheet" href="/wood/assets/opening-pieces.css" />
</head>
<body class="opening-app">
  <header class="wp-header">
    <a class="wp-logo" id="logo" href="/wood/academy/home">
      <span class="wp-logo-icon">♟️</span>
      <span class="wp-logo-text">Chess <em>Training</em></span>
    </a>
    <nav class="wp-nav">
      <a id="nav-home" href="/wood/academy/home">Home</a>
      <a id="nav-academy" href="/wood/academy.php">Students</a>
      <a class="on" href="/wood/academy/openings">♟️ Opening library</a>
      <a href="/wood/academy/endgames">♔ Endgame library</a>
      <button class="logout" type="button" id="logout">Log out</button>
    </nav>
  </header>
  <div class="wrap">
    <div class="hero">
      <a id="back" href="/wood/academy.php">← Students</a>
      <h1>Opening library</h1>
      <p class="sub">Group by White or Black, add opening names, then paste a PGN for each chapter. Assign openings so players can study and take tests.</p>
    </div>
    <div class="grid-2">
      <aside class="card library-side">
        <div class="chips">
          <button class="chip on" type="button" data-group="white">White</button>
          <button class="chip" type="button" data-group="black">Black</button>
        </div>
        <form id="create-opening">
          <div class="field">
            <label>New opening name</label>
            <input id="opening-name" placeholder="Italian Game" required />
          </div>
          <button class="btn" type="submit">Add opening</button>
        </form>
        <div id="opening-list"></div>
      </aside>
      <section class="card" id="detail">
        <p class="empty">Select or create an opening to add chapters.</p>
      </section>
    </div>
  </div>
  <script src="/wood/assets/opening-chrome.js?v=4"></script>
  <script src="/wood/assets/chess-board.js"></script>
  <script src="/wood/assets/academy-openings.js?v=4"></script>
</body>
</html>
