(function () {
  const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";

  function loginUrl(role) {
    const next = role === "academy" || role === "admin" ? role : "student";
    // Always hit the PHP file — never the React /login route.
    return BASE + "/login.php?role=" + next;
  }

  function roleFromPath(path) {
    const clean = String(path || "").split("?")[0].replace(/\/+$/, "");
    if (/(^|\/)academy\/login$/.test(clean)) return "academy";
    if (/(^|\/)login\.php$/.test(clean) || /(^|\/)login$/.test(clean) || /(^|\/)register$/.test(clean)) {
      const q = String(path || "");
      const m = q.match(/[?&]role=(student|academy|admin)/);
      return m ? m[1] : "student";
    }
    return "";
  }

  function clearSession() {
    localStorage.removeItem("woodpecker_token");
    localStorage.removeItem("woodpecker_role");
    localStorage.removeItem("woodpecker_student");
    localStorage.removeItem("woodpecker_academy");
    sessionStorage.removeItem("woodpecker_admin_token");
  }

  function goUnifiedLogin(role) {
    window.location.replace(loginUrl(role || "student"));
  }

  function interceptLoginPath(url) {
    const href = String(url || "");
    if (!href || href === "#") return false;
    const path = href.indexOf("http") === 0 ? href.replace(/^https?:\/\/[^/]+/, "") : href;
    // Already targeting login.php — allow it.
    if (/\/login\.php(\?|$)/.test(path)) return false;
    const role = roleFromPath(path);
    if (!role) return false;
    goUnifiedLogin(role);
    return true;
  }

  // If the SPA already rendered an old login route, bounce immediately.
  (function bounceIfOldLogin() {
    const role = roleFromPath(location.pathname + location.search);
    if (!role) return;
    if (/\/login\.php$/.test(location.pathname.replace(/\/+$/, ""))) return;
    goUnifiedLogin(role);
  })();

  const pushState = history.pushState;
  history.pushState = function () {
    if (interceptLoginPath(arguments[2])) return;
    return pushState.apply(this, arguments);
  };
  const replaceState = history.replaceState;
  history.replaceState = function () {
    if (interceptLoginPath(arguments[2])) return;
    return replaceState.apply(this, arguments);
  };

  window.addEventListener("popstate", function () {
    const role = roleFromPath(location.pathname + location.search);
    if (role && !/\/login\.php$/.test(location.pathname.replace(/\/+$/, ""))) {
      goUnifiedLogin(role);
    }
  });

  document.addEventListener(
    "click",
    function (e) {
      const el = e.target.closest("button, a");
      if (!el) return;
      const text = (el.textContent || "").replace(/\s+/g, " ").trim().toLowerCase();
      const href = el.getAttribute("href") || "";
      if (text === "logout" || text === "log out" || text === "sign out") {
        e.preventDefault();
        e.stopPropagation();
        const role = localStorage.getItem("woodpecker_role") || roleFromPath(location.pathname) || "student";
        clearSession();
        goUnifiedLogin(role);
        return;
      }
      if (interceptLoginPath(href) || interceptLoginPath(el.getAttribute("to") || "")) {
        e.preventDefault();
        e.stopPropagation();
      }
    },
    true
  );

  function isStudent() {
    return localStorage.getItem("woodpecker_role") === "student" && !!localStorage.getItem("woodpecker_token");
  }

  function inject() {
    if (!isStudent()) return;
    if (document.querySelector("[data-wp-home-link]")) return;
    const links = document.querySelectorAll("a");
    let train = null;
    for (let i = 0; i < links.length; i++) {
      if ((links[i].textContent || "").trim() === "Train") {
        train = links[i];
        break;
      }
    }
    if (!train || !train.parentNode) return;
    if ((train.textContent || "").trim() === "Train") {
      train.textContent = "Woodpecker";
    }
    const home = document.createElement("a");
    home.href = BASE + "/home";
    home.textContent = "Home";
    home.className = train.className;
    home.setAttribute("data-wp-home-link", "1");
    train.parentNode.insertBefore(home, train);
  }

  const observer = new MutationObserver(inject);
  if (isStudent()) {
    observer.observe(document.documentElement, { childList: true, subtree: true });
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", inject);
    } else {
      inject();
    }
  }
})();
