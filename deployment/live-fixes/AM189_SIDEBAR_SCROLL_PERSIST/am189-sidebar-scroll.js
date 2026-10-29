/* AM189 — ASSAM MOTORS STAGING ERP: preserve LEFT sidebar scroll across page navigation.
   Session-scoped only (same browser tab), no server request and no user data.
   Finds the actual independently scrollable sidebar, not the main content panel.
*/
(function () {
  "use strict";
  if (window.__am189SidebarScrollLoaded) return;
  window.__am189SidebarScrollLoaded = true;

  var KEY = "am-staging-sidebar-scroll-am189";
  var sidebar = null, ready = false, userMoved = false;
  var target = null, restoreStarted = false, initialRaw = null;
  try {
    initialRaw = window.sessionStorage.getItem(KEY);
    var parsed = initialRaw ? JSON.parse(initialRaw) : null;
    if (parsed && Number.isFinite(parsed.top) && parsed.top >= 0) {
      target = parsed.top;
    }
  } catch (e) { target = null; }

  function visibleLeftPanel(el) {
    if (!el || !el.isConnected) return false;
    var box = el.getBoundingClientRect();
    if (box.width < 110 || box.height < 95) return false;
    if (box.left > Math.max(100, window.innerWidth * 0.29)) return false;
    if (box.width > Math.min(440, window.innerWidth * 0.70)) return false;
    var css = window.getComputedStyle(el);
    if (css.display === "none" || css.visibility === "hidden") return false;
    if (!/auto|scroll|overlay/i.test(css.overflowY)) return false;
    if ((el.scrollHeight - el.clientHeight) < 15) return false;
    return !!el.querySelector("a[href]");
  }

  function findSidebar() {
    var roots = document.querySelectorAll(
      '[data-sidebar-scroll], #sidebar, #erpSidebar, ' +
      '[class*="sidebar" i], [id*="sidebar" i], ' +
      'aside, [class*="side-nav" i], [class*="side-menu" i]'
    );
    var best = null, bestScore = -1;
    var seen = new Set();
    for (var r = 0; r < roots.length; r++) {
      var root = roots[r];
      var candidates = [root].concat(Array.from(root.querySelectorAll("*")).slice(0, 550));
      for (var i = 0; i < candidates.length; i++) {
        var el = candidates[i];
        if (seen.has(el)) continue;
        seen.add(el);
        if (!visibleLeftPanel(el)) continue;
        var linkCount = el.querySelectorAll("a[href]").length;
        var classes = String(el.id || "") + " " +
          String(typeof el.className === "string" ? el.className : "");
        var score = Math.min(linkCount, 60) +
          (/sidebar|side.nav|side.menu|menu.scroll|nav.scroll/i.test(classes) ? 40 : 0) +
          (/sidebar|side.nav|side.menu/i.test(String(root.className || "")) ? 20 : 0) +
          Math.min((el.scrollHeight - el.clientHeight) / 100, 25);
        if (score > bestScore) {
          bestScore = score;
          best = el;
        }
      }
    }
    return best;
  }

  function save() {
    if (!sidebar) return;
    try {
      window.sessionStorage.setItem(KEY, JSON.stringify({
        top: Math.max(0, Math.round(sidebar.scrollTop))
      }));
    } catch (e) { /* private-browsing storage can be disabled */ }
  }

  function restore() {
    if (!sidebar || target === null || userMoved) return;
    if ((sidebar.scrollHeight - sidebar.clientHeight) <= 0) return;
    var maxTop = Math.max(0, sidebar.scrollHeight - sidebar.clientHeight);
    sidebar.scrollTop = Math.min(target, maxTop);
  }

  function listenToUser() {
    if (!sidebar) return;
    ["wheel", "touchstart", "pointerdown", "keydown"].forEach(function (name) {
      sidebar.addEventListener(name, function () {
        userMoved = true;
        ready = true;
      }, {capture: true, passive: name !== "keydown"});
    });
    sidebar.addEventListener("scroll", function () {
      // Ignore layout-driven scroll and active-menu auto-scroll while
      // initial restoration is still in progress.
      if (ready || userMoved) save();
    }, {passive: true});
    sidebar.addEventListener("click", function (e) {
      if (e.target.closest("a[href]")) save();
    }, true);
    document.addEventListener("click", function (e) {
      if (sidebar && sidebar.contains(e.target) &&
          e.target.closest && e.target.closest("a[href]")) save();
    }, true);
    window.addEventListener("pagehide", save, {capture: true});
  }

  function install() {
    if (sidebar) return true;
    sidebar = findSidebar();
    if (!sidebar) return false;
    listenToUser();
    if (target !== null) restore();
    if (!restoreStarted) {
      restoreStarted = true;
      requestAnimationFrame(function () {
        restore();
        requestAnimationFrame(restore);
      });
      // Laravel layout styling/fonts and auto-scroll of active links
      // can finish after DOMContentLoaded. Reapply only until user interacts.
      [100, 300, 700].forEach(function (ms) {
        setTimeout(restore, ms);
      });
      setTimeout(function () {
        ready = true;
        save();
      }, 800);
    }
    return true;
  }

  function boot() {
    if (install()) return;
    // A sidebar rendered later by Livewire still gets the saved position.
    var attempts = 0;
    var timer = setInterval(function () {
      attempts++;
      if (install() || attempts >= 20) clearInterval(timer);
    }, 150);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, {once: true});
  } else {
    boot();
  }
  window.addEventListener("pageshow", function (e) {
    if (e.persisted && sidebar && !userMoved) restore();
  });
})();
