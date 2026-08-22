<?php
$title = "サンプルページ";
?>
<?php
$config = require __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
        $config['user'],
        $config['pass']
    );

    echo "DB接続成功！";
} catch (PDOException $e) {
    echo "DB接続失敗: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
</head>
<body>
    <h1><?php echo $title; ?></h1>
    <p>現在時刻：<?php echo date("Y-m-d H:i:s"); ?></p>
</body>
</html>
