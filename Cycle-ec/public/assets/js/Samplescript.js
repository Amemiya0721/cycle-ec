// ==================================================
// DATA (ダミー / 後でAPIに置換可能な構造)
// ==================================================
const CATEGORIES = [
  { key: 'road', jp: 'ロードバイク', en: 'ROAD BIKE', icon: '🚴', color: 'linear-gradient(160deg,#4a4d54,#181a1d)' },
  { key: 'wheel', jp: 'ホイール', en: 'WHEEL', icon: '⚙', color: 'linear-gradient(160deg,#3f4147,#141517)' },
  { key: 'component', jp: 'コンポーネント', en: 'COMPONENT', icon: '🔧', color: 'linear-gradient(160deg,#43464d,#16181b)' },
  { key: 'parts', jp: 'パーツ', en: 'PARTS', icon: '⚡', color: 'linear-gradient(160deg,#4a4d54,#181a1d)' },
  { key: 'wear', jp: 'ウェア・用品', en: 'WEAR / ACCESSORY', icon: '⛑', color: 'linear-gradient(160deg,#6a6d72,#2a2c2f)' },
];

const NEW_PRODUCTS = [
  { name: 'SHIMANO 105 RD-R7000 リアディレイラー', price: 8800, rank: 'A', cat: 'component' },
  { name: 'MAVIC KSYRIUM ELITE クリンチャー ホイールセット', price: 28000, rank: 'S', cat: 'wheel' },
  { name: 'SHIMANO ULTEGRA FC-R8000 クランクセット', price: 16800, rank: 'B', cat: 'component' },
  { name: 'CANYON ULTIMATE CF SLX フレームセット XSサイズ', price: 98000, rank: 'A', cat: 'road' },
  { name: 'selle ITALIA SLR カーボンサドル', price: 3980, rank: 'JUNK', cat: 'parts' },
];

const DOWN_PRODUCTS = [
  { name: 'SHIMANO 105 FC-R7000 クランクセット', old: 12000, price: 9800, cat: 'component' },
  { name: 'SHIMANO 105 CS-R7000 スプロケット', old: 8800, price: 6980, cat: 'component' },
  { name: 'FULCRUM RACING 5 ホイールセット', old: 18000, price: 15000, cat: 'wheel' },
  { name: 'DEDA SUPERLEGGERO ステム 110mm', old: 6800, price: 4980, cat: 'parts' },
  { name: 'SHIMANO PRO VIBE カーボンハンドル', old: 16000, price: 12800, cat: 'parts' },
];

// ==================================================
// RENDER
// ==================================================
function yen(n) {
  return '¥' + n.toLocaleString('ja-JP');
}

function renderCategories() {
  const grid = document.getElementById('cat-grid');
  if (grid.children.length > 0) {
    return;
  }
  grid.innerHTML = CATEGORIES.map(c => `
    <div class="col-6 col-lg">
      <a href="pages/products.php?category=${c.key}" class="cat-card" style="--cat-bg:${c.color}" data-cat="${c.key}">
        <div class="cat-icon">${c.icon}</div>
        <div class="cat-jp">${c.jp}</div>
        <div class="cat-en">${c.en}</div>
      </a>
    </div>`).join('');
}

function renderNew() {
  const grid = document.getElementById('new-grid');
  if (grid.children.length > 0) {
    return;
  }
  grid.innerHTML = NEW_PRODUCTS.map(p => `
    <div class="col-6 col-md-4 col-lg">
      <a href="pages/products.php?keyword=${encodeURIComponent(p.name)}" class="prod-card" data-cat="${p.cat}">
        <div class="prod-thumb">
          <span class="badge-new">NEW</span>
          <span class="rank-badge">${p.rank}${p.rank !== 'JUNK' ? 'ランク' : ''}</span>
          <span class="thumb-icon">🚲</span>
        </div>
        <div class="prod-info">
          <div class="prod-name">${p.name}</div>
          <div class="prod-price">${yen(p.price)}<span class="tax">税込</span></div>
        </div>
      </a>
    </div>`).join('');
}

function renderDown() {
  const grid = document.getElementById('down-grid');
  if (grid.children.length > 0) {
    return;
  }
  grid.innerHTML = DOWN_PRODUCTS.map(p => {
    const off = Math.round((1 - p.price / p.old) * 100);
    return `
    <div class="col-6 col-md-4 col-lg">
      <a href="pages/products.php?keyword=${encodeURIComponent(p.name)}" class="prod-card" data-cat="${p.cat}">
        <div class="prod-thumb">
          <span class="badge-down">PRICE DOWN</span>
          <span class="off-badge">${off}%<br>OFF</span>
          <span class="thumb-icon">🚲</span>
        </div>
        <div class="prod-info">
          <div class="prod-name">${p.name}</div>
          <div><span class="price-old">${yen(p.old)}</span><span class="price-new">${yen(p.price)}</span><span class="tax">税込</span></div>
        </div>
      </a>
    </div>`;
  }).join('');
}

renderCategories();
renderNew();
renderDown();

// ==================================================
// CART
// ==================================================
let cartCount = 0;
function updateCartBadge() {
  document.getElementById('cart-count-top').textContent = cartCount;
}
updateCartBadge();

// ==================================================
// CATEGORY NAVIGATION
// ==================================================
document.body.addEventListener('click', e => {
  const el = e.target.closest('[data-cat]');
  if (el) {
    const cat = el.getAttribute('data-cat');
    if (el.classList.contains('cat-card')) {
      e.preventDefault();
      window.location.href = `pages/products.php?category=${encodeURIComponent(cat)}`;
    }
  }
});

// ==================================================
// NEWSLETTER
// ==================================================
document.getElementById('newsletter-form').addEventListener('submit', e => {
  e.preventDefault();
  alert('メールマガジンに登録しました（仮）');
  e.target.reset();
});

// ==================================================
// MOBILE NAV: close offcanvas after selecting a link
// ==================================================
const mobileNavEl = document.getElementById('mobileNav');
mobileNavEl.querySelectorAll('a').forEach(a => {
  a.addEventListener('click', () => {
    const oc = bootstrap.Offcanvas.getInstance(mobileNavEl);
    if (oc) oc.hide();
  });
});
