(function () {
  var BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  var API = BASE + "/api";
  var token = localStorage.getItem("woodpecker_token") || "";
  var role = localStorage.getItem("woodpecker_role") || "";

  if (!token || role !== "academy") {
    location.replace(BASE + "/login.php?role=academy");
    return;
  }

  var links = document.querySelectorAll("a[href^='/wood/']");
  for (var i = 0; i < links.length; i++) {
    var a = links[i];
    if (!BASE) {
      a.setAttribute("href", a.getAttribute("href").slice(5) || "/");
    }
  }

  var status = "all";
  var search = "";
  var coachFilter = "";
  var coaches = [];

  try {
    var academy = JSON.parse(localStorage.getItem("woodpecker_academy") || "null");
    if (academy && academy.name) {
      document.getElementById("academy-title").textContent = academy.name;
      document.getElementById("academy-meta").innerHTML =
        "Academy ID: <strong>#" +
        escapeHtml(academy.id) +
        "</strong> · @" +
        escapeHtml(academy.username || "") +
        " — use this ID with Forgot password.";
    }
  } catch (e) {}

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function api(path, options) {
    options = options || {};
    var headers = {
      "Content-Type": "application/json",
      Authorization: "Bearer " + token,
    };
    if (options.headers) {
      for (var key in options.headers) {
        if (Object.prototype.hasOwnProperty.call(options.headers, key)) {
          headers[key] = options.headers[key];
        }
      }
    }
    return fetch(API + path, {
      method: options.method || "GET",
      headers: headers,
      body: options.body,
    }).then(function (res) {
      return res.json().catch(function () {
        return {};
      }).then(function (data) {
        if (!res.ok) {
          throw new Error(data.error || "Request failed");
        }
        return data;
      });
    });
  }

  function formatMs(ms) {
    var total = Math.max(0, Math.floor(Number(ms || 0) / 1000));
    var h = Math.floor(total / 3600);
    var m = Math.floor((total % 3600) / 60);
    if (h > 0) return h + "h " + m + "m";
    return m + "m";
  }

  function showCreated(data) {
    var root = document.getElementById("modal-root");
    root.innerHTML =
      '<div class="modal-bg"><div class="modal">' +
      "<h2>Student login created</h2>" +
      "<p>Give these details to the player. They can sign in on the student login page.</p>" +
      "<p><strong>Name:</strong> " +
      escapeHtml(data.name) +
      "</p>" +
      "<p><strong>Username:</strong> " +
      escapeHtml(data.username) +
      "</p>" +
      "<p><strong>Password:</strong> " +
      escapeHtml(data.password) +
      "</p>" +
      "<p><strong>Student ID:</strong> #" +
      escapeHtml(data.id) +
      "</p>" +
      '<button type="button" class="btn" id="created-done">Done - refresh list</button>' +
      "</div></div>";
    document.getElementById("created-done").onclick = function () {
      root.innerHTML = "";
      loadStudents();
    };
  }

  function fillCoachSelects(selectedCreate) {
    var createSel = document.getElementById("student-coach");
    var filterSel = document.getElementById("filter-coach");
    var createVal = selectedCreate != null ? selectedCreate : createSel.value;
    var filterVal = filterSel.value || coachFilter;
    var n;

    createSel.innerHTML = '<option value="">Unassigned</option>';
    filterSel.innerHTML = '<option value="">All coaches</option>';
    for (n = 0; n < coaches.length; n++) {
      var o1 = document.createElement("option");
      o1.value = coaches[n];
      o1.textContent = coaches[n];
      createSel.appendChild(o1);
      var o2 = document.createElement("option");
      o2.value = coaches[n];
      o2.textContent = coaches[n];
      filterSel.appendChild(o2);
    }
    createSel.value = createVal || "";
    filterSel.value = filterVal || "";

    var list = document.getElementById("coach-list");
    if (!coaches.length) {
      list.innerHTML = '<span class="muted">No coaches yet - add one above.</span>';
    } else {
      var pills = [];
      for (n = 0; n < coaches.length; n++) {
        pills.push('<span class="coach-pill">' + escapeHtml(coaches[n]) + "</span>");
      }
      list.innerHTML = pills.join("");
    }
  }

  function coachOptionsHtml(selected) {
    var html = '<option value="">Unassigned</option>';
    for (var n = 0; n < coaches.length; n++) {
      var name = coaches[n];
      html +=
        '<option value="' +
        escapeHtml(name) +
        '"' +
        (name === selected ? " selected" : "") +
        ">" +
        escapeHtml(name) +
        "</option>";
    }
    return html;
  }

  function loadCoaches(selectAfter) {
    return api("/academy/coaches")
      .then(function (data) {
        coaches = data.coaches || [];
        fillCoachSelects(selectAfter || "");
      })
      .catch(function () {
        coaches = [];
        fillCoachSelects("");
      });
  }

  function updateSummary(counts) {
    document.getElementById("sum-total").textContent = String(counts.total || 0);
    document.getElementById("sum-active").textContent = String(counts.active || 0);
    document.getElementById("sum-inactive").textContent = String(counts.inactive || 0);
    document.getElementById("sum-showing").textContent = String(counts.showing || 0);
  }

  function renderStudents(payload) {
    var students = payload.students || [];
    var counts = payload.counts || {};
    updateSummary(counts);

    var host = document.getElementById("student-list");
    if (!students.length) {
      var total = counts.total || 0;
      if (total > 0) {
        host.innerHTML =
          '<p class="empty">No students match this filter. Click <strong>All</strong> above, or clear the coach/search filter. Total linked to this academy: ' +
          total +
          ".</p>";
      } else {
        host.innerHTML =
          '<p class="empty">No students are linked to <strong>this</strong> academy account yet. Existing players only appear for the academy they belong to. Create a student login here, or sign in as the academy that owns them.</p>';
      }
      return;
    }

    var rows = [];
    for (var i = 0; i < students.length; i++) {
      var s = students[i];
      var active = Number(s.is_active) === 1;
      var coach = s.coach_name || "";
      rows.push(
        '<tr data-id="' +
          s.id +
          '">' +
          "<td><strong><a href=\"" +
          BASE +
          "/academy/student/" +
          s.id +
          "\">" +
          escapeHtml(s.name) +
          "</a></strong><div class=\"muted\">@" +
          escapeHtml(s.username || "") +
          " · #" +
          escapeHtml(s.id) +
          "</div></td>" +
          "<td>" +
          '<select data-act="coach" aria-label="Assign coach">' +
          coachOptionsHtml(coach) +
          "</select>" +
          "</td>" +
          '<td><span class="badge ' +
          (active ? "on" : "off") +
          '">' +
          (active ? "Active" : "Inactive") +
          "</span></td>" +
          '<td class="muted">Cycles done: ' +
          escapeHtml(s.completed_cycles || 0) +
          "<br/>Training: " +
          formatMs(s.total_training_ms) +
          (s.active_cycles_label
            ? "<br/>Active: " + escapeHtml(s.active_cycles_label)
            : "") +
          "</td>" +
          '<td><div class="student-actions">' +
          '<a class="btn-link" href="' +
          BASE +
          "/academy/student/" +
          s.id +
          '">View</a>' +
          '<button type="button" data-act="password">Set password</button>' +
          '<button type="button" data-act="toggle">' +
          (active ? "Mark inactive" : "Mark active") +
          "</button>" +
          '<button type="button" class="danger" data-act="remove">Remove</button>' +
          "</div></td></tr>"
      );
    }

    host.innerHTML =
      '<table class="student-table"><thead><tr>' +
      "<th>Student</th><th>Coach</th><th>Status</th><th>Progress</th><th>Actions</th>" +
      "</tr></thead><tbody>" +
      rows.join("") +
      "</tbody></table>";
  }

  function loadStudents() {
    var params = new URLSearchParams();
    params.set("status", status);
    if (search.trim()) params.set("search", search.trim());
    if (coachFilter) params.set("coach", coachFilter);
    var host = document.getElementById("student-list");
    host.innerHTML = '<p class="empty">Loading students...</p>';
    return api("/academy/students?" + params.toString())
      .then(function (data) {
        renderStudents(data);
      })
      .catch(function (err) {
        host.innerHTML =
          '<p class="empty"><strong>Could not load students.</strong><br/>' +
          escapeHtml((err && err.message) || "Request failed") +
          "<br/><br/>Make sure you are logged in as the correct academy.</p>";
      });
  }

  document.getElementById("add-coach").addEventListener("submit", function (e) {
    e.preventDefault();
    var err = document.getElementById("coach-error");
    var btn = document.getElementById("coach-btn");
    var name = document.getElementById("coach-name").value.trim();
    err.style.display = "none";
    if (!name) return;
    btn.disabled = true;
    btn.textContent = "Adding...";
    api("/academy/coaches", {
      method: "POST",
      body: JSON.stringify({ name: name }),
    })
      .then(function () {
        document.getElementById("coach-name").value = "";
        return loadCoaches(name);
      })
      .then(function () {
        document.getElementById("student-coach").value = name;
      })
      .catch(function (ex) {
        err.textContent = (ex && ex.message) || "Could not add coach";
        err.style.display = "block";
      })
      .then(function () {
        btn.disabled = false;
        btn.textContent = "Add Coach";
      });
  });

  document.getElementById("create-student").addEventListener("submit", function (e) {
    e.preventDefault();
    var err = document.getElementById("create-error");
    var btn = document.getElementById("create-btn");
    err.style.display = "none";
    btn.disabled = true;
    btn.textContent = "Creating...";
    var name = document.getElementById("student-name").value.trim();
    var username = document.getElementById("student-username").value.trim();
    var password = document.getElementById("student-password").value;
    var coachName = document.getElementById("student-coach").value;
    api("/academy/students", {
      method: "POST",
      body: JSON.stringify({
        name: name,
        username: username,
        password: password,
        coachName: coachName,
      }),
    })
      .then(function (data) {
        document.getElementById("create-student").reset();
        fillCoachSelects("");
        showCreated({
          name: (data.student && data.student.name) || name,
          username: data.username || (data.student && data.student.username) || username,
          password: data.password || password,
          id: data.student && data.student.id,
        });
        loadStudents();
      })
      .catch(function (ex) {
        err.textContent = (ex && ex.message) || "Could not create student";
        err.style.display = "block";
      })
      .then(function () {
        btn.disabled = false;
        btn.textContent = "Create student login";
      });
  });

  var chips = document.querySelectorAll(".chip[data-status]");
  for (var c = 0; c < chips.length; c++) {
    chips[c].addEventListener("click", function () {
      var chip = this;
      var all = document.querySelectorAll(".chip[data-status]");
      for (var j = 0; j < all.length; j++) all[j].classList.remove("on");
      chip.classList.add("on");
      status = chip.getAttribute("data-status") || "active";
      loadStudents();
    });
  }

  document.getElementById("filter-coach").addEventListener("change", function (e) {
    coachFilter = e.target.value || "";
    loadStudents();
  });

  var searchTimer = null;
  document.getElementById("search").addEventListener("input", function (e) {
    search = e.target.value || "";
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadStudents, 250);
  });

  document.getElementById("student-list").addEventListener("change", function (e) {
    var sel = e.target.closest ? e.target.closest("select[data-act='coach']") : null;
    if (!sel) return;
    var row = sel.closest("tr[data-id]");
    if (!row) return;
    var id = row.getAttribute("data-id");
    var coachName = sel.value || "Unassigned";
    api("/academy/students/" + id, {
      method: "PATCH",
      body: JSON.stringify({ coachName: coachName }),
    }).catch(function (err) {
      alert((err && err.message) || "Could not update coach");
      loadStudents();
    });
  });

  document.getElementById("student-list").addEventListener("click", function (e) {
    var btn = e.target.closest ? e.target.closest("button[data-act]") : null;
    if (!btn) return;
    var row = btn.closest("tr[data-id]");
    if (!row) return;
    var id = row.getAttribute("data-id");
    var act = btn.getAttribute("data-act");
    var strong = row.querySelector("strong");
    var name = (strong && strong.textContent) || "student";

    if (act === "password") {
      var password = prompt("New password for " + name + " (min 4 characters)");
      if (!password) return;
      api("/academy/students/" + id, {
        method: "PATCH",
        body: JSON.stringify({ password: password }),
      })
        .then(function () {
          alert("Password updated. Give the new password to " + name + ".");
        })
        .catch(function (err) {
          alert((err && err.message) || "Action failed");
        });
      return;
    }

    if (act === "toggle") {
      var currentlyActive = btn.textContent.trim() === "Mark inactive";
      api("/academy/students/" + id, {
        method: "PATCH",
        body: JSON.stringify({ active: !currentlyActive }),
      })
        .then(function () {
          loadStudents();
        })
        .catch(function (err) {
          alert((err && err.message) || "Action failed");
        });
      return;
    }

    if (act === "remove") {
      if (!confirm("Remove " + name + " from this academy?")) return;
      api("/academy/students/" + id, { method: "DELETE" })
        .then(function () {
          loadStudents();
        })
        .catch(function (err) {
          alert((err && err.message) || "Action failed");
        });
    }
  });

  loadCoaches().then(loadStudents);
})();
