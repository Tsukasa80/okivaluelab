(() => {
  const slugify = (text) => {
    return String(text)
      .trim()
      .toLowerCase()
      .replace(/[\s\u3000]+/g, "-")
      .replace(/[^a-z0-9\u3040-\u30ff\u3400-\u9fff\uFF10-\uFF19\uFF41-\uFF5A\-]+/g, "")
      .replace(/-+/g, "-")
      .replace(/^-|-$/g, "");
  };

  const uniqueId = (base, used) => {
    let candidate = base || "section";
    let i = 2;
    while (used.has(candidate) || document.getElementById(candidate)) {
      candidate = `${base || "section"}-${i}`;
      i += 1;
    }
    used.add(candidate);
    return candidate;
  };

  const buildToc = () => {
    const toc = document.querySelector(".c-toc");
    const tocList = document.querySelector(".c-toc .wp-block-table-of-contents__list");
    const content = document.querySelector(".c-article-content");
    if (!toc || !tocList || !content) return;

    const headings = Array.from(content.querySelectorAll("h2, h3"))
      .filter((heading) => heading.textContent && heading.textContent.trim() !== "");

    if (headings.length === 0) {
      toc.style.display = "none";
      return;
    }

    const usedIds = new Set();
    tocList.innerHTML = "";

    for (const heading of headings) {
      const level = heading.tagName === "H3" ? 3 : 2;
      const text = heading.textContent.trim();
      const baseId = heading.id ? heading.id : slugify(text);
      if (!heading.id) {
        heading.id = uniqueId(baseId, usedIds);
      } else {
        usedIds.add(heading.id);
      }

      const li = document.createElement("li");
      li.className = "wp-block-table-of-contents__list-item";
      li.classList.add(level === 3 ? "is-level-3" : "is-level-2");

      const a = document.createElement("a");
      a.href = `#${heading.id}`;
      a.textContent = text;

      li.appendChild(a);
      tocList.appendChild(li);
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", buildToc, { once: true });
  } else {
    buildToc();
  }
})();

