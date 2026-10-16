(function (global) {
  const FEN_CLASS = {
    K: "king white", Q: "queen white", R: "rook white", B: "bishop white", N: "knight white", P: "pawn white",
    k: "king black", q: "queen black", r: "rook black", b: "bishop black", n: "knight black", p: "pawn black",
  };
  const FEN_CODE = {
    K: "wK", Q: "wQ", R: "wR", B: "wB", N: "wN", P: "wP",
    k: "bK", q: "bQ", r: "bR", b: "bB", n: "bN", p: "bP",
  };

  function filesFor(orientation) {
    const files = ["a", "b", "c", "d", "e", "f", "g", "h"];
    return orientation === "black" ? files.slice().reverse() : files;
  }
  function ranksFor(orientation) {
    const ranks = [8, 7, 6, 5, 4, 3, 2, 1];
    return orientation === "black" ? ranks.slice().reverse() : ranks;
  }

  function parseFen(fen) {
    const board = {};
    const placement = String(fen || "").split(" ")[0] || "";
    const rows = placement.split("/");
    for (let r = 0; r < 8; r++) {
      const rank = 8 - r;
      let file = 0;
      const row = rows[r] || "";
      for (let i = 0; i < row.length; i++) {
        const ch = row[i];
        if (ch >= "1" && ch <= "8") {
          file += Number(ch);
          continue;
        }
        const sq = "abcdefgh"[file] + rank;
        if (FEN_CODE[ch]) board[sq] = { code: FEN_CODE[ch], cls: FEN_CLASS[ch] };
        file += 1;
      }
    }
    return board;
  }

  const SVG_NS = "http://www.w3.org/2000/svg";
  let boardSeq = 0;
  const BRUSHES = {
    green: "#15781B",
    red: "#882020",
    blue: "#003088",
    yellow: "#e68f00",
  };
  const BRUSH_ORDER = ["green", "red", "blue", "yellow"];

  function createChessBoard(root, options) {
    const opts = options || {};
    const state = {
      fen: opts.fen || "rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1",
      orientation: opts.orientation === "black" ? "black" : "white",
      interactive: !!opts.interactive || !!opts.editor,
      editor: !!opts.editor,
      brush: opts.brush || "",
      legal: opts.legalMoves || [],
      lastMove: opts.lastMove || null,
      selected: null,
      playerColor: opts.playerColor || "w",
      turn: (opts.fen || " w ").split(" ")[1] || "w",
      drawable: !!opts.drawable,
      shapes: Array.isArray(opts.shapes) ? opts.shapes.slice() : [],
      drawing: null,
    };

    const markerNs = "wpah" + (++boardSeq);
    root.classList.add("wp-board-wrap");
    root.innerHTML = "";
    const frame = document.createElement("div");
    frame.className = "wp-board-frame";
    const board = document.createElement("div");
    board.className = "wp-board";
    frame.appendChild(board);
    const svg = document.createElementNS(SVG_NS, "svg");
    svg.setAttribute("class", "wp-shapes");
    svg.setAttribute("viewBox", "0 0 8 8");
    frame.appendChild(svg);
    root.appendChild(frame);

    function squareXY(sq) {
      const fi = filesFor(state.orientation).indexOf(sq[0]);
      const ri = ranksFor(state.orientation).indexOf(Number(sq[1]));
      return { x: fi + 0.5, y: ri + 0.5 };
    }

    function squareAtPoint(clientX, clientY) {
      const rect = board.getBoundingClientRect();
      const col = Math.floor(((clientX - rect.left) / rect.width) * 8);
      const row = Math.floor(((clientY - rect.top) / rect.height) * 8);
      if (col < 0 || col > 7 || row < 0 || row > 7) return null;
      return filesFor(state.orientation)[col] + ranksFor(state.orientation)[row];
    }

    function shapeNode(shape, preview) {
      const color = BRUSHES[shape.brush] || BRUSHES.green;
      const a = squareXY(shape.orig);
      if (!shape.dest || shape.dest === shape.orig) {
        const c = document.createElementNS(SVG_NS, "circle");
        c.setAttribute("cx", a.x);
        c.setAttribute("cy", a.y);
        c.setAttribute("r", 0.5 - 0.0625 / 2 - 0.02);
        c.setAttribute("fill", "none");
        c.setAttribute("stroke", color);
        c.setAttribute("stroke-width", 0.0625);
        c.setAttribute("opacity", preview ? 0.5 : 1);
        return c;
      }
      const b = squareXY(shape.dest);
      const angle = Math.atan2(b.y - a.y, b.x - a.x);
      const margin = 0.3;
      const line = document.createElementNS(SVG_NS, "line");
      line.setAttribute("x1", a.x);
      line.setAttribute("y1", a.y);
      line.setAttribute("x2", b.x - Math.cos(angle) * margin);
      line.setAttribute("y2", b.y - Math.sin(angle) * margin);
      line.setAttribute("stroke", color);
      line.setAttribute("stroke-width", 0.15625);
      line.setAttribute("stroke-linecap", "round");
      line.setAttribute("marker-end", "url(#" + markerNs + "-" + (BRUSHES[shape.brush] ? shape.brush : "green") + ")");
      line.setAttribute("opacity", preview ? 0.5 : 0.8);
      return line;
    }

    function drawShapes() {
      svg.innerHTML = "";
      const defs = document.createElementNS(SVG_NS, "defs");
      BRUSH_ORDER.forEach((name) => {
        const marker = document.createElementNS(SVG_NS, "marker");
        marker.setAttribute("id", markerNs + "-" + name);
        marker.setAttribute("orient", "auto");
        marker.setAttribute("markerWidth", "4");
        marker.setAttribute("markerHeight", "8");
        marker.setAttribute("refX", "2.05");
        marker.setAttribute("refY", "2.01");
        const path = document.createElementNS(SVG_NS, "path");
        path.setAttribute("d", "M0,0 V4 L3,2 Z");
        path.setAttribute("fill", BRUSHES[name]);
        marker.appendChild(path);
        defs.appendChild(marker);
      });
      svg.appendChild(defs);
      state.shapes.forEach((shape) => svg.appendChild(shapeNode(shape, false)));
      if (state.drawing && state.drawing.dest) {
        svg.appendChild(shapeNode(state.drawing, true));
      }
    }

    function sameShape(a, b) {
      return a.orig === b.orig && (a.dest || a.orig) === (b.dest || b.orig);
    }

    function commitShape(shape) {
      const idx = state.shapes.findIndex((s) => sameShape(s, shape));
      if (idx === -1) {
        state.shapes.push(shape);
      } else if (state.shapes[idx].brush === shape.brush) {
        state.shapes.splice(idx, 1);
      } else {
        state.shapes[idx] = shape;
      }
      drawShapes();
      if (typeof opts.onShapesChange === "function") opts.onShapesChange(state.shapes.slice());
    }

    board.addEventListener("contextmenu", (e) => {
      if (state.drawable) e.preventDefault();
    });

    board.addEventListener("mousedown", (e) => {
      if (!state.drawable) return;
      if (e.button === 0) {
        if (state.shapes.length) {
          state.shapes = [];
          drawShapes();
          if (typeof opts.onShapesChange === "function") opts.onShapesChange([]);
        }
        return;
      }
      if (e.button !== 2) return;
      e.preventDefault();
      const sq = squareAtPoint(e.clientX, e.clientY);
      if (!sq) return;
      const modA = e.shiftKey || e.ctrlKey;
      const modB = e.altKey || e.metaKey;
      state.drawing = { orig: sq, dest: null, brush: BRUSH_ORDER[(modA ? 1 : 0) + (modB ? 2 : 0)] };
    });

    window.addEventListener("mousemove", (e) => {
      if (!state.drawing) return;
      const sq = squareAtPoint(e.clientX, e.clientY);
      const dest = sq && sq !== state.drawing.orig ? sq : null;
      if (dest !== state.drawing.dest) {
        state.drawing.dest = dest;
        drawShapes();
      }
    });

    window.addEventListener("mouseup", (e) => {
      if (!state.drawing || e.button !== 2) return;
      const sq = squareAtPoint(e.clientX, e.clientY);
      const drawing = state.drawing;
      state.drawing = null;
      if (!sq) {
        drawShapes();
        return;
      }
      commitShape({ orig: drawing.orig, dest: sq === drawing.orig ? undefined : sq, brush: drawing.brush });
    });

    function legalTargets(from) {
      return state.legal.filter((m) => m.from === from).map((m) => m.to);
    }

    function render() {
      const pieces = parseFen(state.fen);
      const files = filesFor(state.orientation);
      const ranks = ranksFor(state.orientation);
      board.innerHTML = "";
      ranks.forEach((rank, ri) => {
        files.forEach((file, fi) => {
          const sq = file + rank;
          const dark = (fi + ri) % 2 === 1;
          const el = document.createElement("div");
          el.className = "wp-sq" + (dark ? " dark" : " light");
          el.dataset.square = sq;
          if (state.lastMove && (state.lastMove.from === sq || state.lastMove.to === sq)) {
            el.classList.add("last");
          }
          if (state.selected === sq) el.classList.add("selected");
          if (state.selected && legalTargets(state.selected).indexOf(sq) !== -1) {
            el.classList.add(pieces[sq] ? "capture" : "hint");
          }
          if ((state.orientation === "white" && rank === 1) || (state.orientation === "black" && rank === 8)) {
            const lab = document.createElement("span");
            lab.className = "coord file";
            lab.textContent = file;
            el.appendChild(lab);
          }
          if ((state.orientation === "white" && file === "a") || (state.orientation === "black" && file === "h")) {
            const lab = document.createElement("span");
            lab.className = "coord rank";
            lab.textContent = String(rank);
            el.appendChild(lab);
          }
          if (pieces[sq]) {
            const piece = document.createElement("div");
            piece.className = "wp-piece " + pieces[sq].cls;
            piece.draggable = state.interactive;
            piece.dataset.square = sq;
            piece.dataset.piece = pieces[sq].code;
            el.appendChild(piece);
          }
          board.appendChild(el);
        });
      });
      drawShapes();
    }

    function boardToFen() {
      const pieces = parseFen(state.fen);
      const files = ["a", "b", "c", "d", "e", "f", "g", "h"];
      const rows = [];
      for (let rank = 8; rank >= 1; rank--) {
        let row = "";
        let empty = 0;
        files.forEach((file) => {
          const piece = pieces[file + rank];
          if (!piece) {
            empty += 1;
            return;
          }
          if (empty) {
            row += String(empty);
            empty = 0;
          }
          const map = { wK: "K", wQ: "Q", wR: "R", wB: "B", wN: "N", wP: "P", bK: "k", bQ: "q", bR: "r", bB: "b", bN: "n", bP: "p" };
          row += map[piece.code] || "";
        });
        if (empty) row += String(empty);
        rows.push(row);
      }
      return rows.join("/") + " " + (state.turn || "w") + " - - 0 1";
    }

    function placeBrush(sq) {
      const pieces = parseFen(state.fen);
      if (!state.brush) {
        delete pieces[sq];
      } else {
        const cls = {
          K: "king white", Q: "queen white", R: "rook white", B: "bishop white", N: "knight white", P: "pawn white",
          k: "king black", q: "queen black", r: "rook black", b: "bishop black", n: "knight black", p: "pawn black",
        };
        const code = {
          K: "wK", Q: "wQ", R: "wR", B: "wB", N: "wN", P: "wP",
          k: "bK", q: "bQ", r: "bR", b: "bB", n: "bN", p: "bP",
        };
        pieces[sq] = { code: code[state.brush], cls: cls[state.brush] };
      }
      const files = ["a", "b", "c", "d", "e", "f", "g", "h"];
      const rows = [];
      for (let rank = 8; rank >= 1; rank--) {
        let row = "";
        let empty = 0;
        files.forEach((file) => {
          const piece = pieces[file + rank];
          if (!piece) {
            empty += 1;
            return;
          }
          if (empty) {
            row += String(empty);
            empty = 0;
          }
          const map = { wK: "K", wQ: "Q", wR: "R", wB: "B", wN: "N", wP: "P", bK: "k", bQ: "q", bR: "r", bB: "b", bN: "n", bP: "p" };
          row += map[piece.code] || "";
        });
        if (empty) row += String(empty);
        rows.push(row);
      }
      state.fen = rows.join("/") + " " + (state.turn || "w") + " - - 0 1";
      state.lastMove = null;
      render();
      if (typeof opts.onChange === "function") opts.onChange(state.fen);
    }

    function tryMove(from, to) {
      if (!state.interactive || !from || !to || from === to) return;
      const hit = state.legal.find((m) => m.from === from && m.to === to);
      if (!hit) {
        api.flashWrong();
        if (typeof opts.onIllegal === "function") opts.onIllegal(from, to);
        state.selected = null;
        render();
        return;
      }
      state.selected = null;
      if (typeof opts.onMove === "function") opts.onMove(from, to, hit.promotion || "q");
    }

    board.addEventListener("click", (e) => {
      if (!state.interactive && !state.editor) return;
      const sqEl = e.target.closest("[data-square]");
      if (!sqEl) return;
      const sq = sqEl.dataset.square;
      if (state.editor) {
        placeBrush(sq);
        return;
      }
      const pieces = parseFen(state.fen);
      if (state.selected && legalTargets(state.selected).indexOf(sq) !== -1) {
        tryMove(state.selected, sq);
        return;
      }
      const piece = pieces[sq];
      if (piece && piece.code[0] === state.playerColor) {
        state.selected = state.selected === sq ? null : sq;
        render();
      } else {
        state.selected = null;
        render();
      }
    });

    board.addEventListener("dragstart", (e) => {
      if (!state.interactive) return;
      const piece = e.target.closest(".wp-piece[data-square]");
      if (!piece) return;
      if ((piece.dataset.piece || "")[0] !== state.playerColor) {
        e.preventDefault();
        return;
      }
      state.selected = piece.dataset.square;
      e.dataTransfer.setData("text/plain", piece.dataset.square);
      e.dataTransfer.effectAllowed = "move";
      render();
    });
    board.addEventListener("dragover", (e) => e.preventDefault());
    board.addEventListener("drop", (e) => {
      e.preventDefault();
      const from = e.dataTransfer.getData("text/plain");
      const sqEl = e.target.closest("[data-square]");
      if (!sqEl) return;
      tryMove(from, sqEl.dataset.square);
    });

    const api = {
      setFen(fen, lastMove) {
        state.fen = fen;
        if (lastMove !== undefined) state.lastMove = lastMove;
        state.selected = null;
        render();
      },
      setLegal(moves) {
        state.legal = Array.isArray(moves) ? moves : [];
      },
      setInteractive(on) {
        state.interactive = !!on;
        render();
      },
      setOrientation(value) {
        state.orientation = value === "black" ? "black" : "white";
        render();
      },
      setPlayerColor(color) {
        state.playerColor = color === "b" ? "b" : "w";
      },
      setBrush(ch) {
        state.brush = ch || "";
      },
      setTurn(turn) {
        state.turn = turn === "b" ? "b" : "w";
        const parts = String(state.fen || "").split(" ");
        parts[1] = state.turn;
        state.fen = parts.join(" ");
        if (typeof opts.onChange === "function") opts.onChange(state.fen);
      },
      getFen() {
        return state.fen;
      },
      setShapes(shapes) {
        state.shapes = Array.isArray(shapes) ? shapes.slice() : [];
        drawShapes();
      },
      getShapes() {
        return state.shapes.slice();
      },
      setDrawable(on) {
        state.drawable = !!on;
        state.drawing = null;
      },
      clear() {
        state.fen = "8/8/8/8/8/8/8/8 " + (state.turn || "w") + " - - 0 1";
        render();
        if (typeof opts.onChange === "function") opts.onChange(state.fen);
      },
      flashWrong() {
        board.classList.remove("is-wrong");
        void board.offsetWidth;
        board.classList.add("is-wrong");
        window.setTimeout(() => board.classList.remove("is-wrong"), 650);
      },
    };
    render();
    return api;
  }

  global.createChessBoard = createChessBoard;
})(window);
