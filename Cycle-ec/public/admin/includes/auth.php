<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$authConfig = require __DIR__ . '/../../../config/config.php';
$authBaseUrl = $authConfig['app']['base_url'];

if (($_SESSION['admin_verified'] ?? false) !== true || ($_SESSION['password_authenticated'] ?? false) !== true) {
    if (!empty($_SESSION['password_authenticated']) && !empty($_SESSION['admin_pending'])) {
        header('Location: ' . $authBaseUrl . 'pages/admin_verify.php');
    } else {
        header('Location: ' . $authBaseUrl . 'pages/login.php?error=admin_required');
    }
    exit;
}