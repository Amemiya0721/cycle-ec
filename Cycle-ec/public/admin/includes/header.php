<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
$config = require __DIR__ . '/../../../config/config.php';
$baseUrl = $config['app']['base_url'];
$adminTitle = $adminTitle ?? '管理画面';
$activeMenu = $activeMenu ?? '';
$breadcrumbs = $breadcrumbs ?? [];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= htmlspecialchars($adminTitle, ENT_QUOTES, 'UTF-8') ?> | OVERHAUL 管理画面</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="<?= $baseUrl ?>assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body">
<header class="admin-header">
	<div class="container-fluid d-flex align-items-center justify-content-between px-3 px-lg-4">
		<a class="admin-brand" href="<?= $baseUrl ?>admin/index.php">OVERHAUL <span>管理画面</span></a>
		<div class="d-flex gap-2"><a class="btn btn-sm btn-outline-light" href="<?= $baseUrl ?>index.php">ショップを見る</a><a class="btn btn-sm btn-outline-warning" href="<?= $baseUrl ?>pages/logout.php">ログアウト</a></div>
	</div>
</header>
<div class="admin-shell">
	<?php require __DIR__ . '/sidebar.php'; ?>
	<main class="admin-main">
		<div class="admin-content">
			<?php if ($breadcrumbs): ?>
				<nav aria-label="breadcrumb" class="admin-breadcrumb mb-3">
					<ol class="breadcrumb mb-0">
						<?php foreach ($breadcrumbs as $breadcrumb): ?>
							<?php if (!empty($breadcrumb['url'])): ?>
								<li class="breadcrumb-item"><a href="<?= htmlspecialchars((string) $breadcrumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $breadcrumb['label'], ENT_QUOTES, 'UTF-8') ?></a></li>
							<?php else: ?>
								<li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars((string) $breadcrumb['label'], ENT_QUOTES, 'UTF-8') ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>
