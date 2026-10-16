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
  let previewTimer = null;

  function stopPreview() {
    if (!previewTimer) return;
    clearTimeout(previewTimer);
    previewTimer = null;
  }

  function arrowCount(chapter) {
    return (chapter.shapes || []).reduce((n, row) => n + (Array.isArray(row) ? row.length : 0), 0);
  }

  function shapesAt(chapter, ply) {
    const list = (chapter.shapes || [])[ply];
    return Array.isArray(list) ? list : [];
  }

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
    stopPreview();
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
      <p class="sub">Paste a PGN for each chapter. Players will study these moves, then take a test as ${opening.colorGroup}. Arrow marks in the PGN, such as { [%cal Gg1f3] } and { [%csl Gd4] }, are saved with that chapter.</p>
      <div id="chapters">${(opening.chapters || [])
        .map(
          (c) => `<div class="chapter" data-chapter="${c.id}">
            <strong>${esc(c.title)}</strong>
            <small> · ${c.plyCount} moves${arrowCount(c) ? " · " + arrowCount(c) + " arrow" + (arrowCount(c) === 1 ? "" : "s") : ""}</small>
            <div class="row" style="margin-top:8px">
              <button class="btn ghost" data-preview="${c.id}" type="button">Preview</button>
              <button class="btn ghost" data-moves="${c.id}" type="button">Edit moves</button>
              <button class="btn ghost" data-arrows="${c.id}" type="button">Edit arrows</button>
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
      <div id="move-editor" style="margin-top:16px"></div>
      <div id="arrow-editor" style="margin-top:16px"></div>

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

  let arrowStep = null;

  function openMoveEditor(opening, chapter) {
    const arrows = document.getElementById("arrow-editor");
    if (arrows) arrows.innerHTML = "";
    arrowStep = null;
    const box = document.getElementById("move-editor");
    if (!box) return;
    let line = { startFen: chapter.startFen, moves: (chapter.moves || []).slice() };
    let viewShapes = chapter.shapes || [];
    let ply = 0;
    let board = null;

    box.innerHTML = `
      <h3 style="margin:0 0 6px;color:#1e1b4b">Moves · ${esc(chapter.title)}</h3>
      <p class="sub">Change the chapter name or the PGN, then save. Arrow marks inside { } stay if you leave them there.</p>
      <div class="field">
        <label>Chapter name</label>
        <input id="edit-chapter-title" value="${esc(chapter.title)}" />
      </div>
      <div class="field">
        <label>PGN</label>
        <textarea id="edit-chapter-pgn">${esc(chapter.pgn || "")}</textarea>
      </div>
      <div class="arrow-editor">
        <div>
          <div id="move-board"></div>
          <div class="study-controls">
            <button class="btn ghost" data-mnav="start" type="button">Start</button>
            <button class="btn ghost" data-mnav="prev" type="button">Prev</button>
            <button class="btn" data-mnav="next" type="button">Next</button>
            <button class="btn ghost" data-mnav="end" type="button">End</button>
          </div>
        </div>
        <div class="moves" id="edit-moves"></div>
      </div>
      <div class="row" style="margin-top:12px">
        <button class="btn" id="save-moves" type="button">Save moves</button>
        <button class="btn ghost" id="show-moves" type="button">Show on board</button>
        <button class="btn ghost" id="close-moves" type="button">Close</button>
        <span class="msg" id="move-msg"></span>
      </div>`;

    const msg = document.getElementById("move-msg");
    board = createChessBoard(document.getElementById("move-board"), {
      fen: line.startFen,
      orientation: opening.colorGroup,
      interactive: false,
      shapes: viewShapes[0] || [],
    });

    function renderEditMoves() {
      const rows = [];
      line.moves.forEach((m, i) => {
        if (m.color === "w" || !rows.length || rows[rows.length - 1].b !== null) {
          rows.push({ n: rows.length + 1, w: m.color === "w" ? i : null, b: m.color === "w" ? null : i });
        } else {
          rows[rows.length - 1].b = i;
        }
      });
      const btn = (index) => {
        const m = line.moves[index];
        return `<button class="move-btn ${index === ply - 1 ? "on" : ""}" data-mply="${index + 1}" type="button">${esc(m.san)}</button>`;
      };
      document.getElementById("edit-moves").innerHTML =
        `<div class="move-line"><button class="move-btn ${ply === 0 ? "on" : ""}" data-mply="0" type="button">Start</button></div>` +
        rows
          .map(
            (r) => `<div class="move-line"><strong>${r.n}.</strong>
              ${r.w !== null ? btn(r.w) : '<span class="move-btn">…</span>'}
              ${r.b !== null ? btn(r.b) : ""}</div>`
          )
          .join("");
    }

    function show(next) {
      ply = Math.max(0, Math.min(line.moves.length, next));
      const m = line.moves[ply - 1];
      board.setFen(ply === 0 ? line.startFen : m.fen, ply === 0 ? null : { from: m.from, to: m.to });
      board.setShapes(viewShapes[ply] || []);
      renderEditMoves();
    }

    arrowStep = function (dir) {
      if (dir === "start") show(0);
      else if (dir === "end") show(line.moves.length);
      else show(ply + dir);
    };

    box.querySelector(".study-controls").addEventListener("click", (e) => {
      const nav = e.target.getAttribute("data-mnav");
      if (nav === "start") show(0);
      if (nav === "prev") show(ply - 1);
      if (nav === "next") show(ply + 1);
      if (nav === "end") show(line.moves.length);
    });
    document.getElementById("edit-moves").addEventListener("click", (e) => {
      const button = e.target.closest("[data-mply]");
      if (button) show(Number(button.getAttribute("data-mply")));
    });
    document.getElementById("close-moves").addEventListener("click", () => {
      arrowStep = null;
      box.innerHTML = "";
    });
    document.getElementById("show-moves").addEventListener("click", async () => {
      msg.className = "msg";
      msg.textContent = "Reading the line...";
      try {
        const parsed = await api("/academy/openings/parse-pgn", {
          method: "POST",
          body: JSON.stringify({ pgn: document.getElementById("edit-chapter-pgn").value }),
        });
        line = { startFen: parsed.startFen, moves: parsed.moves || [] };
        viewShapes = parsed.shapes || [];
        show(0);
        msg.className = "msg ok";
        msg.textContent = line.moves.length + " moves on the board. Save to keep them.";
      } catch (err) {
        msg.className = "msg error";
        msg.textContent = err.message;
      }
    });
    document.getElementById("save-moves").addEventListener("click", async () => {
      const title = document.getElementById("edit-chapter-title").value.trim();
      msg.className = "msg";
      msg.textContent = "Saving...";
      try {
        const saved = await api("/academy/chapters/" + chapter.id, {
          method: "PATCH",
          body: JSON.stringify({
            title,
            pgn: document.getElementById("edit-chapter-pgn").value,
          }),
        });
        Object.assign(chapter, saved);
        line = { startFen: saved.startFen, moves: saved.moves || [] };
        viewShapes = saved.shapes || [];
        const heading = box.querySelector("h3");
        if (heading) heading.textContent = "Moves · " + (chapter.title || "");
        const card = document.querySelector('[data-chapter="' + chapter.id + '"]');
        if (card) {
          const name = card.querySelector("strong");
          const count = card.querySelector("small");
          if (name) name.textContent = chapter.title;
          const arrows = arrowCount(chapter);
          if (count) count.textContent = " · " + chapter.plyCount + " moves" + (arrows ? " · " + arrows + " arrow" + (arrows === 1 ? "" : "s") : "");
        }
        show(0);
        msg.className = "msg ok";
        msg.textContent = "Moves saved";
      } catch (err) {
        msg.className = "msg error";
        msg.textContent = err.message;
      }
    });

    show(0);
    box.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function openArrowEditor(opening, chapter) {
    const movesBox = document.getElementById("move-editor");
    if (movesBox) movesBox.innerHTML = "";
    const box = document.getElementById("arrow-editor");
    if (!box) return;
    const moves = chapter.moves || [];
    const shapes = [];
    for (let i = 0; i <= moves.length; i++) {
      shapes.push(((chapter.shapes || [])[i] || []).slice());
    }
    let ply = 0;
    let dirty = false;

    box.innerHTML = `
      <h3 style="margin:0 0 6px;color:#1e1b4b">Arrows · ${esc(chapter.title)}</h3>
      <p class="sub">Go to a move, then right-click and drag to draw an arrow, or right-click a square for a circle.
        Hold Shift for red, Alt for blue, Shift+Alt for yellow. Draw the same arrow again to remove it.
        Left-click the board to clear that position.</p>
      <div class="arrow-editor">
        <div>
          <div id="arrow-board"></div>
          <div class="study-controls">
            <button class="btn ghost" data-anav="start" type="button">Start</button>
            <button class="btn ghost" data-anav="prev" type="button">Prev</button>
            <button class="btn" data-anav="next" type="button">Next</button>
            <button class="btn ghost" data-anav="end" type="button">End</button>
          </div>
        </div>
        <div class="moves" id="arrow-moves"></div>
      </div>
      <div class="row" style="margin-top:12px">
        <button class="btn" id="save-arrows" type="button">Save arrows</button>
        <button class="btn ghost" id="close-arrows" type="button">Close</button>
        <span class="msg" id="arrow-msg"></span>
      </div>`;

    const msg = document.getElementById("arrow-msg");
    const board = createChessBoard(document.getElementById("arrow-board"), {
      fen: chapter.startFen,
      orientation: opening.colorGroup,
      interactive: false,
      drawable: true,
      shapes: shapes[0],
      onShapesChange(list) {
        shapes[ply] = list;
        dirty = true;
        msg.className = "msg";
        msg.textContent = "Unsaved changes";
        renderArrowMoves();
      },
    });

    function moveButton(index) {
      const m = moves[index];
      const mark = shapes[index + 1].length ? '<span class="arrow-mark" title="Has arrows"></span>' : "";
      return `<button class="move-btn ${index === ply - 1 ? "on" : ""}" data-aply="${index + 1}" type="button">${esc(m.san)}${mark}</button>`;
    }

    function renderArrowMoves() {
      const rows = [];
      let n = 1;
      moves.forEach((m, i) => {
        if (m.color === "w" || !rows.length || rows[rows.length - 1].b !== null) {
          rows.push({ n, w: m.color === "w" ? i : null, b: m.color === "w" ? null : i });
          n += 1;
        } else {
          rows[rows.length - 1].b = i;
        }
      });
      const startMark = shapes[0].length ? '<span class="arrow-mark" title="Has arrows"></span>' : "";
      document.getElementById("arrow-moves").innerHTML =
        `<div class="move-line"><button class="move-btn ${ply === 0 ? "on" : ""}" data-aply="0" type="button">Start${startMark}</button></div>` +
        rows
          .map(
            (r) => `<div class="move-line"><strong>${r.n}.</strong>
              ${r.w !== null ? moveButton(r.w) : '<span class="move-btn">…</span>'}
              ${r.b !== null ? moveButton(r.b) : ""}</div>`
          )
          .join("");
    }

    function go(next) {
      ply = Math.max(0, Math.min(moves.length, next));
      const m = moves[ply - 1];
      board.setFen(ply === 0 ? chapter.startFen : m.fen, ply === 0 ? null : { from: m.from, to: m.to });
      board.setShapes(shapes[ply]);
      renderArrowMoves();
    }

    arrowStep = function (dir) {
      if (dir === "start") go(0);
      else if (dir === "end") go(moves.length);
      else go(ply + dir);
    };

    box.querySelector(".study-controls").addEventListener("click", (e) => {
      const nav = e.target.getAttribute("data-anav");
      if (nav === "start") go(0);
      if (nav === "prev") go(ply - 1);
      if (nav === "next") go(ply + 1);
      if (nav === "end") go(moves.length);
    });
    document.getElementById("arrow-moves").addEventListener("click", (e) => {
      const btn = e.target.closest("[data-aply]");
      if (btn) go(Number(btn.getAttribute("data-aply")));
    });
    document.getElementById("close-arrows").addEventListener("click", () => {
      if (dirty && !confirm("Close without saving the arrows?")) return;
      arrowStep = null;
      box.innerHTML = "";
    });
    document.getElementById("save-arrows").addEventListener("click", async () => {
      msg.className = "msg";
      msg.textContent = "Saving...";
      try {
        const saved = await api("/academy/chapters/" + chapter.id, {
          method: "PATCH",
          body: JSON.stringify({ shapes }),
        });
        Object.assign(chapter, saved);
        dirty = false;
        msg.className = "msg ok";
        msg.textContent = "Arrows saved";
      } catch (err) {
        msg.className = "msg error";
        msg.textContent = err.message;
      }
    });

    go(0);
    box.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  document.addEventListener("keydown", (e) => {
    if (!arrowStep || !document.getElementById("arrow-board")) return;
    const tag = (e.target && e.target.tagName) || "";
    if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") return;
    const map = { ArrowLeft: -1, ArrowRight: 1, Home: "start", End: "end" };
    if (!(e.key in map)) return;
    e.preventDefault();
    arrowStep(map[e.key]);
  });

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
      stopPreview();
      box.innerHTML = `<p class="stat">${esc(chapter.title)}</p><div id="preview-board"></div>`;
      previewBoard = createChessBoard(document.getElementById("preview-board"), {
        orientation: opening.colorGroup,
        interactive: false,
        fen: chapter.startFen,
        shapes: shapesAt(chapter, 0),
      });
      let ply = 0;
      function show(next) {
        ply = next;
        const move = chapter.moves[ply - 1];
        previewBoard.setFen(
          ply === 0 ? chapter.startFen : move.fen,
          ply === 0 ? null : { from: move.from, to: move.to }
        );
        previewBoard.setShapes(shapesAt(chapter, ply));
      }
      function step() {
        if (!chapter.moves[ply]) {
          previewTimer = null;
          return;
        }
        show(ply + 1);
        const wait = shapesAt(chapter, ply).length ? 1800 : 800;
        previewTimer = setTimeout(step, wait);
      }
      show(0);
      previewTimer = setTimeout(step, shapesAt(chapter, 0).length ? 1800 : 800);
      return;
    }
    const movesId = e.target.getAttribute("data-moves");
    if (movesId) {
      const chapter = (opening.chapters || []).find((c) => String(c.id) === movesId);
      if (chapter) openMoveEditor(opening, chapter);
      return;
    }
    const arrowsId = e.target.getAttribute("data-arrows");
    if (arrowsId) {
      const chapter = (opening.chapters || []).find((c) => String(c.id) === arrowsId);
      if (chapter) openArrowEditor(opening, chapter);
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
