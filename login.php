<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <meta name="googlebot" content="noindex, nofollow" />
  <title>Sign in — Chess Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --accent: #6366f1;
      --accent-2: #ec4899;
      --text: #475569;
      --text-bright: #1e1b4b;
      --border: #e9d5ff;
      --danger: #ef4444;
      --radius: 12px;
      --radius-lg: 20px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Plus Jakarta Sans", system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      color: var(--text);
      background:
        radial-gradient(circle at 8% 12%, rgba(167,139,250,.45) 0%, transparent 42%),
        radial-gradient(circle at 92% 8%, rgba(34,211,238,.35) 0%, transparent 38%),
        linear-gradient(155deg, #faf5ff, #eff6ff 45%, #fff7ed);
    }
    .card {
      width: 100%;
      max-width: 440px;
      background: #fffffff2;
      border-radius: var(--radius-lg);
      padding: 40px 36px;
      box-shadow: 0 16px 40px rgba(99,102,241,.2);
      position: relative;
    }
    .card::before {
      content: "";
      position: absolute;
      inset: -2px;
      border-radius: calc(var(--radius-lg) + 2px);
      background: linear-gradient(135deg, #6366f1, #a855f7 50%, #ec4899);
      z-index: -1;
    }
    .logo { font-size: 48px; text-align: center; margin-bottom: 8px; }
    h1 {
      text-align: center;
      font-size: 1.5rem;
      background: linear-gradient(135deg, #6366f1, #ec4899);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 8px;
    }
    .intro {
      text-align: center;
      font-size: 0.9rem;
      line-height: 1.55;
      margin-bottom: 20px;
    }
    .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
    .tab {
      flex: 1;
      width: auto;
      padding: 10px 8px;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      background: #fff;
      color: var(--text);
      font: inherit;
      font-weight: 800;
      font-size: 13px;
      cursor: pointer;
      box-shadow: none;
    }
    .tab.active {
      border-color: var(--accent);
      color: #fff;
      background: linear-gradient(135deg, #6366f1, #a855f7 50%, #ec4899);
    }
    label {
      display: block;
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--accent);
      font-weight: 800;
      margin-bottom: 6px;
    }
    .field { margin-bottom: 16px; }
    input {
      width: 100%;
      padding: 13px 16px;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      font: inherit;
      font-size: 15px;
      color: var(--text-bright);
    }
    input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(99,102,241,.12);
    }
    .submit {
      width: 100%;
      margin-top: 8px;
      padding: 14px;
      border: none;
      border-radius: var(--radius);
      font: inherit;
      font-weight: 800;
      font-size: 15px;
      color: #fff;
      cursor: pointer;
      background: linear-gradient(135deg, #6366f1, #a855f7 50%, #ec4899);
      box-shadow: 0 6px 20px rgba(99,102,241,.4);
    }
    .submit:disabled { opacity: 0.65; cursor: not-allowed; }
    .msg {
      margin-bottom: 16px;
      padding: 12px 14px;
      border-radius: var(--radius);
      font-size: 14px;
      font-weight: 600;
      display: none;
    }
    .msg.error { display: block; background: rgba(239,68,68,.1); color: var(--danger); }
    .links {
      margin-top: 22px;
      text-align: center;
      font-size: 13px;
      line-height: 1.7;
    }
    .links a { color: var(--accent); font-weight: 800; text-decoration: none; }
    .links a:hover { color: var(--accent-2); }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo" id="logo">🪶</div>
    <h1>Chess Training</h1>
    <p class="intro" id="intro">Choose Student, Academy, or Admin, then sign in.</p>

    <div class="tabs">
      <button type="button" class="tab" data-role="student">Student</button>
      <button type="button" class="tab" data-role="academy">Academy</button>
      <button type="button" class="tab" data-role="admin">Admin</button>
    </div>

    <div id="message" class="msg" role="alert"></div>

    <form id="login-form">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" required />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required />
      </div>
      <button class="submit" type="submit" id="submit-btn">Sign in</button>
    </form>

    <p class="links" id="links"></p>
  </div>

  <script>
    const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
    const API = BASE + "/api";
    const params = new URLSearchParams(location.search);
    const path = (location.pathname || "").replace(/\/+$/, "");
    let role = params.get("role") || "";
    if (role !== "student" && role !== "academy" && role !== "admin") {
      role = /academy\/login$/.test(path) ? "academy" : "student";
    }

    const copy = {
      student: {
        logo: "🪶",
        intro: "Student login — continue your chess training.",
        endpoint: "/auth/login",
        forgot: "/forgot-password?role=student",
      },
      academy: {
        logo: "🏫",
        intro: "Academy login — create student logins and track training.",
        endpoint: "/academy/login",
        forgot: "/forgot-password?role=academy",
      },
      admin: {
        logo: "🛡️",
        intro: "Super admin login — create academy accounts.",
        endpoint: "/admin/login",
        forgot: "/forgot-password?role=admin",
      },
    };

    const intro = document.getElementById("intro");
    const logo = document.getElementById("logo");
    const links = document.getElementById("links");
    const message = document.getElementById("message");
    const submitBtn = document.getElementById("submit-btn");

    function applyRole() {
      const cfg = copy[role];
      logo.textContent = cfg.logo;
      intro.textContent = cfg.intro;
      document.querySelectorAll(".tab").forEach((btn) => {
        btn.classList.toggle("active", btn.getAttribute("data-role") === role);
      });
      links.innerHTML = '<a href="' + BASE + cfg.forgot + '">Forgot password?</a>';
      history.replaceState({}, "", BASE + "/login.php?role=" + role);
    }

    document.querySelectorAll(".tab").forEach((btn) => {
      btn.addEventListener("click", () => {
        role = btn.getAttribute("data-role");
        message.className = "msg";
        applyRole();
      });
    });

    applyRole();

    function clearAppSession() {
      localStorage.removeItem("woodpecker_token");
      localStorage.removeItem("woodpecker_role");
      localStorage.removeItem("woodpecker_student");
      localStorage.removeItem("woodpecker_academy");
      sessionStorage.removeItem("woodpecker_admin_token");
    }

    document.getElementById("login-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      message.className = "msg";
      submitBtn.disabled = true;
      submitBtn.textContent = "Signing in...";
      try {
        const res = await fetch(API + copy[role].endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            username: document.getElementById("username").value.trim(),
            password: document.getElementById("password").value,
          }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || "Invalid username or password");
        clearAppSession();
        if (role === "admin") {
          sessionStorage.setItem("woodpecker_admin_token", data.token);
          window.location.href = BASE + "/admin";
          return;
        }
        localStorage.setItem("woodpecker_token", data.token);
        if (role === "academy") {
          localStorage.setItem("woodpecker_role", "academy");
          localStorage.setItem("woodpecker_academy", JSON.stringify(data.academy));
          window.location.href = BASE + "/academy.php";
          return;
        }
        localStorage.setItem("woodpecker_role", "student");
        localStorage.setItem("woodpecker_student", JSON.stringify(data.student));
        window.location.href = BASE + "/home";
      } catch (err) {
        message.textContent = err.message || "Could not sign in";
        message.className = "msg error";
        submitBtn.disabled = false;
        submitBtn.textContent = "Sign in";
      }
    });
  </script>
</body>
</html>
