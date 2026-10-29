/* AM187: instant vendor search, live match highlight and keyboard selection.
   TextContent for all user-sourced data; only numeric vendor ids in navigation. */
(function () {
  "use strict";
  const input = document.getElementById("am187-vendor-search");
  const list = document.getElementById("am187-vendor-results");
  const form = document.getElementById("am187-search-form");
  if (!input || !list || !form) return;
  const url = input.getAttribute("data-search-url");
  const manage = input.getAttribute("data-manage-url");
  if (!url || !manage) return;
  let matches = [];
  let activeIndex = -1;
  let timer = null;
  let request = null;
  let sequence = 0;

  function clearResults() {
    matches = [];
    activeIndex = -1;
    list.replaceChildren();
    list.hidden = true;
    input.setAttribute("aria-expanded", "false");
    input.removeAttribute("aria-activedescendant");
  }

  function highlight(node, text, search) {
    const q = search.trim();
    if (!q) { node.textContent = text; return; }
    const lower = text.toLocaleLowerCase();
    const index = lower.indexOf(q.toLocaleLowerCase());
    if (index < 0) { node.textContent = text; return; }
    node.appendChild(document.createTextNode(text.slice(0, index)));
    const mark = document.createElement("mark");
    mark.textContent = text.slice(index, index + q.length);
    node.appendChild(mark);
    node.appendChild(document.createTextNode(text.slice(index + q.length)));
  }

  function select(item) {
    const id = Number(item.id);
    if (!Number.isSafeInteger(id) || id < 1) return;
    const target = new URL(manage, window.location.href);
    target.searchParams.set("edit", String(id));
    if (input.value.trim()) target.searchParams.set("q", input.value.trim());
    window.location.assign(target.href);
  }

  function focusIndex(next) {
    const options = list.querySelectorAll('[role="option"]');
    if (!options.length) return;
    activeIndex = ((next % options.length) + options.length) % options.length;
    options.forEach((opt, i) => {
      const selected = i === activeIndex;
      opt.classList.toggle("am187-active", selected);
      opt.setAttribute("aria-selected", selected ? "true" : "false");
    });
    const current = options[activeIndex];
    input.setAttribute("aria-activedescendant", current.id);
    current.scrollIntoView({block: "nearest"});
  }

  function showResults(rows) {
    list.replaceChildren();
    matches = Array.isArray(rows) ? rows : [];
    if (!matches.length) {
      const empty = document.createElement("div");
      empty.className = "am187-empty";
      empty.textContent = "No matching vendors";
      list.appendChild(empty);
      list.hidden = false;
      input.setAttribute("aria-expanded", "true");
      activeIndex = -1;
      return;
    }
    matches.forEach((v, i) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "am187-option";
      item.id = "am187-match-" + i;
      item.setAttribute("role", "option");
      item.setAttribute("aria-selected", "false");
      const label = document.createElement("span");
      label.className = "am187-option-name";
      highlight(label, String(v.name || ""), input.value);
      const meta = document.createElement("span");
      meta.className = "am187-option-meta";
      meta.textContent = [v.code, v.mobile].filter(Boolean).join(" · ");
      item.append(label, meta);
      item.addEventListener("mousedown", e => e.preventDefault());
      item.addEventListener("click", () => select(v));
      list.appendChild(item);
    });
    list.hidden = false;
    input.setAttribute("aria-expanded", "true");
    // Auto-highlight the first matching vendor as soon as user types.
    focusIndex(0);
  }

  async function load() {
    const id = ++sequence;
    if (request) request.abort();
    request = new AbortController();
    const target = new URL(url, window.location.href);
    target.searchParams.set("q", input.value.trim());
    try {
      const response = await fetch(target.href, {
        credentials: "same-origin",
        headers: {Accept: "application/json"},
        signal: request.signal,
      });
      if (!response.ok) throw new Error("Search failed");
      const json = await response.json();
      if (id !== sequence) return;
      showResults(json.results);
    } catch (err) {
      if (err.name === "AbortError" || id !== sequence) return;
      clearResults();
    }
  }
  input.addEventListener("input", () => {
    clearTimeout(timer);
    if (!input.value.trim()) {
      clearResults();
      return;
    }
    timer = setTimeout(load, 120);
  });
  input.addEventListener("focus", () => {
    if (input.value.trim()) load();
  });
  input.addEventListener("keydown", e => {
    if (e.key === "Escape") { clearResults(); return; }
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      if (list.hidden) { load(); return; }
      focusIndex(activeIndex + (e.key === "ArrowDown" ? 1 : -1));
    }
    if (e.key === "Enter" && !list.hidden && activeIndex >= 0 && matches[activeIndex]) {
      e.preventDefault();
      select(matches[activeIndex]);
    }
  });
  form.addEventListener("submit", e => {
    if (activeIndex >= 0 && matches[activeIndex] && !list.hidden) {
      e.preventDefault();
      select(matches[activeIndex]);
    }
  });
  document.addEventListener("pointerdown", e => {
    if (!form.contains(e.target)) clearResults();
  });
})();
