<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Endgame library — Academy</title>
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
      <a href="/wood/academy/openings">♟️ Opening library</a>
      <a class="on" href="/wood/academy/endgames">♔ Endgame library</a>
      <button class="logout" type="button" id="logout">Log out</button>
    </nav>
  </header>
  <div class="wrap">
    <div class="hero">
      <a id="back" href="/wood/academy.php">← Students</a>
      <h1>Endgame library</h1>
      <p class="sub">Create categories and subcategories, set positions on the board editor, choose the result, then assign them to players.</p>
    </div>
    <div class="grid-2">
      <aside class="card library-side">
        <form id="create-category">
          <div class="field">
            <label>New category</label>
            <input id="category-name" placeholder="King and pawn" required />
          </div>
          <button class="btn" type="submit">Add category</button>
        </form>
        <div id="category-list"></div>
      </aside>
      <section class="card" id="detail">
        <p class="empty">Select or create a category to add endgame positions.</p>
      </section>
    </div>
  </div>
  <script src="/wood/assets/opening-chrome.js?v=4"></script>
  <script src="/wood/assets/chess-board.js"></script>
  <script src="/wood/assets/academy-endgames.js?v=4"></script>
</body>
</html>
