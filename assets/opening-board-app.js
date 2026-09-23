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
  const openingId = Number(params.get("opening") || 0);
  const chapterId = Number(params.get("chapter") || 0);
  const mode = params.get("mode") === "test" ? "test" : "study";

  document.getElementById("back").setAttribute("href", BASE + "/opening.php?id=" + openingId);

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

  function boardUrl(oid, cid, nextMode) {
    return BASE + "/opening-board.php?opening=" + oid + "&chapter=" + cid + "&mode=" + nextMode;
  }

  function chapterInfo(opening, currentId) {
    const chapters = opening.chapters || [];
    const idx = chapters.findIndex((c) => c.id === currentId);
    return {
      chapters,
      idx,
      total: chapters.length,
      prev: idx > 0 ? chapters[idx - 1] : null,
      next: idx >= 0 && idx < chapters.length - 1 ? chapters[idx + 1] : null,
      current: idx >= 0 ? chapters[idx] : null,
    };
  }

  function renderChapterNav(opening) {
    const info = chapterInfo(opening, chapterId);
    const el = document.getElementById("chapter-nav");
    if (!el) return info;
    const prevHref = info.prev ? boardUrl(opening.id, info.prev.id, mode) : "";
    const nextHref = info.next ? boardUrl(opening.id, info.next.id, mode) : "";
    el.innerHTML =
      '<span class="count">Chapter ' +
      (info.idx + 1) +
      " of " +
      info.total +
      "</span>" +
      (info.prev
        ? '<a class="btn ghost" href="' + prevHref + '">← ' + escapeHtml(info.prev.title) + "</a>"
        : '<span class="btn ghost is-off">← Prev chapter</span>') +
      (info.next
        ? '<a class="btn" href="' + nextHref + '">Next chapter → ' + escapeHtml(info.next.title) + "</a>"
        : '<span class="btn ghost is-off">Last chapter</span>');
    return info;
  }

  function renderNextLine(opening, finished) {
    const box = document.getElementById("next-line");
    if (!box) return;
    const info = chapterInfo(opening, chapterId);
    if (!finished || !info.next) {
      box.innerHTML = "";
      return;
    }
    const studyHref = boardUrl(opening.id, info.next.id, "study");
    const testHref = boardUrl(opening.id, info.next.id, "test");
    box.innerHTML =
      "<p>Chapter done. Next: <strong>" +
      escapeHtml(info.next.title) +
      "</strong> (" +
      (info.idx + 2) +
      " of " +
      info.total +
      ")</p>" +
      '<div class="row">' +
      '<a class="btn ghost" href="' +
      studyHref +
      '">Study next chapter</a>' +
      '<a class="btn" href="' +
      testHref +
      '">Test next chapter</a>' +
      "</div>";
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function showBanner(text, kind) {
    const el = document.getElementById("banner");
    if (!text) {
      el.innerHTML = "";
      return;
    }
    el.innerHTML = '<div class="banner ' + kind + '">' + text + "</div>";
  }

  function movePairs(moves) {
    const rows = [];
    let n = 1;
    for (let i = 0; i < moves.length; i++) {
      const m = moves[i];
      if (m.color === "w") {
        rows.push({ n, w: m, b: null, wi: i, bi: -1 });
        n += 1;
      } else if (rows.length && rows[rows.length - 1].b === null) {
        rows[rows.length - 1].b = m;
        rows[rows.length - 1].bi = i;
      } else {
        rows.push({ n, w: null, b: m, wi: -1, bi: i });
        n += 1;
      }
    }
    return rows;
  }

  let board = null;
  let ply = 0;
  let autoTimer = null;
  let stepLine = null;
  let reviewReady = false;

  function startFen(data) {
    return data.startFen || "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1";
  }

  function fenAt(data, index) {
    if (index <= 0) return startFen(data);
    return data.moves[index - 1].fen;
  }
  function lastAt(data, index) {
    if (index <= 0) return null;
    const m = data.moves[index - 1];
    return { from: m.from, to: m.to };
  }

  function renderMoves(data, current, hideFuture) {
    const box = document.getElementById("moves");
    box.innerHTML = movePairs(data.moves)
      .map((row) => {
        const wLabel = row.w ? (hideFuture && row.wi >= current ? "…" : row.w.san) : "";
        const bLabel = row.b ? (hideFuture && row.bi >= current ? "…" : row.b.san) : "";
        return `<div class="move-line">
          <strong>${row.n}.</strong>
          ${row.w ? `<button class="move-btn ${row.wi === current - 1 ? "on" : ""}" data-ply="${row.wi + 1}">${wLabel}</button>` : ""}
          ${row.b ? `<button class="move-btn ${row.bi === current - 1 ? "on" : ""}" data-ply="${row.bi + 1}">${bLabel}</button>` : ""}
        </div>`;
      })
      .join("");
  }

  function stopAutoplay() {
    if (!autoTimer) return;
    clearInterval(autoTimer);
    autoTimer = null;
    const btn = document.getElementById("autoplay");
    if (btn) btn.textContent = "Autoplay";
  }

  function bindKeyboard() {
    document.addEventListener("keydown", (e) => {
      if (!stepLine) return;
      const tag = (e.target && e.target.tagName) || "";
      if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") return;
      if (e.key === "ArrowLeft") {
        e.preventDefault();
        stopAutoplay();
        stepLine(-1);
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        stopAutoplay();
        stepLine(1);
      } else if (e.key === "Home") {
        e.preventDefault();
        stopAutoplay();
        stepLine("start");
      } else if (e.key === "End") {
        e.preventDefault();
        stopAutoplay();
        stepLine("end");
      }
    });
  }

  async function startStudy(opening) {
    const chapter = (opening.chapters || []).find((c) => c.id === chapterId);
    if (!chapter) throw new Error("Chapter not found");
    renderChapterNav(opening);
    document.getElementById("title").textContent = opening.name;
    document.getElementById("subtitle").textContent = chapter.title + " · Study";
    document.getElementById("side-title").textContent = "Study the line";
    const turnText = document.getElementById("turn-text");
    const turnDot = document.getElementById("turn-dot");
    if (turnText) turnText.textContent = "Use ← → to step through the moves";
    if (turnDot) turnDot.className = "turn-dot " + (opening.colorGroup === "black" ? "black" : "white");
    document.getElementById("study-controls").style.display = "flex";
    board = createChessBoard(document.getElementById("board"), {
      fen: chapter.startFen,
      orientation: opening.colorGroup,
      interactive: false,
    });
    ply = 0;
    reviewReady = true;
    renderMoves(chapter, ply, false);
    function go(next) {
      ply = Math.max(0, Math.min(chapter.moves.length, next));
      board.setFen(fenAt(chapter, ply), lastAt(chapter, ply));
      renderMoves(chapter, ply, false);
      renderNextLine(opening, ply >= chapter.moves.length && chapter.moves.length > 0);
    }
    stepLine = function (dir) {
      if (dir === "start") go(0);
      else if (dir === "end") go(chapter.moves.length);
      else go(ply + dir);
    };
    document.getElementById("study-controls").addEventListener("click", (e) => {
      const nav = e.target.getAttribute("data-nav");
      if (!nav) return;
      if (nav === "start") go(0);
      if (nav === "prev") go(ply - 1);
      if (nav === "next") go(ply + 1);
      if (nav === "end") go(chapter.moves.length);
    });
    document.getElementById("moves").addEventListener("click", (e) => {
      const btn = e.target.closest("[data-ply]");
      if (!btn) return;
      go(Number(btn.getAttribute("data-ply")));
    });
    document.getElementById("autoplay").addEventListener("click", () => {
      if (autoTimer) {
        stopAutoplay();
        return;
      }
      document.getElementById("autoplay").textContent = "Stop";
      autoTimer = setInterval(() => {
        if (ply >= chapter.moves.length) {
          stopAutoplay();
          return;
        }
        go(ply + 1);
      }, 800);
    });
    let flipped = false;
    document.getElementById("flip").onclick = () => {
      flipped = !flipped;
      board.setOrientation(flipped ? (opening.colorGroup === "black" ? "white" : "black") : opening.colorGroup);
    };
  }

  async function startTest(opening) {
    const chapter = (opening.chapters || []).find((c) => c.id === chapterId);
    if (!chapter) throw new Error("Chapter not found");
    renderChapterNav(opening);
    document.getElementById("title").textContent = opening.name;
    document.getElementById("subtitle").textContent = chapter.title + " · Test";
    document.getElementById("side-title").textContent = "Play the " + (opening.colorGroup === "black" ? "Black" : "White") + " moves";
    const turnText = document.getElementById("turn-text");
    const turnDot = document.getElementById("turn-dot");
    if (turnText) turnText.textContent = "Your move as " + (opening.colorGroup === "black" ? "Black" : "White");
    if (turnDot) turnDot.className = "turn-dot " + (opening.colorGroup === "black" ? "black" : "white");
    const started = await api("/opening-tests/start", {
      method: "POST",
      body: JSON.stringify({ chapterId }),
    });
    const playerColor = started.playerColor;
    board = createChessBoard(document.getElementById("board"), {
      fen: started.fen,
      orientation: opening.colorGroup,
      interactive: !started.test.completed,
      playerColor,
      legalMoves: started.legalMoves || [],
      lastMove: started.lastMove,
      onMove: play,
    });
    let state = started;
    applyTest(opening, state, chapter);
    if (state.test.completed) {
      enableReview();
    }
    let flipped = false;
    document.getElementById("flip").onclick = () => {
      flipped = !flipped;
      board.setOrientation(flipped ? (opening.colorGroup === "black" ? "white" : "black") : opening.colorGroup);
    };

    function enableReview() {
      reviewReady = true;
      ply = chapter.moves.length;
      document.getElementById("study-controls").style.display = "flex";
      stepLine = function (dir) {
        if (dir === "start") ply = 0;
        else if (dir === "end") ply = chapter.moves.length;
        else ply = Math.max(0, Math.min(chapter.moves.length, ply + dir));
        board.setInteractive(false);
        board.setFen(fenAt(chapter, ply), lastAt(chapter, ply));
        renderMoves(chapter, ply, false);
      };
    }

    async function play(from, to, promotion) {
      try {
        state = await api("/opening-tests/" + state.test.id + "/play", {
          method: "POST",
          body: JSON.stringify({ from, to, promotion }),
        });
        if (!state.correct) {
          board.flashWrong();
          showBanner(state.message || "Wrong move. Try again.", "wrong");
          board.setLegal(state.legalMoves || []);
          board.setFen(state.fen, state.lastMove);
          return;
        }
        showBanner(state.completed ? "Chapter complete." : "Good move.", state.completed ? "ok" : "info");
        board.setLegal(state.legalMoves || []);
        board.setInteractive(!state.test.completed);
        board.setFen(state.fen, state.lastMove || state.autoMove);
        applyTest(opening, state, chapter);
        if (state.test.completed) enableReview();
      } catch (err) {
        showBanner(err.message, "wrong");
      }
    }
  }

  function applyTest(opening, state, chapter) {
    renderMoves(chapter, state.test.currentMoveIndex, true);
    const extra = state.test.completed
      ? " Finished. Wrong moves: " + state.test.wrongMoves + (state.test.passed ? " · Clean test" : "")
      : " Your move as " + (state.playerColor === "b" ? "Black" : "White") + ". Wrong moves: " + state.test.wrongMoves;
    document.getElementById("subtitle").textContent = chapter.title + " · Test ·" + extra;
    renderNextLine(opening, !!state.test.completed);
    if (state.test.completed) {
      showBanner("Test finished. Wrong moves: " + state.test.wrongMoves + ".", state.test.passed ? "ok" : "info");
    }
  }

  bindKeyboard();
  api("/me/openings/" + openingId)
    .then((opening) => (mode === "test" ? startTest(opening) : startStudy(opening)))
    .catch((err) => showBanner(err.message, "wrong"));
})();
