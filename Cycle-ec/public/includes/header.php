<?php

$config = require __DIR__ . '/../../config/config.php';

$baseUrl = $config['app']['base_url'];

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($pageTitle ?? 'OVERHAUL', ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- 共通CSS -->
    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>assets/css/style.css">

    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>assets/css/nav.css">

    <?php if (!empty($pageCss)): ?>
        <!-- ページ固有CSS -->
        <link
            rel="stylesheet"
            href="<?= $baseUrl ?>assets/css/<?= htmlspecialchars($pageCss, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <?php if (isset($pageCssExtras) && is_array($pageCssExtras)): ?>
        <?php foreach ($pageCssExtras as $pageCssExtra): ?>
            <?php if (is_string($pageCssExtra)): ?>
                <link rel="stylesheet" href="<?= htmlspecialchars($pageCssExtra, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <!-- Cookie案内用CSS -->
    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>assets/css/cookie-banner.css">
    </head>
    <body>