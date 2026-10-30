<?php
// Boots a Yii app inside the test process, only to reach the TEST database for seeding and cleanup.
// The HTTP requests themselves are served by `php -S ... web/index-test.php`.

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/vendor/yiisoft/yii2/Yii.php';
require_once $root . '/components/globals.php';

new yii\web\Application(require $root . '/config/test.php');