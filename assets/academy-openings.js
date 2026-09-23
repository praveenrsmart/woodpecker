(function () {
  const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  const API = BASE + "/api";
  const token = localStorage.getItem("woodpecker_token") || "";
  const role = localStorage.getItem("woodpecker_role");
  if (!token || role !== "academy") {
    location.replace(BASE + "/login.php?role=academy");
    return;
  }
  document.getElementById("back").setAttribute("href", BASE + "/academy.php");

  let group = "white";
  let openings = [];
  let students = [];
  let selectedId = null;
  let previewBoard = null;

  const headers = {
    "Content-Type": "application/json",
    Authorization: "Bearer " + token,
  };

  async function api(path, options) {
    const res = await fetch(API + path, Object.assign({ headers }, options || {}));
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Request failed");
    return data;
  }

  function esc(v) {
    return String(v ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function selected() {
    return openings.find((o) => o.id === selectedId) || null;
  }

  async function load() {
    const [openData, studentData] = await Promise.all([
      api("/academy/openings"),
      api("/academy/students?status=active"),
    ]);
    openings = openData.openings || [];
    students = studentData.students || [];
    if (selectedId && !openings.some((o) => o.id === selectedId)) selectedId = null;
    renderList();
    renderDetail();
  }

  function renderList() {
    const box = document.getElementById("opening-list");
    const items = openings.filter((o) => o.colorGroup === group);
    if (!items.length) {
      box.innerHTML = '<p class="empty">No ' + group + " openings yet.</p>";
      return;
    }
    box.innerHTML = items
      .map(
        (o) =>
          `<button type="button" class="list-item ${o.id === selectedId ? "active" : ""}" data-id="${o.id}">
            <strong>${esc(o.name)}</strong>
            <small>${o.chapterCount} chapter${o.chapterCount === 1 ? "" : "s"} · ${o.assignedCount} assigned</small>
          </button>`
      )
      .join("");
  }

  function renderDetail() {
    const el = document.getElementById("detail");
    const opening = selected();
    if (!opening) {
      el.innerHTML = '<p class="empty">Select or create an opening to add chapters.</p>';
      previewBoard = null;
      return;
    }
    const assignedIds = new Set((opening.assignedStudents || []).map((s) => String(s.id)));
    el.innerHTML = `
      <div class="row" style="justify-content:space-between;margin-bottom:12px">
        <div>
          <h2 style="margin:0;color:#1e1b4b">${esc(opening.name)}</h2>
          <p class="sub">${opening.colorGroup === "white" ? "White repertoire" : "Black repertoire"}</p>
        </div>
        <button class="btn danger" id="delete-opening" type="button">Delete opening</button>
      </div>
      <div class="field">
        <label>Notes</label>
        <textarea id="opening-notes">${esc(opening.notes)}</textarea>
      </div>
      <button class="btn ghost" id="save-notes" type="button">Save notes</button>

      <h3 style="margin:22px 0 8px;color:#1e1b4b">Chapters</h3>
      <p class="sub">Paste a PGN for each chapter. Players will study these moves, then take a test as ${opening.colorGroup}.</p>
      <div id="chapters">${(opening.chapters || [])
        .map(
          (c) => `<div class="chapter" data-chapter="${c.id}">
            <strong>${esc(c.title)}</strong>
            <small> · ${c.plyCount} moves</small>
            <div class="row" style="margin-top:8px">
              <button class="btn ghost" data-preview="${c.id}" type="button">Preview</button>
              <button class="btn danger" data-del-chapter="${c.id}" type="button">Remove</button>
            </div>
          </div>`
        )
        .join("") || '<p class="empty">No chapters yet.</p>'}
      </div>
      <form id="add-chapter" style="margin-top:12px">
        <div class="field">
          <label>Chapter name</label>
          <input id="chapter-title" placeholder="Main line" />
        </div>
        <div class="field">
          <label>PGN</label>
          <textarea id="chapter-pgn" placeholder="1. e4 e5 2. Nf3 Nc6 3. Bc4 ..." required></textarea>
        </div>
        <div class="row">
          <button class="btn" type="submit">Add chapter</button>
          <span class="msg" id="chapter-msg"></span>
        </div>
      </form>
      <div id="preview" style="margin-top:16px"></div>

      <h3 style="margin:22px 0 8px;color:#1e1b4b">Assign to players</h3>
      <div class="students-box" id="student-box">
        ${students
          .map(
            (s) =>
              `<label class="assign-row"><input type="checkbox" value="${s.id}" ${
                assignedIds.has(String(s.id)) ? "checked" : ""
              }/><span class="assign-name">${esc(s.name)}</span><span class="assign-id">#${s.id}</span></label>`
          )
          .join("") || '<p class="empty">No active students.</p>'}
      </div>
      <button class="btn" id="save-assign" type="button" style="margin-top:10px">Save assignments</button>

      <h3 style="margin:22px 0 8px;color:#1e1b4b">Test records</h3>
      <div id="progress"><p class="empty">Loading...</p></div>
    `;
    loadProgress(opening.id);
  }

  async function loadProgress(id) {
    try {
      const data = await api("/academy/openings/" + id);
      const opening = openings.find((o) => o.id === id);
      if (opening) opening.assignedStudents = data.assignedStudents || [];
      const prog = await api("/academy/openings/" + id + "/progress");
      const box = document.getElementById("progress");
      if (!box) return;
      const rows = prog.progress || [];
      if (!rows.length) {
        box.innerHTML = '<p class="empty">Assign this opening to see student test records.</p>';
        return;
      }
      box.innerHTML = `<table>
        <thead><tr><th>Player</th><th>Test taken</th><th>Chapters</th><th>Passed</th><th>Wrong moves</th><th>Last test</th></tr></thead>
        <tbody>
          ${rows
            .map(
              (r) => `<tr>
                <td>${esc(r.name)}</td>
                <td>${r.testTaken ? "Yes (" + r.testsTaken + ")" : "No"}</td>
                <td>${r.chaptersTested} / ${r.chapterCount}</td>
                <td>${r.chaptersPassed}</td>
                <td>${r.wrongMoves}</td>
                <td>${r.lastTestAt || "—"}</td>
              </tr>`
            )
            .join("")}
        </tbody>
      </table>`;
    } catch (err) {
      const box = document.getElementById("progress");
      if (box) box.innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
    }
  }

  document.querySelectorAll("[data-group]").forEach((btn) => {
    btn.addEventListener("click", () => {
      group = btn.getAttribute("data-group");
      document.querySelectorAll("[data-group]").forEach((b) => b.classList.toggle("on", b === btn));
      selectedId = null;
      renderList();
      renderDetail();
    });
  });

  document.getElementById("create-opening").addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = document.getElementById("opening-name").value.trim();
    try {
      const created = await api("/academy/openings", {
        method: "POST",
        body: JSON.stringify({ name, colorGroup: group }),
      });
      document.getElementById("opening-name").value = "";
      await load();
      selectedId = created.id;
      renderList();
      renderDetail();
    } catch (err) {
      alert(err.message);
    }
  });

  document.getElementById("opening-list").addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-id]");
    if (!btn) return;
    selectedId = Number(btn.getAttribute("data-id"));
    try {
      const full = await api("/academy/openings/" + selectedId);
      openings = openings.map((o) => (o.id === selectedId ? Object.assign(o, full) : o));
    } catch (err) {}
    renderList();
    renderDetail();
  });

  document.getElementById("detail").addEventListener("click", async (e) => {
    const opening = selected();
    if (!opening) return;
    if (e.target.id === "delete-opening") {
      if (!confirm("Delete this opening, its chapters, and test records?")) return;
      await api("/academy/openings/" + opening.id, { method: "DELETE" });
      selectedId = null;
      await load();
      return;
    }
    if (e.target.id === "save-notes") {
      await api("/academy/openings/" + opening.id, {
        method: "PATCH",
        body: JSON.stringify({ notes: document.getElementById("opening-notes").value }),
      });
      await load();
      return;
    }
    if (e.target.id === "save-assign") {
      const ids = Array.from(document.querySelectorAll("#student-box input:checked")).map((i) => Number(i.value));
      await api("/academy/openings/" + opening.id + "/assign", {
        method: "POST",
        body: JSON.stringify({ studentIds: ids, replace: true }),
      });
      await load();
      selectedId = opening.id;
      const full = await api("/academy/openings/" + opening.id);
      openings = openings.map((o) => (o.id === selectedId ? Object.assign(o, full) : o));
      renderList();
      renderDetail();
      return;
    }
    const previewId = e.target.getAttribute("data-preview");
    if (previewId) {
      const chapter = (opening.chapters || []).find((c) => String(c.id) === previewId);
      const box = document.getElementById("preview");
      if (!chapter || !box) return;
      box.innerHTML = `<p class="stat">${esc(chapter.title)}</p><div id="preview-board"></div>`;
      previewBoard = createChessBoard(document.getElementById("preview-board"), {
        orientation: opening.colorGroup,
        interactive: false,
        fen: chapter.startFen,
      });
      let i = 0;
      const timer = setInterval(() => {
        if (!chapter.moves[i]) {
          clearInterval(timer);
          return;
        }
        previewBoard.setFen(chapter.moves[i].fen, { from: chapter.moves[i].from, to: chapter.moves[i].to });
        i += 1;
      }, 700);
      return;
    }
    const delId = e.target.getAttribute("data-del-chapter");
    if (delId) {
      if (!confirm("Remove this chapter?")) return;
      await api("/academy/chapters/" + delId, { method: "DELETE" });
      await load();
      selectedId = opening.id;
      renderList();
      renderDetail();
    }
  });

  document.getElementById("detail").addEventListener("submit", async (e) => {
    if (e.target.id !== "add-chapter") return;
    e.preventDefault();
    const opening = selected();
    const msg = document.getElementById("chapter-msg");
    msg.textContent = "";
    try {
      await api("/academy/openings/" + opening.id + "/chapters", {
        method: "POST",
        body: JSON.stringify({
          title: document.getElementById("chapter-title").value.trim(),
          pgn: document.getElementById("chapter-pgn").value,
        }),
      });
      await load();
      selectedId = opening.id;
      const full = await api("/academy/openings/" + opening.id);
      openings = openings.map((o) => (o.id === selectedId ? Object.assign(o, full) : o));
      renderList();
      renderDetail();
    } catch (err) {
      msg.className = "msg error";
      msg.textContent = err.message;
    }
  });

  load().catch((err) => {
    document.getElementById("detail").innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
  });
})();
