(function () {
  const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  const API = BASE + "/api";
  const STARTPOS = "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
  const EMPTY = "8/8/8/8/8/8/8/8 w - - 0 1";
  const PIECES = [
    ["K", "king white"], ["Q", "queen white"], ["R", "rook white"],
    ["B", "bishop white"], ["N", "knight white"], ["P", "pawn white"],
    ["k", "king black"], ["q", "queen black"], ["r", "rook black"],
    ["b", "bishop black"], ["n", "knight black"], ["p", "pawn black"],
  ];

  const token = localStorage.getItem("woodpecker_token") || "";
  const role = localStorage.getItem("woodpecker_role");
  if (!token || role !== "academy") {
    location.replace(BASE + "/login.php?role=academy");
    return;
  }
  document.getElementById("back").setAttribute("href", BASE + "/academy.php");

  let categories = [];
  let students = [];
  let goals = [];
  let selectedId = null;
  let selectedSubId = null;
  let editor = null;
  let editorFen = EMPTY;
  let brush = "K";

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
    return categories.find((c) => c.id === selectedId) || null;
  }

  function selectedSub(category) {
    return ((category && category.subcategories) || []).find((s) => s.id === selectedSubId) || null;
  }

  async function load() {
    const [endData, studentData] = await Promise.all([
      api("/academy/endgames"),
      api("/academy/students?status=active"),
    ]);
    categories = endData.categories || [];
    goals = endData.goals || [];
    students = studentData.students || [];
    if (selectedId && !categories.some((c) => c.id === selectedId)) {
      selectedId = null;
      selectedSubId = null;
    }
    if (selectedId) {
      try {
        const full = await api("/academy/endgames/" + selectedId);
        categories = categories.map((c) => (c.id === selectedId ? Object.assign(c, full) : c));
      } catch (err) {}
    }
    const cat = selected();
    if (cat && selectedSubId && !selectedSub(cat)) selectedSubId = null;
    if (cat && !selectedSubId && (cat.subcategories || []).length) {
      selectedSubId = cat.subcategories[0].id;
    }
    renderList();
    renderDetail();
  }

  function renderList() {
    const box = document.getElementById("category-list");
    if (!categories.length) {
      box.innerHTML = '<p class="empty">No categories yet.</p>';
      return;
    }
    box.innerHTML = categories
      .map(
        (c) =>
          `<button type="button" class="list-item ${c.id === selectedId ? "active" : ""}" data-id="${c.id}">
            <strong>${esc(c.name)}</strong>
            <small>${c.subcategoryCount} sub · ${c.chapterCount} position${c.chapterCount === 1 ? "" : "s"} · ${c.assignedCount} assigned</small>
          </button>`
      )
      .join("");
  }

  function paletteHtml() {
    return (
      '<div class="palette" id="palette">' +
      PIECES.map(
        ([ch, cls]) =>
          `<button type="button" data-brush="${ch}" class="wp-piece ${cls}${brush === ch ? " on" : ""}" title="${ch}" aria-label="${ch}"></button>`
      ).join("") +
      `<button type="button" data-brush="" class="${brush === "" ? "on" : ""}" title="Erase">✕</button>` +
      "</div>"
    );
  }

  function renderDetail() {
    const el = document.getElementById("detail");
    const category = selected();
    editor = null;
    if (!category) {
      el.innerHTML = '<p class="empty">Select or create a category to add endgame positions.</p>';
      return;
    }
    const sub = selectedSub(category);
    const assignedIds = new Set((category.assignedStudents || []).map((s) => String(s.id)));
    const goalOptions = goals
      .map((g) => `<option value="${esc(g.id)}">${esc(g.label)}</option>`)
      .join("");
    el.innerHTML = `
      <div class="row" style="justify-content:space-between;margin-bottom:12px">
        <div>
          <h2 style="margin:0;color:#1e1b4b">${esc(category.name)}</h2>
          <p class="sub">${category.subcategoryCount} subcategories · ${category.chapterCount} positions</p>
        </div>
        <button class="btn danger" id="delete-category" type="button">Delete category</button>
      </div>
      <div class="field">
        <label>Notes</label>
        <textarea id="category-notes">${esc(category.notes)}</textarea>
      </div>
      <button class="btn ghost" id="save-notes" type="button">Save notes</button>

      <h3 style="margin:22px 0 8px;color:#1e1b4b">Subcategories</h3>
      <div class="chips" id="sub-chips">
        ${(category.subcategories || [])
          .map(
            (s) =>
              `<button class="chip ${s.id === selectedSubId ? "on" : ""}" type="button" data-sub="${s.id}">${esc(s.name)} (${s.chapterCount})</button>`
          )
          .join("") || '<p class="empty">Add a subcategory, then save positions under it.</p>'}
      </div>
      <form id="add-sub" class="row" style="margin-bottom:16px">
        <input id="sub-name" placeholder="New subcategory" style="flex:1;min-width:180px" />
        <button class="btn" type="submit">Add subcategory</button>
      </form>
      ${
        sub
          ? `<div class="row" style="margin-bottom:16px">
              <strong style="color:#1e1b4b">${esc(sub.name)}</strong>
              <button class="btn danger" id="delete-sub" type="button">Remove subcategory</button>
            </div>
            <h3 style="margin:8px 0;color:#1e1b4b">Positions</h3>
            <div id="chapters">${(sub.chapters || [])
              .map(
                (c) => `<div class="chapter" data-chapter="${c.id}">
                  <strong>${esc(c.title)}</strong>
                  <small> · ${esc(c.goalLabel)}</small>
                  <div class="row" style="margin-top:8px">
                    <button class="btn ghost" data-edit-chapter="${c.id}" type="button">Edit on board</button>
                    <button class="btn danger" data-del-chapter="${c.id}" type="button">Remove</button>
                  </div>
                </div>`
              )
              .join("") || '<p class="empty">No positions yet. Set the board and save.</p>'}
            </div>
            <h3 style="margin:22px 0 8px;color:#1e1b4b">Board editor</h3>
            <p class="sub">Place pieces like Lichess, set who moves, choose the result, then save.</p>
            ${paletteHtml()}
            <div class="row" style="margin-bottom:10px">
              <button class="btn ghost" id="startpos" type="button">Start position</button>
              <button class="btn ghost" id="clear-board" type="button">Clear</button>
              <button class="btn ghost" id="flip-editor" type="button">Flip</button>
            </div>
            <div class="row" style="margin-bottom:12px">
              <label class="chip ${editorFen.split(" ")[1] === "w" ? "on" : ""}" id="turn-w"><input type="radio" name="turn" value="w" ${editorFen.split(" ")[1] === "b" ? "" : "checked"} style="display:none"/> White to move</label>
              <label class="chip ${editorFen.split(" ")[1] === "b" ? "on" : ""}" id="turn-b"><input type="radio" name="turn" value="b" ${editorFen.split(" ")[1] === "b" ? "checked" : ""} style="display:none"/> Black to move</label>
            </div>
            <div id="editor-board"></div>
            <form id="save-position" style="margin-top:14px">
              <div class="field">
                <label>Chapter / position name</label>
                <input id="chapter-title" placeholder="Lucena position" />
              </div>
              <div class="field">
                <label>Result</label>
                <select id="chapter-goal">${goalOptions}</select>
              </div>
              <div class="field">
                <label>Notes</label>
                <textarea id="chapter-notes" placeholder="Optional hint for the academy"></textarea>
              </div>
              <input type="hidden" id="edit-chapter-id" value="" />
              <div class="row">
                <button class="btn" type="submit">Save position</button>
                <span class="msg" id="chapter-msg"></span>
              </div>
            </form>`
          : ""
      }

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

      <h3 style="margin:22px 0 8px;color:#1e1b4b">Learned positions</h3>
      <div id="progress"><p class="empty">Loading...</p></div>
    `;
    if (sub) initEditor();
    loadProgress(category.id);
  }

  function initEditor() {
    const root = document.getElementById("editor-board");
    if (!root || typeof createChessBoard !== "function") return;
    editor = createChessBoard(root, {
      fen: editorFen,
      editor: true,
      brush: brush,
      orientation: "white",
      onChange: function (fen) {
        editorFen = fen;
      },
    });
    editor.setBrush(brush);
  }

  function setTurnUi(turn) {
    const w = document.getElementById("turn-w");
    const b = document.getElementById("turn-b");
    if (w) w.classList.toggle("on", turn === "w");
    if (b) b.classList.toggle("on", turn === "b");
    const radioW = document.querySelector('input[name="turn"][value="w"]');
    const radioB = document.querySelector('input[name="turn"][value="b"]');
    if (radioW) radioW.checked = turn === "w";
    if (radioB) radioB.checked = turn === "b";
  }

  async function loadProgress(id) {
    try {
      const prog = await api("/academy/endgames/" + id + "/progress");
      const box = document.getElementById("progress");
      if (!box) return;
      const rows = prog.progress || [];
      if (!rows.length) {
        box.innerHTML = '<p class="empty">Assign this category to see who completed clean tests.</p>';
        return;
      }
      box.innerHTML = `<table>
        <thead><tr><th>Player</th><th>Tested</th><th>Green (clean tests)</th><th>Positions</th></tr></thead>
        <tbody>
          ${rows
            .map(
              (r) => `<tr>
                <td>${esc(r.name)}</td>
                <td>${r.testTaken ? "Yes" : "No"}</td>
                <td><span class="${r.green ? "green" : ""}">${r.green} / ${r.chapterCount}</span></td>
                <td>${r.chaptersPassed} passed · ${r.chaptersTested} tested</td>
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

  document.getElementById("create-category").addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = document.getElementById("category-name").value.trim();
    try {
      const created = await api("/academy/endgames", {
        method: "POST",
        body: JSON.stringify({ name }),
      });
      document.getElementById("category-name").value = "";
      selectedId = created.id;
      selectedSubId = null;
      await load();
    } catch (err) {
      alert(err.message);
    }
  });

  document.getElementById("category-list").addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-id]");
    if (!btn) return;
    selectedId = Number(btn.getAttribute("data-id"));
    selectedSubId = null;
    editorFen = EMPTY;
    await load();
  });

  document.getElementById("detail").addEventListener("click", async (e) => {
    const category = selected();
    if (!category) return;
    const subBtn = e.target.closest("[data-sub]");
    if (subBtn) {
      selectedSubId = Number(subBtn.getAttribute("data-sub"));
      editorFen = EMPTY;
      renderDetail();
      return;
    }
    if (e.target.id === "delete-category") {
      if (!confirm("Delete this category, subcategories, positions, and records?")) return;
      await api("/academy/endgames/" + category.id, { method: "DELETE" });
      selectedId = null;
      selectedSubId = null;
      await load();
      return;
    }
    if (e.target.id === "save-notes") {
      await api("/academy/endgames/" + category.id, {
        method: "PATCH",
        body: JSON.stringify({ notes: document.getElementById("category-notes").value }),
      });
      await load();
      return;
    }
    if (e.target.id === "delete-sub" && selectedSubId) {
      if (!confirm("Remove this subcategory and its positions?")) return;
      await api("/academy/endgame-subcategories/" + selectedSubId, { method: "DELETE" });
      selectedSubId = null;
      await load();
      return;
    }
    if (e.target.id === "save-assign") {
      const ids = Array.from(document.querySelectorAll("#student-box input:checked")).map((i) => Number(i.value));
      await api("/academy/endgames/" + category.id + "/assign", {
        method: "POST",
        body: JSON.stringify({ studentIds: ids, replace: true }),
      });
      await load();
      return;
    }
    const brushBtn = e.target.closest("[data-brush]");
    if (brushBtn && editor) {
      brush = brushBtn.getAttribute("data-brush") || "";
      editor.setBrush(brush);
      document.querySelectorAll("#palette button").forEach((b) => {
        b.classList.toggle("on", b === brushBtn);
      });
      return;
    }
    if (e.target.id === "startpos" && editor) {
      editorFen = STARTPOS;
      editor.setFen(STARTPOS);
      editor.setTurn("w");
      setTurnUi("w");
      return;
    }
    if (e.target.id === "clear-board" && editor) {
      editor.clear();
      editorFen = editor.getFen();
      return;
    }
    if (e.target.id === "flip-editor" && editor) {
      const next = editorFen && false;
      editor.setOrientation(document.getElementById("editor-board").dataset.orient === "black" ? "white" : "black");
      document.getElementById("editor-board").dataset.orient =
        document.getElementById("editor-board").dataset.orient === "black" ? "white" : "black";
      void next;
      return;
    }
    if (e.target.id === "turn-w" || e.target.closest("#turn-w")) {
      if (editor) editor.setTurn("w");
      editorFen = editor ? editor.getFen() : editorFen;
      setTurnUi("w");
      return;
    }
    if (e.target.id === "turn-b" || e.target.closest("#turn-b")) {
      if (editor) editor.setTurn("b");
      editorFen = editor ? editor.getFen() : editorFen;
      setTurnUi("b");
      return;
    }
    const editId = e.target.getAttribute("data-edit-chapter");
    if (editId) {
      const sub = selectedSub(category);
      const chapter = ((sub && sub.chapters) || []).find((c) => String(c.id) === editId);
      if (!chapter || !editor) return;
      editorFen = chapter.fen;
      editor.setFen(chapter.fen);
      const turn = (chapter.fen.split(" ")[1] || "w") === "b" ? "b" : "w";
      editor.setTurn(turn);
      setTurnUi(turn);
      document.getElementById("chapter-title").value = chapter.title;
      document.getElementById("chapter-goal").value = chapter.goal;
      document.getElementById("chapter-notes").value = chapter.notes || "";
      document.getElementById("edit-chapter-id").value = chapter.id;
      document.getElementById("chapter-msg").textContent = "Editing " + chapter.title;
      return;
    }
    const delId = e.target.getAttribute("data-del-chapter");
    if (delId) {
      if (!confirm("Remove this position?")) return;
      await api("/academy/endgame-chapters/" + delId, { method: "DELETE" });
      await load();
    }
  });

  document.getElementById("detail").addEventListener("submit", async (e) => {
    const category = selected();
    if (e.target.id === "add-sub") {
      e.preventDefault();
      const name = document.getElementById("sub-name").value.trim();
      if (!name) return;
      try {
        const created = await api("/academy/endgames/" + category.id + "/subcategories", {
          method: "POST",
          body: JSON.stringify({ name }),
        });
        selectedSubId = created.id;
        await load();
      } catch (err) {
        alert(err.message);
      }
      return;
    }
    if (e.target.id !== "save-position") return;
    e.preventDefault();
    const msg = document.getElementById("chapter-msg");
    msg.className = "msg";
    msg.textContent = "";
    const fen = editor ? editor.getFen() : editorFen;
    const editId = document.getElementById("edit-chapter-id").value;
    const payload = {
      title: document.getElementById("chapter-title").value.trim(),
      fen: fen,
      goal: document.getElementById("chapter-goal").value,
      notes: document.getElementById("chapter-notes").value,
    };
    try {
      if (editId) {
        await api("/academy/endgame-chapters/" + editId, {
          method: "PATCH",
          body: JSON.stringify(payload),
        });
      } else {
        await api("/academy/endgame-subcategories/" + selectedSubId + "/chapters", {
          method: "POST",
          body: JSON.stringify(payload),
        });
      }
      document.getElementById("chapter-title").value = "";
      document.getElementById("chapter-notes").value = "";
      document.getElementById("edit-chapter-id").value = "";
      msg.className = "msg ok";
      msg.textContent = "Position saved.";
      await load();
    } catch (err) {
      msg.className = "msg error";
      msg.textContent = err.message;
    }
  });

  load().catch((err) => {
    document.getElementById("detail").innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
  });
})();
