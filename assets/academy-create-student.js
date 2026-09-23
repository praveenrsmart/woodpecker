(function () {
  const API = "/wood/api";
  const FORM_FLAG = "data-wp-create-student";

  function isAcademyDashboard() {
    const path = (location.pathname || "").replace(/\/+$/, "");
    return /(^|\/)academy$/.test(path);
  }

  function token() {
    return localStorage.getItem("woodpecker_token") || "";
  }

  function isAcademyRole() {
    return localStorage.getItem("woodpecker_role") === "academy";
  }

  function existingAddForm() {
    return document.querySelector('form input[placeholder="Student ID"]')
      ? document.querySelector('form input[placeholder="Student ID"]').closest("form")
      : null;
  }

  function coachSelect(form) {
    return form ? form.querySelector("select") : null;
  }

  function showCreds(data) {
    let box = document.getElementById("wp-created-student");
    if (!box) {
      box = document.createElement("div");
      box.id = "wp-created-student";
      box.style.cssText = [
        "position:fixed",
        "inset:0",
        "background:rgba(15,23,42,.45)",
        "z-index:9999",
        "display:flex",
        "align-items:center",
        "justify-content:center",
        "padding:20px",
      ].join(";");
      document.body.appendChild(box);
    }
    box.innerHTML = `
      <div style="width:min(440px,100%);background:#fff;border-radius:20px;padding:28px;box-shadow:0 20px 50px rgba(15,23,42,.25);font-family:inherit;color:#1e1b4b">
        <h2 style="margin:0 0 8px;font-size:22px">Student login created</h2>
        <p style="margin:0 0 16px;color:#475569;line-height:1.5">Give these details to the player. They can sign in on the student login page.</p>
        <p style="margin:0 0 8px"><strong>Name:</strong> ${escapeHtml(data.name)}</p>
        <p style="margin:0 0 8px"><strong>Username:</strong> ${escapeHtml(data.username)}</p>
        <p style="margin:0 0 8px"><strong>Password:</strong> ${escapeHtml(data.password)}</p>
        <p style="margin:0 0 18px"><strong>Student ID:</strong> #${escapeHtml(data.id)}</p>
        <button type="button" id="wp-created-done" style="width:100%;padding:12px;border:0;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7 50%,#ec4899);color:#fff;font-weight:800;cursor:pointer">Done — refresh list</button>
      </div>`;
    document.getElementById("wp-created-done").onclick = function () {
      box.remove();
      location.reload();
    };
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function academyEntity() {
    try {
      return JSON.parse(localStorage.getItem("woodpecker_academy") || "null");
    } catch (e) {
      return null;
    }
  }

  function isAcademyLogin() {
    const path = (location.pathname || "").replace(/\/+$/, "");
    return /(^|\/)academy\/login$/.test(path);
  }

  function studentIdFromRow(row) {
    const spans = row.querySelectorAll("span");
    for (let i = 0; i < spans.length; i++) {
      const text = (spans[i].textContent || "").trim();
      if (/^#\d+$/.test(text)) return text.slice(1);
    }
    return null;
  }

  function studentNameFromRow(row) {
    const link = row.querySelector("a");
    if (!link) return "this student";
    const text = (link.childNodes[0] && link.childNodes[0].textContent) || link.textContent || "this student";
    return text.trim();
  }

  function injectAcademyLoginForgot() {
    if (!isAcademyLogin()) return;
    if (document.querySelector("[data-wp-academy-forgot]")) return;
    const links = document.querySelectorAll("a");
    let host = null;
    for (let i = 0; i < links.length; i++) {
      if ((links[i].getAttribute("href") || "").indexOf("/login") !== -1 || (links[i].textContent || "").indexOf("Student") !== -1) {
        host = links[i].parentElement;
        break;
      }
    }
    if (!host) return;
    const sep = document.createTextNode(" · ");
    const a = document.createElement("a");
    a.setAttribute("data-wp-academy-forgot", "1");
    a.href = "/wood/forgot-password?role=academy";
    a.textContent = "Forgot password?";
    host.appendChild(sep);
    host.appendChild(a);
  }

  function injectAcademyTools() {
    if (document.querySelector("[data-wp-academy-tools]")) return;
    const academy = academyEntity();
    if (!academy || !academy.id) return;
    const header = document.querySelector("h1");
    if (!header) return;
    const box = document.createElement("div");
    box.setAttribute("data-wp-academy-tools", "1");
    box.style.cssText = "margin:12px 0 0;color:#e0e7ff;font-size:14px;line-height:1.5";
    box.innerHTML = "Academy ID: <strong>#" + escapeHtml(academy.id) + "</strong> — use this with Forgot password.";
    const actions = document.createElement("div");
    actions.style.cssText = "margin-top:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center";
    const changeBtn = document.createElement("button");
    changeBtn.type = "button";
    changeBtn.textContent = "Change my password";
    changeBtn.style.cssText = "padding:10px 14px;border:0;border-radius:12px;background:#fff;color:#312e81;font-weight:800;cursor:pointer";
    changeBtn.addEventListener("click", async function () {
      const currentPassword = prompt("Current academy password");
      if (!currentPassword) return;
      const newPassword = prompt("New academy password (min 4 characters)");
      if (!newPassword) return;
      try {
        const res = await fetch(API + "/academy/change-password", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token(),
          },
          body: JSON.stringify({ currentPassword, newPassword }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.error || "Could not update password");
        alert("Academy password updated.");
      } catch (err) {
        alert(err.message || "Could not update password");
      }
    });
    const libraryStyle = [
      "padding:11px 16px",
      "border-radius:12px",
      "background:linear-gradient(135deg,#fff,#fef3c7)",
      "color:#1e1b4b",
      "font-weight:800",
      "text-decoration:none",
      "display:inline-flex",
      "align-items:center",
      "gap:8px",
      "box-shadow:0 6px 16px rgba(15,23,42,.22)",
      "border:2px solid #fde68a",
      "min-height:44px",
    ].join(";");
    const openingsLink = document.createElement("a");
    openingsLink.href = "/wood/academy/openings";
    openingsLink.innerHTML = '<span style="font-size:20px;line-height:1">♟️</span><span>Opening library</span>';
    openingsLink.style.cssText = libraryStyle;
    const endgamesLink = document.createElement("a");
    endgamesLink.href = "/wood/academy/endgames";
    endgamesLink.innerHTML = '<span style="font-size:20px;line-height:1">♔</span><span>Endgame library</span>';
    endgamesLink.style.cssText = libraryStyle;
    const homeLink = document.createElement("a");
    homeLink.href = "/wood/academy/home";
    homeLink.innerHTML = '<span style="font-size:20px;line-height:1">🏠</span><span>Academy home</span>';
    homeLink.style.cssText = libraryStyle;
    actions.appendChild(homeLink);
    actions.appendChild(changeBtn);
    actions.appendChild(openingsLink);
    actions.appendChild(endgamesLink);
    box.appendChild(actions);
    header.parentNode.appendChild(box);
  }

  function injectStudentPasswordButtons() {
    const removeButtons = document.querySelectorAll("button");
    for (let i = 0; i < removeButtons.length; i++) {
      const btn = removeButtons[i];
      if ((btn.textContent || "").trim() !== "Remove") continue;
      const actions = btn.parentElement;
      if (!actions || actions.querySelector("[data-wp-set-password]")) continue;
      const row = actions.parentElement;
      const studentId = studentIdFromRow(row);
      if (!studentId) continue;
      const resetBtn = document.createElement("button");
      resetBtn.type = "button";
      resetBtn.setAttribute("data-wp-set-password", studentId);
      resetBtn.textContent = "Set password";
      resetBtn.className = btn.className;
      resetBtn.style.marginRight = "6px";
      resetBtn.addEventListener("click", async function () {
        const name = studentNameFromRow(row);
        const password = prompt("New password for " + name + " (min 4 characters)");
        if (!password) return;
        try {
          const res = await fetch(API + "/academy/students/" + studentId, {
            method: "PATCH",
            headers: {
              "Content-Type": "application/json",
              Authorization: "Bearer " + token(),
            },
            body: JSON.stringify({ password }),
          });
          const data = await res.json().catch(function () { return {}; });
          if (!res.ok) throw new Error(data.error || "Could not set password");
          alert("Password updated. Give the new password to " + name + ".");
        } catch (err) {
          alert(err.message || "Could not set password");
        }
      });
      actions.insertBefore(resetBtn, btn);
    }
  }

  function inject() {
    injectAcademyLoginForgot();
    if (!isAcademyDashboard() || !isAcademyRole()) {
      document.querySelectorAll("[" + FORM_FLAG + "], [data-wp-create-student-hint], [data-wp-academy-tools]").forEach(function (node) {
        node.remove();
      });
      return;
    }
    injectAcademyTools();
    injectStudentPasswordButtons();
    if (document.querySelector("[" + FORM_FLAG + "]")) return;
    const addForm = existingAddForm();
    if (!addForm) return;

    const panel = document.createElement("form");
    panel.setAttribute(FORM_FLAG, "1");
    panel.style.cssText = [
      "display:flex",
      "flex-wrap:wrap",
      "gap:12px",
      "margin-bottom:16px",
      "padding:20px",
      "background:#eef2ff",
      "border:1px solid #c7d2fe",
      "border-radius:20px",
    ].join(";");

    const title = document.createElement("div");
    title.style.cssText = "flex:1 1 100%;font-weight:800;color:#1e1b4b";
    title.textContent = "Create a student login";
    panel.appendChild(title);

    const note = document.createElement("p");
    note.style.cssText = "flex:1 1 100%;margin:0;color:#475569;font-size:14px;line-height:1.45";
    note.textContent = "Create the account here, then give the username and password to the player. If they leave, mark them inactive or remove them to block login.";
    panel.appendChild(note);

    const name = field("text", "Student name");
    const username = field("text", "Username");
    const password = field("password", "Password (min 4 characters)");
    name.required = true;
    username.required = true;
    password.required = true;
    password.minLength = 4;

    const coach = document.createElement("select");
    coach.style.cssText = selectStyle();
    const sourceCoach = coachSelect(addForm);
    if (sourceCoach) {
      coach.innerHTML = sourceCoach.innerHTML;
    } else {
      coach.innerHTML = '<option value="">Unassigned</option>';
    }

    const submit = document.createElement("button");
    submit.type = "submit";
    submit.textContent = "Create student login";
    submit.style.cssText = [
      "padding:12px 16px",
      "border:0",
      "border-radius:12px",
      "color:#fff",
      "font-weight:800",
      "cursor:pointer",
      "background:linear-gradient(135deg,#6366f1,#a855f7 50%,#ec4899)",
    ].join(";");

    const error = document.createElement("div");
    error.style.cssText = "flex:1 1 100%;display:none;color:#b91c1c;font-weight:700";

    panel.appendChild(name);
    panel.appendChild(username);
    panel.appendChild(password);
    panel.appendChild(coach);
    panel.appendChild(submit);
    panel.appendChild(error);

    addForm.parentNode.insertBefore(panel, addForm);

    const hint = document.createElement("div");
    hint.setAttribute("data-wp-create-student-hint", "1");
    hint.style.cssText = "margin:0 0 10px;font-size:13px;font-weight:800;color:#64748b";
    hint.textContent = "Or add an existing student by ID";
    addForm.parentNode.insertBefore(hint, addForm);

    panel.addEventListener("submit", async function (e) {
      e.preventDefault();
      error.style.display = "none";
      submit.disabled = true;
      submit.textContent = "Creating...";
      try {
        const res = await fetch(API + "/academy/students", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token(),
          },
          body: JSON.stringify({
            name: name.value.trim(),
            username: username.value.trim(),
            password: password.value,
            coachName: coach.value,
          }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.error || "Could not create student");
        showCreds({
          name: data.student && data.student.name,
          username: data.username || (data.student && data.student.username),
          password: data.password || password.value,
          id: data.student && data.student.id,
        });
      } catch (err) {
        error.textContent = err.message || "Could not create student";
        error.style.display = "block";
      } finally {
        submit.disabled = false;
        submit.textContent = "Create student login";
      }
    });
  }

  function field(type, placeholder) {
    const input = document.createElement("input");
    input.type = type;
    input.placeholder = placeholder;
    input.style.cssText = [
      "flex:1",
      "min-width:160px",
      "padding:12px 16px",
      "border:2px solid #e9d5ff",
      "border-radius:12px",
      "font:inherit",
    ].join(";");
    return input;
  }

  function selectStyle() {
    return [
      "min-width:160px",
      "padding:12px 16px",
      "border:2px solid #e9d5ff",
      "border-radius:12px",
      "font:inherit",
      "background:#fff",
    ].join(";");
  }

  function shouldWatch() {
    return isAcademyRole() || isAcademyDashboard() || isAcademyLogin();
  }

  let observer = null;
  function ensureObserver() {
    if (observer || !shouldWatch()) {
      return;
    }
    observer = new MutationObserver(inject);
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  function boot() {
    if (!shouldWatch() && !isAcademyLogin()) {
      return;
    }
    ensureObserver();
    inject();
  }

  const pushState = history.pushState;
  history.pushState = function () {
    pushState.apply(this, arguments);
    boot();
  };
  window.addEventListener("popstate", boot);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
