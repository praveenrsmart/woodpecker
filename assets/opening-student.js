(function () {
  const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  const API = BASE + "/api";
  const token = localStorage.getItem("woodpecker_token") || "";
  const role = localStorage.getItem("woodpecker_role");
  if (!token || role !== "student") {
    location.replace(BASE + "/login.php?role=student");
    return;
  }
  document.getElementById("back").setAttribute("href", BASE + "/home");

  function esc(v) {
    return String(v ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  async function api(path) {
    const res = await fetch(API + path, { headers: { Authorization: "Bearer " + token } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Could not load openings");
    return data;
  }

  function boardUrl(openingId, chapterId, mode) {
    return BASE + "/opening-board.php?opening=" + openingId + "&chapter=" + chapterId + "&mode=" + mode;
  }

  function renderList(openings) {
    const app = document.getElementById("app");
    if (!openings.length) {
      app.innerHTML = '<div class="card"><p class="empty">Your academy has not assigned any openings yet.</p></div>';
      return;
    }
    const groups = [
      { key: "white", title: "White openings" },
      { key: "black", title: "Black openings" },
    ];
    app.innerHTML = groups
      .map((g) => {
        const items = openings.filter((o) => o.colorGroup === g.key);
        if (!items.length) return "";
        return `<div style="margin-bottom:22px">
          <h2 class="group-title">${g.title}</h2>
          ${items
            .map(
              (o) => `<div class="section-card">
                <h3>${esc(o.name)}</h3>
                <div class="meta">
                  ${o.chapterCount} chapter${o.chapterCount === 1 ? "" : "s"} ·
                  Test taken: <span class="stat">${o.testTaken ? "Yes" : "No"}</span> ·
                  Chapters tested: ${o.chaptersTested}/${o.chapterCount} ·
                  Passed: ${o.chaptersPassed} ·
                  Wrong moves: ${o.wrongMoves}
                </div>
                <div class="row" style="margin-top:14px">
                  <a class="btn" href="${BASE}/opening.php?id=${o.id}">Open chapters</a>
                </div>
              </div>`
            )
            .join("")}
        </div>`;
      })
      .join("");
  }

  function renderOpening(opening) {
    const app = document.getElementById("app");
    document.getElementById("back").setAttribute("href", BASE + "/opening.php");
    document.querySelector("h1").textContent = opening.name;
    document.querySelector(".sub").textContent =
      (opening.colorGroup === "black" ? "Black" : "White") +
      " repertoire · Test taken: " +
      (opening.testTaken ? "Yes" : "No") +
      " · " +
      opening.chaptersTested +
      "/" +
      opening.chapterCount +
      " chapters tested";
    app.innerHTML = (opening.chapters || [])
      .map(
        (c) => `<div class="section-card">
          <h3>${esc(c.title)}</h3>
          <div class="meta">${c.plyCount} moves · Test taken: ${c.testTaken ? "Yes" : "No"}${
            c.lastCompletedAt ? " · Last wrong moves: " + c.lastWrongMoves : ""
          }</div>
          <div class="row" style="margin-top:14px">
            <a class="btn ghost" href="${boardUrl(opening.id, c.id, "study")}">Study</a>
            <a class="btn" href="${boardUrl(opening.id, c.id, "test")}">Take test</a>
          </div>
        </div>`
      )
      .join("") || '<div class="card"><p class="empty">No chapters in this opening yet.</p></div>';
  }

  const params = new URLSearchParams(location.search);
  const id = Number(params.get("id") || 0);
  if (id) {
    api("/me/openings/" + id)
      .then(renderOpening)
      .catch((err) => {
        document.getElementById("app").innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
      });
  } else {
    api("/me/openings")
      .then((data) => renderList(data.openings || []))
      .catch((err) => {
        document.getElementById("app").innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
      });
  }
})();
