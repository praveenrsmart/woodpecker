<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <title>Students — Chess Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/wood/assets/opening.css" />
  <style>
    .create-panel {
      margin-bottom: 20px;
      padding: 22px;
      background: #eef2ff;
      border: 1px solid #c7d2fe;
      border-radius: 20px;
    }
    .create-panel h2 {
      margin: 0 0 6px;
      color: var(--text-bright);
      font-size: 1.15rem;
    }
    .create-panel .hint {
      margin: 0 0 16px;
      color: #475569;
      font-size: 14px;
      line-height: 1.5;
    }
    .create-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: stretch;
    }
    .create-grid input,
    .create-grid select {
      flex: 1;
      min-width: 160px;
      padding: 12px 16px;
      border: 2px solid #e9d5ff;
      border-radius: 12px;
      font: inherit;
      background: #fff;
    }
    .create-grid .btn { flex: 0 0 auto; min-width: 180px; }
    .create-error {
      display: none;
      flex: 1 1 100%;
      color: #b91c1c;
      font-weight: 700;
      margin-top: 4px;
    }
    .summary-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin: 0 0 20px;
    }
    @media (max-width: 900px) {
      .summary-row { grid-template-columns: 1fr 1fr; }
    }
    .summary-card {
      background: #fffffff2;
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 16px 18px;
      box-shadow: var(--shadow-sm);
    }
    .summary-label {
      display: block;
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--text-muted);
      margin-bottom: 6px;
    }
    .summary-value {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--text-bright);
    }
    .coach-panel {
      margin-bottom: 20px;
      padding: 18px 20px;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 16px;
    }
    .coach-panel h2 {
      margin: 0 0 8px;
      font-size: 1.05rem;
      color: var(--text-bright);
    }
    .coach-panel .hint { margin: 0 0 12px; font-size: 14px; color: #64748b; }
    .coach-form {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .coach-form input {
      flex: 1;
      min-width: 200px;
      padding: 12px 16px;
      border: 2px solid #e9d5ff;
      border-radius: 12px;
      font: inherit;
    }
    .add-coach-link {
      margin-top: 10px;
      background: none;
      border: 0;
      color: var(--accent);
      font: inherit;
      font-weight: 800;
      cursor: pointer;
      padding: 0;
    }
    .coach-list {
      margin-top: 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .coach-pill {
      padding: 6px 12px;
      border-radius: 999px;
      background: #ede9fe;
      color: #4c1d95;
      font-size: 13px;
      font-weight: 700;
    }
    .filters {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 14px;
      align-items: center;
    }
    .filters input,
    .filters select {
      flex: 1;
      min-width: 160px;
      padding: 10px 14px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font: inherit;
      background: #fff;
    }
    .chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .student-table { width: 100%; border-collapse: collapse; }
    .student-table th,
    .student-table td {
      text-align: left;
      padding: 12px 10px;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }
    .student-table th {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--text-muted);
    }
    .student-table .muted { color: #64748b; font-size: 13px; }
    .student-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    .student-actions button,
    .student-actions select,
    .student-actions a.btn-link {
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: #fff;
      font: inherit;
      font-weight: 700;
      font-size: 13px;
      cursor: pointer;
      color: var(--text-bright);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .student-actions a.btn-link {
      background: linear-gradient(135deg, #6366f1, #a855f7 50%, #ec4899);
      color: #fff;
      border: 0;
    }
    .student-actions button.danger {
      border-color: #fecaca;
      color: #b91c1c;
      background: #fef2f2;
    }
    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
    }
    .badge.on { background: #d1fae5; color: #065f46; }
    .badge.off { background: #fee2e2; color: #991b1b; }
    .counts { margin-bottom: 10px; color: #64748b; font-size: 14px; }
    .modal-bg {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,.45);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal {
      width: min(440px, 100%);
      background: #fff;
      border-radius: 20px;
      padding: 28px;
      box-shadow: 0 20px 50px rgba(15,23,42,.25);
      color: #1e1b4b;
    }
    .modal h2 { margin: 0 0 8px; font-size: 22px; }
    .modal p { margin: 0 0 10px; line-height: 1.5; color: #475569; }
    .modal .btn { width: 100%; margin-top: 10px; }
    @media (max-width: 720px) {
      .student-table thead { display: none; }
      .student-table tr {
        display: block;
        margin-bottom: 14px;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px;
        background: #fff;
      }
      .student-table td {
        display: block;
        border: 0;
        padding: 6px 4px;
      }
    }
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
      <h1 id="academy-title">Academy dashboard</h1>
      <p class="sub">Create a login for each player, assign coaches, and track Woodpecker progress.</p>
      <p class="counts" id="academy-meta"></p>
    </div>

    <div class="summary-row" id="summary-row">
      <div class="summary-card">
        <span class="summary-label">Total Students Added</span>
        <span class="summary-value" id="sum-total">0</span>
      </div>
      <div class="summary-card">
        <span class="summary-label">Active</span>
        <span class="summary-value" id="sum-active">0</span>
      </div>
      <div class="summary-card">
        <span class="summary-label">Inactive</span>
        <span class="summary-value" id="sum-inactive">0</span>
      </div>
      <div class="summary-card">
        <span class="summary-label">Showing Now</span>
        <span class="summary-value" id="sum-showing">0</span>
      </div>
    </div>

    <section class="coach-panel">
      <h2>Coaches</h2>
      <p class="hint">Add coaches first, then assign a coach when you create or edit a student.</p>
      <form id="add-coach" class="coach-form">
        <input id="coach-name" type="text" placeholder="Coach name (e.g. GM Smith)" required />
        <button class="btn" type="submit" id="coach-btn">Add Coach</button>
        <div class="create-error" id="coach-error"></div>
      </form>
      <div class="coach-list" id="coach-list"></div>
    </section>

    <section class="card create-panel">
      <h2>Create a student login</h2>
      <p class="hint">Create the account here, then give the username and password to the player. If they leave, mark them inactive or remove them to block login.</p>
      <form id="create-student" class="create-grid">
        <input id="student-name" type="text" placeholder="Student name" required />
        <input id="student-username" type="text" placeholder="Username" required autocomplete="off" />
        <input id="student-password" type="password" placeholder="Password (min 4 characters)" required minlength="4" autocomplete="new-password" />
        <select id="student-coach" aria-label="Coach">
          <option value="">Unassigned</option>
        </select>
        <button class="btn" type="submit" id="create-btn">Create student login</button>
        <div class="create-error" id="create-error"></div>
      </form>
    </section>

    <section class="card">
      <div class="filters">
        <input id="search" type="search" placeholder="Search name, username, or ID" />
        <select id="filter-coach" aria-label="Filter by coach">
          <option value="">All coaches</option>
        </select>
        <div class="chip-row">
          <button type="button" class="chip" data-status="active">Active</button>
          <button type="button" class="chip" data-status="inactive">Inactive</button>
          <button type="button" class="chip on" data-status="all">All</button>
        </div>
      </div>
      <div id="student-list">
        <p class="empty">Loading students…</p>
      </div>
    </section>
  </div>

  <div id="modal-root"></div>
  <script src="/wood/assets/opening-chrome.js?v=3"></script>
  <script src="/wood/assets/academy-students.js?v=4"></script>
</body>
</html>
