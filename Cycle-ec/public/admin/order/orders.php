<?php

declare(strict_types=1);

// 注文管理は admin/order/index.php に移動した。旧URLからの転送用。
header('Location: order/index.php', true, 301);
exit;
