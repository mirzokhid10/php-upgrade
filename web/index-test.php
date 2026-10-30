<?php
// Entry script for ACCEPTANCE TESTS ONLY (test config, test database).
// Start it with:  php -S 127.0.0.1:8060 -t web web/index-test.php
// Under php-fpm / Apache it answers 404, so it is harmless if deployed by accident.

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

define('YII_DEBUG', true);
define('YII_ENV', 'test');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../components/globals.php';

$config = require __DIR__ . '/../config/test.php';

(new yii\web\Application($config))->run();