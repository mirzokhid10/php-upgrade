<?php

require __DIR__ . '/../vendor/autoload.php';

defined('YII_DEBUG') or define('YII_DEBUG', filter_var(getenv('YII_DEBUG'), FILTER_VALIDATE_BOOLEAN));
defined('YII_ENV')   or define('YII_ENV', getenv('YII_ENV') ?: 'prod');

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../components/globals.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
