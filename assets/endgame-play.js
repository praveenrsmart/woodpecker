(function () {
  const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  const API = BASE + "/api";
  const token = localStorage.getItem("woodpecker_token") || "";
  const role = localStorage.getItem("woodpecker_role");
  if (!token || role !== "student") {
    location.replace(BASE + "/login.php?role=student");
    return;
  }

  const params = new URLSearchParams(location.search);
  const categoryId = Number(params.get("category") || 0);
  const chapterId = Number(params.get("chapter") || 0);
  const mode = params.get("mode") === "test" ? "test" : "practice";
  let level = params.get("level") || localStorage.getItem("woodpecker_endgame_level") || "intermediate";

  document.getElementById("back").setAttribute("href", BASE + "/endgame.php?id=" + categoryId);

  const headers = {
    "Content-Type": "application/json",
    Authorization: "Bearer " + token,
  };

  let board = null;
  let category = null;
  let attemptId = null;
  let chapter = null;
  let startFen = "";
  let playerColor = "w";
  let goal = "";
  let busy = false;
  let finished = false;
  let failedRestarts = 0;
  let currentFen = "";
  let moves = [];
  let startPosition = null;

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
      .replace(/>/g, "&gt;");
  }

  function allChapters() {
    const list = [];
    (category.subcategories || []).forEach((sub) => {
      (sub.chapters || []).forEach((c) => list.push(c));
    });
    return list;
  }

  function boardUrl(cid, nextMode, nextLevel) {
    return (
      BASE +
      "/endgame-board.php?category=" +
      categoryId +
      "&chapter=" +
      cid +
      "&mode=" +
      nextMode +
      "&level=" +
      encodeURIComponent(nextLevel || level)
    );
  }

  function setBanner(kind, text) {
    const el = document.getElementById("banner");
    if (!text) {
      el.innerHTML = "";
      return;
    }
    el.innerHTML = '<div class="banner ' + kind + '">' + esc(text) + "</div>";
  }

  function setTurn(turn, text) {
    const dot = document.getElementById("turn-dot");
    const label = document.getElementById("turn-text");
    if (dot) {
      dot.className = "turn-dot " + (turn === "b" ? "black" : "white");
    }
    if (label) label.textContent = text;
  }

  function recordMove(pos) {
    if (!pos || !pos.lastMove || !pos.lastMove.san) return;
    const color = pos.turn === "b" ? "w" : "b";
    moves.push({ san: pos.lastMove.san, color: color });
    renderMoves();
  }

  function renderMoves() {
    const box = document.getElementById("moves");
    if (!box) return;
    if (!moves.length) {
      box.innerHTML = '<p class="empty">No moves yet.</p>';
      return;
    }
    const rows = [];
    let i = 0;
    let n = 1;
    if (moves[0].color === "b") {
      rows.push({ n: n, w: "", b: moves[0].san });
      i = 1;
      n = 2;
    }
    while (i < moves.length) {
      const white = moves[i] && moves[i].color === "w" ? moves[i++].san : "";
      const black = moves[i] && moves[i].color === "b" ? moves[i++].san : "";
      rows.push({ n: n, w: white, b: black });
      n += 1;
    }
    box.innerHTML = rows
      .map((row) => {
        const white = row.w
          ? '<span class="move-btn">' + esc(row.w) + "</span>"
          : row.b
            ? '<span class="move-btn">…</span>'
            : "";
        const black = row.b ? '<span class="move-btn">' + esc(row.b) + "</span>" : "";
        return (
          '<div class="move-line"><strong>' +
          row.n +
          ".</strong> " +
          white +
          " " +
          black +
          "</div>"
        );
      })
      .join("");
    box.scrollTop = box.scrollHeight;
  }

  function renderNav() {
    const chapters = allChapters();
    const idx = chapters.findIndex((c) => c.id === chapterId);
    const el = document.getElementById("chapter-nav");
    if (!el || idx < 0) return;
    const prev = idx > 0 ? chapters[idx - 1] : null;
    const next = idx < chapters.length - 1 ? chapters[idx + 1] : null;
    el.innerHTML =
      '<span class="count">Position ' +
      (idx + 1) +
      " of " +
      chapters.length +
      "</span>" +
      (prev
        ? '<a class="btn ghost" href="' + boardUrl(prev.id, mode) + '">← ' + esc(prev.title) + "</a>"
        : '<span class="btn ghost is-off">← Prev</span>') +
      (next
        ? '<a class="btn" href="' + boardUrl(next.id, mode) + '">Next → ' + esc(next.title) + "</a>"
        : '<span class="btn ghost is-off">Last position</span>');
  }

  function renderNext(done) {
    const box = document.getElementById("next-line");
    if (!box) return;
    const chapters = allChapters();
    const idx = chapters.findIndex((c) => c.id === chapterId);
    const next = idx >= 0 && idx < chapters.length - 1 ? chapters[idx + 1] : null;
    if (!done || !next) {
      box.innerHTML = "";
      return;
    }
    box.innerHTML =
      "<p>Next: <strong>" +
      esc(next.title) +
      "</strong></p>" +
      '<div class="row"><a class="btn ghost" href="' +
      boardUrl(next.id, "practice") +
      '">Practice next</a><a class="btn" href="' +
      boardUrl(next.id, "test") +
      '">Test next</a></div>';
  }

  function applyPosition(pos) {
    currentFen = pos.fen;
    if (!board) return;
    board.setLegal(pos.legalMoves || []);
    board.setFen(pos.fen, pos.lastMove || null);
    board.setPlayerColor(playerColor);
    board.setInteractive(!finished && !busy && pos.turn === playerColor && !pos.gameOver);
    setTurn(
      pos.turn,
      pos.gameOver
        ? "Game over"
        : pos.turn === playerColor
          ? "Your move"
          : "Engine thinking..."
    );
  }

  async function engineReply() {
    busy = true;
    applyPosition({ fen: currentFen, turn: playerColor === "w" ? "b" : "w", legalMoves: [] });
    try {
      const pos = await api("/endgame/engine-move", {
        method: "POST",
        body: JSON.stringify({
          fen: currentFen,
          level: level,
          goal: goal,
          playerColor: playerColor,
        }),
      });
      recordMove(pos);
      busy = false;
      applyPosition(pos);
      return pos;
    } catch (err) {
      busy = false;
      setBanner("wrong", err.message);
      applyPosition({ fen: currentFen, turn: playerColor === "w" ? "b" : "w", legalMoves: [] });
      return null;
    }
  }

  async function resetToStart(message) {
    finished = false;
    moves = [];
    renderMoves();
    currentFen = startFen;
    if (message) setBanner("info", message);
    if (startPosition) applyPosition(startPosition);
    if (startPosition && !startPosition.gameOver && startPosition.turn !== playerColor) {
      const reply = await engineReply();
      if (reply) await handleJudge(reply);
    }
  }

  async function handleJudge(pos) {
    const judge = pos.judge || {};
    if (!judge.over) return;
    if (judge.failed) {
      failedRestarts += 1;
      if (failedRestarts > 8) {
        finished = true;
        if (board) board.setInteractive(false);
        setBanner("wrong", "Too many failed restarts. Open the position again to retry.");
        return;
      }
      if (board) board.flashWrong();
      setBanner(
        "wrong",
        mode === "test"
          ? "Not the required result. Test restarts from the first move."
          : "Not the required result. Restart from the first move."
      );
      await resetToStart("");
      return;
    }
    if (judge.passed) {
      finished = true;
      if (board) board.setInteractive(false);
      try {
        const done = await api("/endgame-attempts/" + attemptId + "/finish", {
          method: "POST",
          body: JSON.stringify({
            passed: true,
            failedRestarts: failedRestarts,
            result: pos.result || "*",
          }),
        });
        if (mode === "test" && done.green) {
          setBanner("ok", "Clean test. This position is now green.");
        } else if (mode === "test") {
          setBanner("info", "You reached the result, but a restart means it is not marked green yet.");
        } else {
          setBanner("ok", "Practice complete. You can take the test from the list.");
        }
      } catch (err) {
        setBanner("wrong", err.message);
      }
      renderNext(true);
    }
  }

  async function play(from, to, promotion) {
    if (busy || finished) return;
    busy = true;
    try {
      const pos = await api("/endgame/move", {
        method: "POST",
        body: JSON.stringify({
          fen: currentFen,
          from: from,
          to: to,
          promotion: promotion || "q",
          goal: goal,
          playerColor: playerColor,
        }),
      });
      recordMove(pos);
      applyPosition(pos);
      busy = false;
      if (pos.judge && pos.judge.over) {
        await handleJudge(pos);
        return;
      }
      if (pos.turn !== playerColor) {
        const reply = await engineReply();
        if (reply) await handleJudge(reply);
      }
    } catch (err) {
      busy = false;
      if (board) board.flashWrong();
      setBanner("wrong", err.message || "Illegal move");
    }
  }

  function fillLevels(levels) {
    const sel = document.getElementById("level");
    sel.innerHTML = (levels || [])
      .map((l) => {
        const id = l.id;
        const label = (l.label || id) + " (~" + (l.rating || "") + ")";
        return `<option value="${esc(id)}" ${id === level ? "selected" : ""}>${esc(label)}</option>`;
      })
      .join("");
    sel.addEventListener("change", () => {
      level = sel.value;
      localStorage.setItem("woodpecker_endgame_level", level);
      location.href = boardUrl(chapterId, mode, level);
    });
  }

  async function boot() {
    category = await api("/me/endgames/" + categoryId);
    const chapters = allChapters();
    const found = chapters.find((c) => c.id === chapterId);
    if (!found) throw new Error("Position not found");
    if (mode === "test" && !found.practiceTaken) {
      location.replace(BASE + "/endgame.php?id=" + categoryId);
      return;
    }
    chapter = found;
    document.getElementById("title").textContent = found.title;
    document.getElementById("subtitle").textContent =
      category.name + " · " + found.goalLabel + " · " + (mode === "test" ? "Test" : "Practice");
    document.getElementById("goal-text").textContent =
      "Required result: " + found.goalLabel + ". You play " + (found.playerColor === "b" ? "Black" : "White") + ".";
    renderNav();

    const started = await api("/endgame-attempts/start", {
      method: "POST",
      body: JSON.stringify({ chapterId: chapterId, mode: mode, level: level }),
    });
    attemptId = started.attempt.id;
    chapter = started.chapter;
    startFen = started.chapter.fen;
    startPosition = Object.assign({}, started.position, { lastMove: null });
    playerColor = started.playerColor;
    goal = started.chapter.goal;
    currentFen = started.position.fen;
    fillLevels(started.levels || []);

    board = createChessBoard(document.getElementById("board"), {
      fen: started.position.fen,
      orientation: playerColor === "b" ? "black" : "white",
      interactive: true,
      playerColor: playerColor,
      legalMoves: started.position.legalMoves || [],
      onMove: play,
      onIllegal: function () {
        setBanner("wrong", "That move is not legal.");
      },
    });
    applyPosition(started.position);
    if (!started.position.gameOver && started.position.turn !== playerColor) {
      const reply = await engineReply();
      if (reply) await handleJudge(reply);
    }
  }

  document.getElementById("flip").addEventListener("click", () => {
    if (!board) return;
    const root = document.getElementById("board");
    const next = root.dataset.orient === "black" ? "white" : "black";
    root.dataset.orient = next;
    board.setOrientation(next);
  });

  document.getElementById("restart").addEventListener("click", async () => {
    if (finished) return;
    if (moves.length) failedRestarts += 1;
    await resetToStart("Restarted from the first move.");
  });

  boot().catch((err) => {
    setBanner("wrong", err.message);
  });
})();
