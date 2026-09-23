(function () {
  var BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  var API = BASE + "/api";
  var token = localStorage.getItem("woodpecker_token") || "";
  var role = localStorage.getItem("woodpecker_role");
  if (!token || role !== "student") {
    location.replace(BASE + "/login.php?role=student");
    return;
  }
  document.getElementById("back").setAttribute("href", BASE + "/home");

  function esc(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function api(path) {
    return fetch(API + path, { headers: { Authorization: "Bearer " + token } }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) throw new Error(data.error || "Could not load endgames");
        return data;
      });
    });
  }

  function boardUrl(categoryId, chapterId, mode, level) {
    return (
      BASE +
      "/endgame-board.php?category=" +
      categoryId +
      "&chapter=" +
      chapterId +
      "&mode=" +
      mode +
      (level ? "&level=" + encodeURIComponent(level) : "")
    );
  }

  function renderList(categories) {
    var app = document.getElementById("app");
    if (!categories.length) {
      app.innerHTML = '<div class="card"><p class="empty">Your academy has not assigned any endgames yet.</p></div>';
      return;
    }
    var html = "";
    for (var i = 0; i < categories.length; i++) {
      var c = categories[i];
      var green = c.chaptersPassed || 0;
      var total = c.chapterCount || 0;
      html +=
        '<div class="section-card ' + (green && green === total ? "is-green" : "") + '">' +
        "<h3>" + esc(c.name) + (green && green === total ? ' <span class="green">Completed</span>' : "") + "</h3>" +
        '<div class="meta">' +
        c.subcategoryCount + " subcategor" + (c.subcategoryCount === 1 ? "y" : "ies") + " · " +
        total + " position" + (total === 1 ? "" : "s") + " · Green: <span class=\"stat\">" + green + "/" + total + "</span>" +
        "</div>" +
        '<div class="row" style="margin-top:14px">' +
        '<a class="btn" href="' + BASE + "/endgame.php?id=" + c.id + '">Open positions</a>' +
        "</div></div>";
    }
    app.innerHTML = html;
  }

  function renderCategory(category) {
    var app = document.getElementById("app");
    document.getElementById("back").setAttribute("href", BASE + "/endgame.php");
    document.querySelector("h1").textContent = category.name;
    document.querySelector(".sub").textContent =
      "Green tests: " +
      (category.chaptersPassed || 0) +
      "/" +
      (category.chapterCount || 0) +
      " · Practice first, then take the test with no mistakes.";
    var blocks = "";
    var subs = category.subcategories || [];
    for (var s = 0; s < subs.length; s++) {
      var sub = subs[s];
      var cards = "";
      var chapters = sub.chapters || [];
      for (var c = 0; c < chapters.length; c++) {
        var ch = chapters[c];
        var testHref = boardUrl(category.id, ch.id, "test");
        var practiceHref = boardUrl(category.id, ch.id, "practice");
        cards +=
          '<div class="section-card ' + (ch.passed ? "is-green" : "") + '">' +
          "<h3>" + esc(ch.title) + (ch.passed ? ' <span class="green">Green</span>' : "") + "</h3>" +
          '<div class="meta">' + esc(ch.goalLabel) + " · You play " + (ch.playerColor === "b" ? "Black" : "White") +
          (ch.practiceTaken ? " · Practiced" : "") +
          (ch.testTaken && !ch.passed ? " · Test taken" : "") +
          "</div>" +
          '<div class="row" style="margin-top:14px">' +
          '<a class="btn ghost" href="' + practiceHref + '">Practice vs engine</a>' +
          (ch.practiceTaken
            ? '<a class="btn" href="' + testHref + '">Take test</a>'
            : '<span class="btn ghost is-off">Take test (practice first)</span>') +
          "</div></div>";
      }
      blocks +=
        '<div style="margin-bottom:22px">' +
        '<h2 class="group-title">' + esc(sub.name) + "</h2>" +
        (cards || '<p class="empty">No positions in this subcategory yet.</p>') +
        "</div>";
    }
    app.innerHTML = blocks || '<div class="card"><p class="empty">No positions in this category yet.</p></div>';
  }

  var params = new URLSearchParams(location.search);
  var id = Number(params.get("id") || 0);
  if (id) {
    api("/me/endgames/" + id)
      .then(renderCategory)
      .catch(function (err) {
        document.getElementById("app").innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
      });
  } else {
    api("/me/endgames")
      .then(function (data) {
        renderList(data.categories || []);
      })
      .catch(function (err) {
        document.getElementById("app").innerHTML = '<p class="msg error">' + esc(err.message) + "</p>";
      });
  }
})();
