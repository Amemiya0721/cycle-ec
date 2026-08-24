/* ==========================================================
   fuwatto-animation.js
   -----------------------------------------------------------
   既存のショップサイト（navy/redテーマ）のクラス構造に合わせて、
   HTMLを書き換えずに「ほわっと表示」を後付けするスクリプト。

   ・TARGET_MAP に既存クラス名を登録するだけで対象を追加できる
   ・type: "single"  → 要素単体にアニメーションを付与
   ・type: "group"   → 親要素配下の子要素をスタガー表示
   ========================================================== */

(() => {
  const DEFAULT_STAGGER_MS = 80;

  /* ---------- サイト固有：対象要素の定義 ----------
     size: "sm" | "md" | "lg" は fuwa--sm / fuwa--lg のクラスに対応
     （未指定は既定の.fuwaのみ＝mdサイズ相当）
  --------------------------------------------------- */
  const SINGLE_TARGETS = [
    { selector: ".hero-inner h1", size: "lg" },
    { selector: ".hero-inner p", size: "md", delay: 120 },
    { selector: ".hero-inner .hero-cta", size: "md", delay: 220 },
    { selector: ".section-head", size: "md" },
    { selector: ".cat-title", size: "md" },
    { selector: ".cond-title", size: "md" },
  ];

  const GROUP_TARGETS = [
    { groupSelector: ".cat-grid, .row:has(.cat-card)", itemSelector: ".cat-card", stagger: 80 },
    { groupSelector: ".prod-grid, .row:has(.prod-card)", itemSelector: ".prod-card", stagger: 70 },
    { groupSelector: ".cond-list, .row:has(.cond-item)", itemSelector: ".cond-item", stagger: 90 },
    { groupSelector: ".info-list, .row:has(.info-item)", itemSelector: ".info-item", stagger: 90 },
    { groupSelector: ".footer-grid", itemSelector: ".footer-col", stagger: 60 },
  ];

  /** :has() 未対応ブラウザ向けのフォールバック探索 */
  function findGroupContainer(target) {
    const selectors = target.groupSelector.split(",").map((s) => s.trim());
    for (const sel of selectors) {
      try {
        const found = document.querySelector(sel);
        if (found) return found;
      } catch (e) {
        /* :has() 未対応環境ではここでエラーになるため無視して次へ */
      }
    }
    // フォールバック：itemSelectorの最初の要素の直近の親を代表コンテナとみなす
    const firstItem = document.querySelector(target.itemSelector);
    return firstItem ? firstItem.parentElement : null;
  }

  function tagSingleTargets() {
    SINGLE_TARGETS.forEach(({ selector, size, delay }) => {
      document.querySelectorAll(selector).forEach((el) => {
        el.classList.add("fuwa");
        if (size === "lg") el.classList.add("fuwa--lg");
        if (size === "sm") el.classList.add("fuwa--sm");
        if (delay) el.style.transitionDelay = `${delay}ms`;
      });
    });
  }

  function tagGroupTargets() {
    GROUP_TARGETS.forEach((target) => {
      const container = findGroupContainer(target);
      if (!container) return;
      const items = container.querySelectorAll(target.itemSelector);
      items.forEach((item, index) => {
        item.classList.add("fuwa-item");
        item.style.transitionDelay = `${index * (target.stagger || DEFAULT_STAGGER_MS)}ms`;
      });
    });
  }

  function initObserver() {
    const targets = document.querySelectorAll(".fuwa, .fuwa-item");
    if (!targets.length) return;

    const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (prefersReduced) {
      targets.forEach((el) => el.classList.add("is-visible"));
      return;
    }

    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          obs.unobserve(entry.target);
        });
      },
      {
        threshold: 0.15,
        rootMargin: "0px 0px -8% 0px",
      }
    );

    targets.forEach((el) => observer.observe(el));
  }

  document.addEventListener("DOMContentLoaded", () => {
    tagSingleTargets();
    tagGroupTargets();
    initObserver();
  });
})();
