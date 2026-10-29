/* AM192 Sidebar Scroll — installed inline in ERP Blade, no public JS URL required.
   Scoped to the left-side navigation only; per-tab sessionStorage persistence. */
(function () {
  "use strict";
  if (window.__am192SidebarScrollActive) return;
  window.__am192SidebarScrollActive = true;

  var KEY = "am-staging-sidebar-scroll-am189";
  var wanted = null, sidebar = null, restoring = true, userScrolling = false;
  var nav = null, restoreTimer = null, lastManual = 0;
  try {
    var previous = JSON.parse(sessionStorage.getItem(KEY) || "null");
    if (previous && Number.isFinite(previous.top) && previous.top >= 0) {
      wanted = Math.round(previous.top);
    }
  } catch (_) {}

  function scroller() {
    sidebar = document.getElementById("erpSidebar") ||
      document.querySelector("aside.erp-sidebar");
    if (!sidebar) return null;
    var menu = sidebar.querySelector("nav.sidebar-nav") ||
      sidebar.querySelector(".sidebar-nav");
    // Select the REAL scrollable menu; only fall back to the outer
    // sidebar if the menu itself does not have independent overflow.
    if (menu && menu.scrollHeight - menu.clientHeight > 8) return menu;
    if (sidebar.scrollHeight - sidebar.clientHeight > 8) return sidebar;
    return menu || sidebar;
  }

  function remember() {
    var element = nav || scroller();
    if (!element) return;
    var position = Math.max(0, Math.round(element.scrollTop));
    // Avoid losing a saved position to a layout/active-menu reset during
    // first render, before the user scrolls.
    if (restoring && !userScrolling && wanted !== null &&
        position < wanted - 2) return;
    wanted = position;
    try {
      sessionStorage.setItem(KEY, JSON.stringify({top:position}));
    } catch (_) {}
  }

  function restore() {
    if (userScrolling || wanted === null) return;
    var element = scroller();
    if (!element) return;
    nav = element;
    var max = element.scrollHeight - element.clientHeight;
    if (max < 5) return;
    var pos = Math.min(Math.max(0,wanted),max);
    if (Math.abs(element.scrollTop - pos) > 1) {
      var previousBehavior = element.style.scrollBehavior;
      element.style.scrollBehavior = "auto";
      element.scrollTop = pos;
      element.style.scrollBehavior = previousBehavior;
    }
  }

  function userIsScrolling(e) {
    var element = nav || scroller();
    if (!element) return;
    if (e.type === "keydown" &&
        ["ArrowUp","ArrowDown","PageUp","PageDown","Home","End"].indexOf(e.key) < 0)
      return;
    if (e.target !== element && !element.contains(e.target)) return;
    // Clicking a sidebar link is navigation, not a scroll gesture.
    if (e.type === "pointerdown" && e.target.closest &&
        e.target.closest("a[href]")) return;
    userScrolling = true;
    restoring = false;
    lastManual = Date.now();
    if (restoreTimer) { clearInterval(restoreTimer); restoreTimer = null; }
  }

  document.addEventListener("wheel",userIsScrolling,{capture:true,passive:true});
  document.addEventListener("touchmove",userIsScrolling,{capture:true,passive:true});
  document.addEventListener("pointerdown",userIsScrolling,true);
  document.addEventListener("keydown",userIsScrolling,true);

  document.addEventListener("scroll",function(e) {
    var element = nav || scroller();
    if (!element || e.target !== element) return;
    if (!restoring || userScrolling) remember();
  },true);

  function captureClick(e) {
    var link = e.target.closest ? e.target.closest("a[href]") : null;
    if (!link) return;
    var element = nav || scroller();
    if (element && element.contains(link)) remember();
  }
  document.addEventListener("pointerdown",captureClick,true);
  document.addEventListener("click",captureClick,true);
  window.addEventListener("pagehide",function() {
    // Do not overwrite restored scroll position during navigation.
    if (!restoring) remember();
  },true);

  function boot() {
    nav = scroller();
    restore();
    var count = 0;
    restoreTimer = setInterval(function() {
      count++;
      if (!userScrolling) restore();
      if (count >= 18 || userScrolling) {
        clearInterval(restoreTimer);
        restoreTimer = null;
        restoring = false;
        if (!userScrolling) remember();
      }
    },100);
    requestAnimationFrame(function() {
      restore();
      requestAnimationFrame(restore);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded",boot,{once:true});
  } else boot();
  window.addEventListener("pageshow",function(ev) {
    if (ev.persisted && !userScrolling) restore();
  });
})();
