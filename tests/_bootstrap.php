<?php

// Location: tests/_bootstrap.php

defined('YII_ENV') or define('YII_ENV', 'test');
defined('YII_DEBUG') or define('YII_DEBUG', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$testDbConfig = require __DIR__ . '/../config/test_db.php';
$dsn = $testDbConfig['dsn'];

if (strpos(strtolower($dsn), 'test') === false) {
    die("\n❌ ERROR: CRITICAL SAFETY HALT!\nYour test configuration is pointing to a database that does not have 'test' in its name.\nTo prevent accidental data loss on live databases, tests will not run.\n\n");
}
