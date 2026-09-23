<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />
  <meta name="googlebot" content="noindex, nofollow" />
  <title>Super Admin — Chess Training</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
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
      color: var(--text);
      background:
        radial-gradient(circle at 8% 12%, rgba(167,139,250,.45) 0%, transparent 42%),
        radial-gradient(circle at 92% 8%, rgba(34,211,238,.35) 0%, transparent 38%),
        linear-gradient(155deg, #faf5ff, #eff6ff 45%, #fff7ed);
    }
    .wrap { max-width: 980px; margin: 0 auto; padding: 32px 20px 64px; }
    .card {
      background: #fffffff2;
      border-radius: var(--radius-lg);
      padding: 32px;
      box-shadow: 0 16px 40px rgba(99,102,241,.18);
    }
    .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .login-card { width: 100%; max-width: 420px; padding: 40px 36px; position: relative; }
    .login-card::before {
      content: "";
      position: absolute;
      inset: -2px;
      border-radius: calc(var(--radius-lg) + 2px);
      background: linear-gradient(135deg, #6366f1, #a855f7 50%, #ec4899);
      z-index: -1;
    }
    .logo { font-size: 42px; text-align: center; margin-bottom: 8px; }
    h1 {
      font-size: 1.5rem;
      background: linear-gradient(135deg, #6366f1, #ec4899);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .center { text-align: center; }
    .intro { font-size: 0.92rem; line-height: 1.55; margin: 8px 0 24px; }
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
    input, select {
      width: 100%;
      padding: 12px 14px;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      font: inherit;
      font-size: 15px;
      color: var(--text-bright);
      background: #fff;
    }
    input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
    button {
      border: none;
      border-radius: var(--radius);
      font: inherit;
      font-weight: 800;
      cursor: pointer;
    }
    .btn {
      padding: 12px 16px;
      color: #fff;
      background: linear-gradient(135deg, #6366f1, #a855f7 50%, #ec4899);
      box-shadow: 0 6px 20px rgba(99,102,241,.35);
    }
    .btn:disabled { opacity: 0.65; cursor: not-allowed; }
    .btn-full { width: 100%; margin-top: 8px; padding: 14px; }
    .btn-ghost {
      background: #fff;
      color: var(--accent);
      border: 2px solid var(--border);
      box-shadow: none;
      padding: 8px 12px;
      font-size: 13px;
    }
    .btn-danger { background: #fff; color: var(--danger); border: 2px solid #fecaca; box-shadow: none; padding: 8px 12px; font-size: 13px; }
    .btn-ok { background: #fff; color: var(--success); border: 2px solid #a7f3d0; box-shadow: none; padding: 8px 12px; font-size: 13px; }
    .top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 24px;
    }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 720px) { .grid, .top { grid-template-columns: 1fr; display: block; } .top > * { margin-bottom: 12px; } }
    .create { margin-bottom: 28px; padding: 20px; border: 1px solid var(--border); border-radius: var(--radius-lg); background: #fafafe; }
    .msg { display: none; margin-bottom: 16px; padding: 12px 14px; border-radius: var(--radius); font-size: 14px; font-weight: 600; }
    .msg.error { display: block; background: rgba(239,68,68,.1); color: var(--danger); }
    .msg.success { display: block; background: rgba(16,185,129,.12); color: var(--success); }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; padding: 10px 8px; border-bottom: 1px solid var(--border); }
    td { padding: 12px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .badge { display: inline-block; font-size: 12px; font-weight: 800; padding: 3px 8px; border-radius: 999px; }
    .on { background: #d1fae5; color: #047857; }
    .off { background: #fee2e2; color: #b91c1c; }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .creds { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: var(--radius); padding: 14px; margin: 0 0 18px; }
    .creds code { font-weight: 800; color: var(--text-bright); }
    .empty { color: #64748b; padding: 24px 0; text-align: center; }
    .links { margin-top: 18px; text-align: center; font-size: 13px; }
    .links a { color: var(--accent); font-weight: 800; text-decoration: none; }
    h2 { font-size: 1.05rem; color: var(--text-bright); margin: 28px 0 12px; }
  </style>
</head>
<body>
  <div id="app"></div>
  <script>
    const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
    const API = BASE + "/api";
    const TOKEN_KEY = "woodpecker_admin_token";

    const app = document.getElementById("app");
    let token = sessionStorage.getItem(TOKEN_KEY) || "";
    let adminUser = null;
    let academies = [];
    let admins = [];
    let notice = "";
    let noticeType = "success";
    let createdCreds = null;

    function setNotice(text, type) {
      notice = text || "";
      noticeType = type || "success";
    }

    async function api(path, opts = {}) {
      const headers = { "Content-Type": "application/json", ...(opts.headers || {}) };
      if (token) headers.Authorization = "Bearer " + token;
      const res = await fetch(API + path, { ...opts, headers });
      const data = await res.json().catch(() => ({}));
      if (res.status === 401) {
        token = "";
        sessionStorage.removeItem(TOKEN_KEY);
        window.location.replace(BASE + "/login.php?role=admin");
        throw new Error(data.error || "Please sign in again");
      }
      if (!res.ok) throw new Error(data.error || "Request failed");
      return data;
    }

    function render() {
      if (!token) {
        window.location.replace(BASE + "/login.php?role=admin");
        return;
      }

      const rows = academies.map((a) => {
        const active = Number(a.is_active) !== 0;
        return `<tr>
          <td><strong>${escapeHtml(a.name)}</strong><br /><span>@${escapeHtml(a.username)}</span></td>
          <td>${a.student_count || 0} total<br />${a.active_students || 0} active</td>
          <td><span class="badge ${active ? "on" : "off"}">${active ? "Active" : "Disabled"}</span></td>
          <td class="actions">
            <button class="btn-ghost" data-reset="${a.id}" data-name="${escapeHtml(a.name)}">Set password</button>
            <button class="${active ? "btn-danger" : "btn-ok"}" data-toggle="${a.id}" data-active="${active ? "1" : "0"}" data-name="${escapeHtml(a.name)}">
              ${active ? "Disable" : "Enable"}
            </button>
          </td>
        </tr>`;
      }).join("");

      app.innerHTML = `
        <div class="wrap">
          <div class="card">
            <div class="top">
              <div>
                <h1>Super Admin</h1>
                <p class="intro">Your Admin ID: <strong>#${adminUser && adminUser.id ? adminUser.id : "—"}</strong> · username <strong>@${escapeHtml((adminUser && adminUser.username) || "")}</strong><br />Use this ID with Forgot password. Create academy logins below, and reset an academy password anytime.</p>
              </div>
              <button class="btn-ghost" id="logout-btn">Log out</button>
            </div>
            <div id="message" class="msg"></div>
            ${createdCreds ? `<div class="creds">
              Give these details to the academy, then they can create student logins.<br />
              Academy: <code>${escapeHtml(createdCreds.name)}</code><br />
              Username: <code>${escapeHtml(createdCreds.username)}</code><br />
              Password: <code>${escapeHtml(createdCreds.password)}</code>
            </div>` : ""}
            <form class="create" id="create-form">
              <div class="grid">
                <div class="field">
                  <label>Academy name</label>
                  <input name="name" required placeholder="Chess Academy Mumbai" />
                </div>
                <div class="field">
                  <label>Username</label>
                  <input name="username" required placeholder="mumbai_academy" />
                </div>
                <div class="field">
                  <label>Password</label>
                  <input name="password" required minlength="4" placeholder="At least 4 characters" />
                </div>
                <div class="field" style="display:flex;align-items:flex-end">
                  <button class="btn btn-full" type="submit" id="create-btn">Create academy login</button>
                </div>
              </div>
            </form>
            <h2>Your password</h2>
            <form class="create" id="change-pass-form">
              <div class="grid">
                <div class="field">
                  <label>Current password</label>
                  <input name="currentPassword" type="password" required />
                </div>
                <div class="field">
                  <label>New password</label>
                  <input name="newPassword" type="password" required minlength="4" />
                </div>
                <div class="field" style="display:flex;align-items:flex-end">
                  <button class="btn btn-full" type="submit" id="change-pass-btn">Update my password</button>
                </div>
              </div>
            </form>
            <h2>Add another super admin</h2>
            <form class="create" id="create-admin-form">
              <div class="grid">
                <div class="field">
                  <label>Name</label>
                  <input name="name" placeholder="Super Admin" />
                </div>
                <div class="field">
                  <label>Username</label>
                  <input name="username" required placeholder="admin2" />
                </div>
                <div class="field">
                  <label>Password</label>
                  <input name="password" required minlength="4" />
                </div>
                <div class="field" style="display:flex;align-items:flex-end">
                  <button class="btn btn-full" type="submit" id="create-admin-btn">Create super admin</button>
                </div>
              </div>
            </form>
            ${admins.length ? `<table>
              <thead><tr><th>Super admins</th><th>Username</th><th>Admin ID</th></tr></thead>
              <tbody>${admins.map((p) => `<tr>
                <td>${escapeHtml(p.name || "Super Admin")}</td>
                <td>@${escapeHtml(p.username)}</td>
                <td>#${p.id}</td>
              </tr>`).join("")}</tbody>
            </table>` : ""}
            <h2>Academies</h2>
            ${academies.length ? `<table>
              <thead><tr><th>Academy</th><th>Students</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>${rows}</tbody>
            </table>` : `<p class="empty">No academies yet. Create the first academy login above.</p>`}
          </div>
        </div>`;

      const msg = document.getElementById("message");
      if (notice) {
        msg.className = "msg " + noticeType;
        msg.textContent = notice;
      }
      document.getElementById("logout-btn").addEventListener("click", () => {
        token = "";
        sessionStorage.removeItem(TOKEN_KEY);
        adminUser = null;
        academies = [];
        admins = [];
        createdCreds = null;
        setNotice("");
        render();
      });
      document.getElementById("create-form").addEventListener("submit", onCreate);
      document.getElementById("change-pass-form").addEventListener("submit", onChangePassword);
      document.getElementById("create-admin-form").addEventListener("submit", onCreateAdmin);
      app.querySelectorAll("[data-reset]").forEach((btn) => btn.addEventListener("click", onReset));
      app.querySelectorAll("[data-toggle]").forEach((btn) => btn.addEventListener("click", onToggle));
    }

    function escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    async function onLogin(e) {
      e.preventDefault();
      const btn = document.getElementById("login-btn");
      btn.disabled = true;
      try {
        const data = await api("/admin/login", {
          method: "POST",
          body: JSON.stringify({
            username: document.getElementById("username").value.trim(),
            password: document.getElementById("password").value,
          }),
        });
        token = data.token;
        sessionStorage.setItem(TOKEN_KEY, token);
        adminUser = data.admin || null;
        setNotice("");
        await loadDashboard();
      } catch (err) {
        setNotice(err.message, "error");
        render();
      } finally {
        btn.disabled = false;
      }
    }

    async function loadDashboard() {
      const [me, acad, people] = await Promise.all([
        api("/admin/me"),
        api("/admin/academies"),
        api("/admin/admins"),
      ]);
      adminUser = me.admin || adminUser;
      academies = acad.academies || [];
      admins = people.admins || [];
      render();
    }

    async function onCreate(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById("create-btn");
      const payload = {
        name: form.name.value.trim(),
        username: form.username.value.trim(),
        password: form.password.value,
      };
      btn.disabled = true;
      try {
        const data = await api("/admin/academies", { method: "POST", body: JSON.stringify(payload) });
        createdCreds = {
          name: payload.name,
          username: data.username,
          password: payload.password,
        };
        setNotice(data.message || "Academy login created.", "success");
        form.reset();
        await loadDashboard();
      } catch (err) {
        setNotice(err.message, "error");
        render();
      } finally {
        btn.disabled = false;
      }
    }

    async function onChangePassword(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById("change-pass-btn");
      btn.disabled = true;
      try {
        await api("/admin/change-password", {
          method: "POST",
          body: JSON.stringify({
            currentPassword: form.currentPassword.value,
            newPassword: form.newPassword.value,
          }),
        });
        setNotice("Your password was updated.", "success");
        form.reset();
        render();
      } catch (err) {
        setNotice(err.message, "error");
        render();
      } finally {
        btn.disabled = false;
      }
    }

    async function onCreateAdmin(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById("create-admin-btn");
      const payload = {
        name: form.name.value.trim() || "Super Admin",
        username: form.username.value.trim(),
        password: form.password.value,
      };
      btn.disabled = true;
      try {
        const data = await api("/admin/admins", { method: "POST", body: JSON.stringify(payload) });
        createdCreds = {
          name: payload.name + " (super admin)",
          username: data.username,
          password: payload.password,
        };
        setNotice(data.message || "Super admin created.", "success");
        form.reset();
        await loadDashboard();
      } catch (err) {
        setNotice(err.message, "error");
        render();
      } finally {
        btn.disabled = false;
      }
    }

    async function onReset(e) {
      const id = e.currentTarget.getAttribute("data-reset");
      const name = e.currentTarget.getAttribute("data-name");
      const password = prompt("New password for " + name + " (min 4 characters)");
      if (!password) return;
      try {
        await api("/admin/academies/" + id, { method: "PATCH", body: JSON.stringify({ password }) });
        createdCreds = { name, username: "(unchanged)", password };
        setNotice("Password updated. Give the new password to the academy.", "success");
        await loadDashboard();
      } catch (err) {
        setNotice(err.message, "error");
        render();
      }
    }

    async function onToggle(e) {
      const id = e.currentTarget.getAttribute("data-toggle");
      const active = e.currentTarget.getAttribute("data-active") === "1";
      const name = e.currentTarget.getAttribute("data-name");
      if (!confirm((active ? "Disable " : "Enable ") + name + "?")) return;
      try {
        await api("/admin/academies/" + id, { method: "PATCH", body: JSON.stringify({ active: !active }) });
        setNotice(active ? "Academy login disabled." : "Academy login enabled.", "success");
        await loadDashboard();
      } catch (err) {
        setNotice(err.message, "error");
        render();
      }
    }

    (async function boot() {
      if (!token) {
        window.location.replace(BASE + "/login.php?role=admin");
        return;
      }
      try {
        await api("/admin/me");
        await loadDashboard();
      } catch {
        token = "";
        sessionStorage.removeItem(TOKEN_KEY);
        window.location.replace(BASE + "/login.php?role=admin");
      }
    })();
  </script>
</body>
</html>
