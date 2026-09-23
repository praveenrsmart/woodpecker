<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Opening — Chess Training</title>
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
      <a class="on" id="nav-opening" href="/wood/opening">Opening</a>
      <a id="nav-wood" href="/wood/">Woodpecker</a>
      <a id="nav-endgame" href="/wood/endgame">Endgame</a>
      <button class="logout" type="button" id="logout">Log out</button>
    </nav>
  </header>
  <div class="wrap">
    <div class="hero">
      <a id="back" href="/wood/opening">← All openings</a>
      <h1 id="title">Opening</h1>
      <p class="sub" id="subtitle"></p>
      <div class="chapter-nav" id="chapter-nav"></div>
    </div>
    <div id="banner"></div>
    <div class="board-layout">
      <div class="board-area">
        <div class="turn-bar" id="turn-bar">
          <span class="turn-dot white" id="turn-dot"></span>
          <span id="turn-text">Study the line</span>
          <button class="btn ghost" id="flip" type="button" style="margin-left:auto">Flip board</button>
        </div>
        <div id="board"></div>
        <div class="study-controls" id="study-controls" style="display:none">
          <button class="btn ghost" data-nav="start" type="button">Start</button>
          <button class="btn ghost" data-nav="prev" type="button">Prev</button>
          <button class="btn" data-nav="next" type="button">Next</button>
          <button class="btn ghost" data-nav="end" type="button">End</button>
          <button class="btn ghost" id="autoplay" type="button">Autoplay</button>
        </div>
        <div id="next-line"></div>
      </div>
      <aside class="side-panel">
        <div class="card">
          <h2 id="side-title">Moves</h2>
          <div id="moves" class="moves"></div>
        </div>
      </aside>
    </div>
  </div>
  <script src="/wood/assets/opening-chrome.js"></script>
  <script src="/wood/assets/chess-board.js"></script>
  <script src="/wood/assets/opening-board-app.js"></script>
</body>
</html>
