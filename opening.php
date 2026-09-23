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
</head>
<body class="opening-app">
  <header class="wp-header">
    <a class="wp-logo" id="logo" href="/wood/home">
      <span class="wp-logo-icon">♟️</span>
      <span class="wp-logo-text">Chess <em>Training</em></span>
    </a>
    <nav class="wp-nav">
      <a id="nav-home" href="/wood/home">Home</a>
      <a class="on" id="nav-opening" href="/wood/opening.php">Opening</a>
      <a id="nav-wood" href="/wood/">Woodpecker</a>
      <a id="nav-endgame" href="/wood/endgame.php">Endgame</a>
      <button class="logout" type="button" id="logout">Log out</button>
    </nav>
  </header>
  <div class="wrap">
    <div class="hero">
      <a id="back" href="/wood/home">← Modules</a>
      <h1>Opening</h1>
      <p class="sub">Study the openings your academy assigned, then take a test. White openings: you move White and Black replies from the chapter.</p>
    </div>
    <div id="app"><p class="empty">Loading openings...</p></div>
  </div>
  <script src="/wood/assets/opening-chrome.js"></script>
  <script src="/wood/assets/opening-student.js"></script>
</body>
</html>
