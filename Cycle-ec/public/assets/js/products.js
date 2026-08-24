/* ==========================================================
   products.js
   -----------------------------------------------------------
   検索状態はあくまでURL（＝サーバー側で$_GETから復元されるもの）が正。
   このJSはURLの組み立てを補助するだけで、検索条件をJS変数として
   保持・管理することはしない（ページを直接開いた場合と挙動が
   ズレることを防ぐため）。

   やること：
     ・カテゴリ / 並び替えのプルダウンを変更した瞬間に、
       URLSearchParamsで安全にURLを組み立てて即座に遷移する
     ・キーワード欄はEnter/送信ボタンで通常のGET送信に任せる
       （入力の都度遷移すると邪魔なため）
     ・ページ送り等の<a>リンクはサーバー側で生成済みのURLを
       そのまま使うので、ここでは何もしない
   ========================================================== */

(() => {
  const form = document.getElementById("productSearchForm");
  if (!form) return;

  const categorySelect = document.getElementById("category");
  const sortSelect = document.getElementById("sort");

  /**
   * 現在のURLをベースに、指定したキーだけを差し替えたURLを返す。
   * ページ番号は検索条件が変わったら1ページ目に戻すため除去する。
   */
  function buildUrl(overrides) {
    const params = new URLSearchParams(window.location.search);

    Object.entries(overrides).forEach(([key, value]) => {
      if (value === null || value === "") {
        params.delete(key);
      } else {
        params.set(key, value);
      }
    });

    params.delete("page"); // 条件変更時はページングをリセット

    const query = params.toString();
    return `products.php${query ? `?${query}` : ""}`;
  }

  function navigateWith(overrides) {
    window.location.href = buildUrl(overrides);
  }

  categorySelect?.addEventListener("change", () => {
    navigateWith({ category: categorySelect.value });
  });

  sortSelect?.addEventListener("change", () => {
    navigateWith({ sort: sortSelect.value });
  });

  // キーワードはフォーム送信（Enterキー or 検索ボタン）で通常どおりGET送信
  // → ブラウザが自動的にURLエンコードしてURLへ反映するため、ここでは何もしない
})();
