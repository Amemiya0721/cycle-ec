<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>管理画面 | OVERHAUL</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">OVERHAUL 管理画面</span><a class="btn btn-outline-light btn-sm" href="../index.php">サイトを見る</a></div></nav>
<main class="container py-5"><h1 class="h3 mb-4">管理メニュー</h1><div class="row g-3">
	<div class="col-md-4"><a class="card card-body text-decoration-none h-100" href="products/index.php"><h2 class="h5">商品管理</h2><p class="text-muted mb-0">商品の一覧・登録・編集</p></a></div>
	<div class="col-md-4"><a class="card card-body text-decoration-none h-100" href="category_add.php"><h2 class="h5">カテゴリ追加</h2><p class="text-muted mb-0">カテゴリ名・画像の登録</p></a></div>
	<div class="col-md-4"><a class="card card-body text-decoration-none h-100" href="orders.php"><h2 class="h5">注文管理</h2><p class="text-muted mb-0">注文内容・ステータスの確認</p></a></div>
</div></main>
</body>
</html>
<?php /*

ヘッダー

オーダー
残タスクを一番上に


問い合わせ
　返品　要望　その他いろいろ


商品管理
　追加　編集



　　

割引管理
　値下げ
　¥　→　￥
（確定申告　青色申告用に）


カテゴリ追加

ユーザー情報











フッター

*/