<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Endgame — Chess Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/wood/assets/opening.css" />
  <link rel="stylesheet" href="/wood/assets/opening-pieces.css" />
</head>
<body class="opening-app">
  <header class="wp-header">
    <a class="wp-logo" id="logo" href="/wood/home">
      <span class="wp-logo-icon">♟️</span>
      <span class="wp-logo-text">Chess <em>Training</em></span>
    </a>
    <nav class="wp-nav">
      <a id="nav-home" href="/wood/home">Home</a>
      <a id="nav-opening" href="/wood/opening.php">Opening</a>
      <a id="nav-wood" href="/wood/">Woodpecker</a>
      <a class="on" id="nav-endgame" href="/wood/endgame.php">Endgame</a>
      <button class="logout" type="button" id="logout">Log out</button>
    </nav>
  </header>
  <div class="wrap">
    <div class="hero">
      <a id="back" href="/wood/endgame.php">← All endgames</a>
      <h1 id="title">Endgame</h1>
      <p class="sub" id="subtitle"></p>
      <div class="chapter-nav" id="chapter-nav"></div>
    </div>
    <div id="banner"></div>
    <div class="board-layout">
      <div class="board-area">
        <div class="turn-bar" id="turn-bar">
          <span class="turn-dot white" id="turn-dot"></span>
          <span id="turn-text">Play the position</span>
          <button class="btn ghost" id="flip" type="button" style="margin-left:auto">Flip board</button>
        </div>
        <div id="board"></div>
        <div class="study-controls" id="play-controls">
          <button class="btn ghost" id="restart" type="button">Restart</button>
        </div>
        <div id="next-line" class="next-line"></div>
      </div>
      <aside class="side-panel">
        <div class="card">
          <h2 id="side-title">Engine</h2>
          <div class="field">
            <label>Strength</label>
            <select id="level"></select>
          </div>
          <p class="meta" id="goal-text"></p>
          <div id="moves" class="moves"></div>
        </div>
      </aside>
    </div>
  </div>
  <script src="/wood/assets/opening-chrome.js?v=3"></script>
  <script src="/wood/assets/chess-board.js"></script>
  <script src="/wood/assets/endgame-play.js?v=3"></script>
</body>
</html>
