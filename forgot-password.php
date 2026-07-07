<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <meta name="googlebot" content="noindex, nofollow" />
  <title>Reset Password — Woodpecker Method</title>
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
      --success: #059669;
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
      max-width: 420px;
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
      margin-bottom: 24px;
      color: var(--text);
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
    button {
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
    button:disabled { opacity: 0.65; cursor: not-allowed; }
    .msg {
      margin-bottom: 16px;
      padding: 12px 14px;
      border-radius: var(--radius);
      font-size: 14px;
      font-weight: 600;
      display: none;
    }
    .msg.error { display: block; background: rgba(239,68,68,.1); color: var(--danger); }
    .msg.success { display: block; background: rgba(16,185,129,.12); color: var(--success); }
    .links {
      margin-top: 22px;
      text-align: center;
      font-size: 13px;
      line-height: 1.7;
    }
    .links a { color: var(--accent); font-weight: 800; text-decoration: none; }
    .links a:hover { color: var(--accent-2); }
    .hint {
      font-size: 12px;
      color: #7c3aed;
      margin-top: 4px;
      line-height: 1.45;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">🪶</div>
    <h1>Reset Password</h1>
    <p class="intro">Enter your username and Student ID (shown on your home screen after login), then choose a new password.</p>

    <div id="message" class="msg" role="alert"></div>

    <form id="reset-form">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" required placeholder="your_username" />
      </div>
      <div class="field">
        <label for="studentId">Student ID</label>
        <input id="studentId" name="studentId" type="number" min="1" step="1" required placeholder="e.g. 42" />
        <p class="hint">Your numeric ID from the training home page (e.g. #42 → enter 42).</p>
      </div>
      <div class="field">
        <label for="newPassword">New password</label>
        <input id="newPassword" name="newPassword" type="password" autocomplete="new-password" required minlength="4" placeholder="At least 4 characters" />
      </div>
      <div class="field">
        <label for="confirmPassword">Confirm new password</label>
        <input id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password" required minlength="4" />
      </div>
      <button type="submit" id="submit-btn">Update password</button>
    </form>

    <p class="links">
      <a href="/wood/login">← Back to sign in</a><br />
      <a href="/wood/register">Create an account</a>
    </p>
  </div>

  <script>
    const form = document.getElementById("reset-form");
    const message = document.getElementById("message");
    const submitBtn = document.getElementById("submit-btn");

    function showMsg(text, type) {
      message.textContent = text;
      message.className = "msg " + type;
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      message.className = "msg";

      const username = document.getElementById("username").value.trim();
      const studentId = parseInt(document.getElementById("studentId").value, 10);
      const newPassword = document.getElementById("newPassword").value;
      const confirmPassword = document.getElementById("confirmPassword").value;

      if (newPassword !== confirmPassword) {
        showMsg("Passwords do not match.", "error");
        return;
      }
      if (newPassword.length < 4) {
        showMsg("Password must be at least 4 characters.", "error");
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Updating…";

      try {
        const res = await fetch("/wood/api/auth/reset-password", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ username, studentId, newPassword }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
          throw new Error(data.error || "Could not reset password");
        }
        showMsg(data.message || "Password updated. Redirecting to sign in…", "success");
        form.reset();
        setTimeout(() => { window.location.href = "/wood/login"; }, 2000);
      } catch (err) {
        showMsg(err.message || "Something went wrong. Try again.", "error");
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "Update password";
      }
    });
  </script>
</body>
</html>
