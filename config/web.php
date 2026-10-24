<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'debug'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'modules' => [
        'debug' => [  // ← Add this module
            'class' => 'yii\debug\Module',
            'allowedIPs' => ['127.0.0.1', '::1'],
        ],
    ],
    'components' => [
        'smscomponent' => [
            'class' => 'app\components\SmsComponent',
        ],
        'request' => [
            'enableCookieValidation' => false,
            'enableCsrfValidation'   => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
            'formatters' => [
                \yii\web\Response::FORMAT_JSON => [
                    'class' => 'yii\web\JsonResponseFormatter',
                    'prettyPrint' => YII_DEBUG, // use "pretty" output in debug mode
                    'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ],
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableSession' => false,
            'loginUrl' => NULL

        ],
        'errorHandler' => [
            // 'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                [
                    'class' => 'yii\rest\UrlRule',

                    'controller' => [
                        'waybill',
                        'resource',
                    ]
                ],

                'POST login' => 'auth/login-courier',
                'POST auth-signup-phone' => 'auth/signup-phone',
                'POST auth-signup-code' => 'auth/signup-code',

                // Added Login APIs For Login
                'GET track-search' => 'track-search/index',
                'GET track-detail' => 'track-search/detail',

                // Added Track History URLs In Detailed Tracking Card
                'GET track-history' => 'track-history/index',

                // Added Calling Couriers APIs For Calling Courier Requests
                "GET calling-courier" => "calling-courier/cities",
                "POST calling-courier" => "calling-courier/create",

                'GET waybill-delivery' => 'waybill/delivery',
                'GET waybill-card-delivery' => 'waybill/card-delivery',
                'GET waybill-pickup' => 'waybill/pickup',
                'GET for-sign' => 'waybill/for-sign',
                'POST waybill-new' => 'waybill/new',
                'POST waybill-detail' => 'waybill/detail',
                'POST bot-waybill-detail' => 'waybill/bot-detail',
                'POST delivered' => 'waybill/delivered',
                'POST picked' => 'waybill/picked',
                'POST courier-picked' => 'waybill/courier-picked',

                // Resources
                'GET resource/regions' => 'resource/regions',
                'GET resource/cities' => 'resource/cities',
                'GET resource/clients' => 'resource/clients',
                'GET resource/cities-by-region' => 'resource/cities-by-region',
                'GET resource/status' => 'resource/status',
                'GET resource/parcel-types' => 'resource/parcel-types',
                'GET resource/parcel-lists' => 'resource/parcel-lists',
                'GET resource/return-types' => 'resource/return-types',
                'GET resource/status-labels' => 'resource/status-labels',
                'GET resource/version' => 'resource/version',

                // Files
                'POST waybill-sign-photo' => 'waybill/sign-photo',

                // Client API
                'POST new-parcel' => 'waybill/new-parcel',
                'POST current-status' => 'waybill/current-status',
                'POST accept' => 'waybill/accept',
                'POST calculate' => 'waybill/calculate',
                'POST ready-notification' => 'notification/ready',
                'POST st-package-created' => 'st/package-created',
                'POST confirm-delivery' => 'waybill/confirm-delivery',
                'POST handover-status' => 'waybill/handover-status',

                'POST waybill-accept-delivery' => 'waybill/accept-delivery',
                'POST waybill-comment' => 'waybill/comment',
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV && false) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['127.0.0.1', '::1', '192.168.*.*', '10.0.*.*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        'allowedIPs' => ['127.0.0.1', '::1', '192.168.*.*', '10.0.*.*'],
    ];
}

return $config;
