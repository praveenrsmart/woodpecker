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
      <a id="back" href="/wood/home">← Modules</a>
      <h1>Endgame</h1>
      <p class="sub">Practice assigned positions against the engine, then take a clean test. Passed tests turn green so your academy can see what you learned.</p>
    </div>
    <div id="app"><p class="empty">Loading endgames...</p></div>
  </div>
  <script src="/wood/assets/opening-chrome.js?v=3"></script>
  <script src="/wood/assets/endgame-student.js?v=3"></script>
</body>
</html>
