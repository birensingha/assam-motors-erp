/* AM190 — NEW ERP sidebar scroll restoration v2.
   Fixes AM189: listen to the ACTUAL scrolling element (capturing scroll
   events on document) rather than guessing one CSS sidebar selector.
   State is per-tab, not sent to the server. Never scroll the main content.
*/
(function () {
  "use strict";
  if (window.__am190SidebarScrollLoaded) return;
  window.__am190SidebarScrollLoaded = true;

  var KEY = "am-staging-sidebar-scroll-am189";
  var saved = null, lastElement = null, userScrolled = false;
  var restoreComplete = false, startTime = Date.now();
  var candidates = [], lastScan = 0, timer = null;
  try {
    var data = JSON.parse(sessionStorage.getItem(KEY) || "null");
    if (data && Number.isFinite(data.top) && data.top >= 0) {
      saved = { top: Math.round(data.top), id: data.id || "", cls: data.cls || "" };
    }
  } catch (_) {}

  function matchSidebar(el) {
    if (!(el instanceof Element) || !el.isConnected) return false;
    var r = el.getBoundingClientRect();
    if (r.width < 110 || r.height < 100 || r.right < 110) return false;
    if (r.left > Math.max(55, window.innerWidth * 0.16)) return false;
    if (r.right > Math.min(460, window.innerWidth * 0.45)) return false;
    if (r.top > window.innerHeight * 0.65) return false;
    if (el.scrollHeight - el.clientHeight < 18) return false;
    var st = getComputedStyle(el);
    if (st.display === "none" || st.visibility === "hidden") return false;
    // A scroller is identified by its menu links and being in the narrow
    // LEFT strip, not by CSS overflow. Some layouts scroll through JS.
    var links = el.querySelectorAll("a[href]");
    if (links.length < 3) return false;
    return true;
  }

  function signature(el) {
    return {
      id: el.id || "",
      cls: typeof el.className === "string" ?
           el.className.trim().split(/\s+/).slice(0, 4).join(" ") : ""
    };
  }

  function scan(force) {
    if (!force && Date.now() - lastScan < 230) return candidates.filter(matchSidebar);
    lastScan = Date.now();
    var found = [], seen = new Set();
    var roots = document.querySelectorAll(
      '[id*="sidebar" i],[class*="sidebar" i],aside,nav,' +
      '[id*="side-nav" i],[class*="side-nav" i],' +
      '[id*="side-menu" i],[class*="side-menu" i],' +
      '[class*="menu-scroll" i],[class*="nav-scroll" i]'
    );
    function add(el) {
      if (!el || seen.has(el)) return;
      seen.add(el);
      if (matchSidebar(el)) found.push(el);
    }
    for (var i = 0; i < roots.length; i++) {
      var root = roots[i];
      add(root);
      var descendants = root.querySelectorAll("*");
      for (var j = 0; j < descendants.length; j++) add(descendants[j]);
    }
    if (!found.length) {
      // Fallback: unknown theme class names. Restrict to left strip and
      // only candidates that contain >=3 clickable menu links.
      var all = document.querySelectorAll("body *");
      for (var k = 0; k < Math.min(all.length, 7500); k++) add(all[k]);
    }
    candidates = found;
    return found;
  }

  function isLeftScroll(el) {
    if (!el || el === document || el === document.body ||
        el === document.documentElement) return false;
    if (matchSidebar(el)) return true;
    // Some menus place links inside an inner scrollable div with no
    // links of its own. Confirm containment within sidebar region.
    var r = el.getBoundingClientRect();
    if (r.left > Math.max(55, window.innerWidth * 0.16) ||
        r.right > Math.min(460, window.innerWidth * 0.45) ||
        el.scrollHeight - el.clientHeight < 18) return false;
    var a = el.parentElement;
    for (var steps = 0; a && steps < 8; steps++, a = a.parentElement) {
      if (/\b(sidebar|side-nav|side-menu|admin-menu|menu-scroll)\b/i.test(
          String(a.id || "") + " " + String(a.className || ""))) return true;
    }
    return false;
  }

  function record(el) {
    if (!el || !isLeftScroll(el)) return false;
    var value = Number(el.scrollTop);
    if (!Number.isFinite(value) || value < 0) return false;
    lastElement = el;
    var sig = signature(el);
    saved = {top:Math.max(0,Math.round(value)),id:sig.id,cls:sig.cls};
    try { sessionStorage.setItem(KEY, JSON.stringify(saved)); } catch (_) {}
    return true;
  }

  function best() {
    if (lastElement && lastElement.isConnected && isLeftScroll(lastElement)) {
      return lastElement;
    }
    var list = scan(false), bestEl = null, bestScore = -1e9;
    for (var i = 0; i < list.length; i++) {
      var el = list[i], sig = signature(el);
      var score = Math.max(0,Number(el.scrollTop) || 0) * 10 +
        Math.min(el.scrollHeight - el.clientHeight, 2000) / 20 +
        Math.min(el.querySelectorAll("a[href]").length, 100);
      if (saved && saved.id && sig.id === saved.id) score += 10000;
      if (saved && saved.cls && sig.cls === saved.cls) score += 2000;
      if (score > bestScore) {bestScore = score; bestEl = el;}
    }
    return bestEl;
  }

  function saveBeforeLeave() {
    var el = best();
    if (!el) return;
    // If a page's active-link script zeroed the sidebar after the previous
    // user scroll, don't replace a legitimate saved nonzero position with 0.
    var top = Number(el.scrollTop) || 0;
    if (!userScrolled && saved && saved.top > 0 && top === 0) return;
    record(el);
  }

  function restore() {
    if (!saved || userScrolled || !document.body) return;
    var list = scan(true);
    if (!list.length) return;
    // Prefer saved element identity; but also try other left-side scroll
    // containers in case the actual scrollbar belongs to an inner wrapper.
    list.sort(function (a,b) {
      function rank(el) {
        var s = signature(el), n = 0;
        if (saved.id && s.id === saved.id) n += 100;
        if (saved.cls && s.cls === saved.cls) n += 50;
        n += Math.min(el.querySelectorAll("a[href]").length, 60);
        return n;
      }
      return rank(b)-rank(a);
    });
    for (var i = 0; i < list.length; i++) {
      var el = list[i], maxTop = Math.max(0, el.scrollHeight - el.clientHeight);
      if (maxTop < 18) continue;
      var next = Math.min(saved.top, maxTop);
      if (Math.abs(el.scrollTop - next) > 1) el.scrollTop = next;
    }
  }

  // Capture works even though scroll events themselves don't bubble.
  document.addEventListener("scroll", function (event) {
    var el = event.target;
    if (!isLeftScroll(el)) return;
    if (userScrolled || restoreComplete) record(el);
  }, true);

  function onUserScroll(event) {
    var el = event.target;
    if (!el || !el.closest) return;
    var area = el.closest('aside,nav,[class*="side" i],[id*="side" i]');
    if (!area) return;
    if (!scan(false).some(function (candidate) {
      return candidate.contains(el) || el.contains(candidate);
    })) return;
    userScrolled = true;
    restoreComplete = true;
  }
  document.addEventListener("wheel", onUserScroll, {capture:true,passive:true});
  document.addEventListener("touchmove", onUserScroll, {capture:true,passive:true});
  // Dragging the sidebar scrollbar should also stop automatic restoration.
  document.addEventListener("pointerdown", function (e) {
    if (!e.target.closest || e.target.closest("a[href]")) return;
    onUserScroll(e);
  }, true);
  document.addEventListener("keydown", function (e) {
    if (["ArrowDown","ArrowUp","PageUp","PageDown","Home","End"].indexOf(e.key) >= 0)
      onUserScroll(e);
  }, true);

  // Save as EARLY as possible; don't wait for pagehide (it can run after
  // link state/active menu logic has already reset the sidebar scroll).
  document.addEventListener("pointerdown", function (event) {
    if (event.target.closest && event.target.closest("a[href]")) saveBeforeLeave();
  }, true);
  document.addEventListener("click", function (event) {
    if (event.target.closest && event.target.closest("a[href]")) saveBeforeLeave();
  }, true);
  window.addEventListener("pagehide", saveBeforeLeave, true);
  window.addEventListener("beforeunload", saveBeforeLeave, true);

  function boot() {
    restore();
    // Some sidebar CSS, fonts, Livewire or active-link highlighting are
    // applied after DOMContentLoaded. Keep restoration alive briefly.
    var ticks = 0;
    timer = setInterval(function () {
      ticks++;
      if (!userScrolled) restore();
      if (ticks >= 24 || userScrolled) {
        clearInterval(timer);
        timer = null;
        restoreComplete = true;
      }
    }, 125);
    requestAnimationFrame(function () {restore();requestAnimationFrame(restore);});
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded",boot,{once:true});
  } else {
    boot();
  }
  window.addEventListener("pageshow", function (ev) {
    if (ev.persisted && !userScrolled) restore();
  });
})();
