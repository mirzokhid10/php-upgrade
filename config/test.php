<?php
use yii\helpers\ArrayHelper;

$webConfig = require __DIR__ . '/web.php';
$testDb = require __DIR__ . '/test_db.php';
$params = require __DIR__ . '/params.php';


return ArrayHelper::merge($webConfig, [
    'id' => 'basic-tests',
    'language' => 'en-US',
    'components' => [
        'db' => $testDb,
        
        'mailer' => [
            'useFileTransport' => true,
        ],
        
        'smsComponent' => [
            'class' => 'app\components\SmsComponent', 
            // 'username' => 'fake_test_user',
            // 'password' => 'fake_test_password',
        ],
        
        'urlManager' => [
            'showScriptName' => false,
        ],
        
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
        ],
    ],
    // 'params' => $params,
    'params' => array_merge($params, ['sign_dir' => sys_get_temp_dir() . '/aktiv-test-signs/']),
]);
