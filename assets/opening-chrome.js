(function () {
  const BASE = location.pathname.indexOf("/wood") === 0 ? "/wood" : "";
  const onAcademy = location.pathname.indexOf("/academy") !== -1;
  const map = {
    logo: BASE + (onAcademy ? "/academy/home" : "/home"),
    "nav-home": BASE + (onAcademy ? "/academy/home" : "/home"),
    "nav-opening": BASE + "/opening.php",
    "nav-wood": BASE + "/",
    "nav-academy": BASE + "/academy.php",
    "nav-students": BASE + "/academy.php",
    "nav-openings": BASE + "/academy-openings.php",
    "nav-endgames": BASE + "/academy-endgames.php",
    "nav-endgame": BASE + "/endgame.php",
    back: null,
  };
  Object.keys(map).forEach((id) => {
    const el = document.getElementById(id);
    if (el && map[id]) el.setAttribute("href", map[id]);
  });
  const logout = document.getElementById("logout");
  if (logout) {
    logout.addEventListener("click", () => {
      const role = localStorage.getItem("woodpecker_role") || "student";
      localStorage.removeItem("woodpecker_token");
      localStorage.removeItem("woodpecker_role");
      localStorage.removeItem("woodpecker_student");
      localStorage.removeItem("woodpecker_academy");
      location.href = BASE + "/login.php?role=" + (role === "academy" || role === "admin" ? role : "student");
    });
  }
})();
