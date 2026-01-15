(() => {
  const initFeaturedBlur = () => {
    const featured = document.querySelector(".ovl-post-single__featured");
    if (!featured) return;

    const img = featured.querySelector("img");
    if (!img) return;

    const apply = () => {
      const src = img.currentSrc || img.src;
      if (!src) return;

      const escaped = src.replace(/"/g, '\\"');
      featured.style.setProperty("--ovl-featured-blur-url", `url("${escaped}")`);
      featured.classList.add("has-ovl-featured-blur");
    };

    if (img.complete) {
      apply();
      return;
    }

    img.addEventListener("load", apply, { once: true });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initFeaturedBlur, { once: true });
  } else {
    initFeaturedBlur();
  }
})();
