<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'name' => '<b>Doc</b>Z',
    'language' => 'th-TH',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'view' => [
                'theme' => [
                    'pathMap' => [
                    '@app/views' => '@app/themes/adminlte'
                    ],
                ],
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '99299929',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
            // 'class' => 'yii\caching\MemCache',
            // 'servers' => [
            //     [
            //         'host' => 'server1',
            //         'port' => 11211,
            //         'weight' => 60,
            //     ],
            //     [
            //         'host' => 'server2',
            //         'port' => 11211,
            //         'weight' => 40,
            //     ],
            // ],
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            // 'enableAutoLogin' => true,
            'enableAutoLogin' => false,
            'authTimeout' => 3600, // auth expire 
        ],
        'session' => [
            'class' => 'yii\web\Session',
            'cookieParams' => ['httponly' => true, 'lifetime' => 3600 * 4],
            'timeout' => 3600, //session expire
            'useCookies' => true
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
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
            'showScriptName' => false,
            'rules' => [
                'index' => 'site/index',
                'login' => 'site/login',
                'logout' => 'site/logout',
                'about' => 'site/about',
                'signup' => 'profile/create_profile',
                'profile' => 'profile/index',
            ],
        ],
        
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'generators' => [ // HERE
            'crud' => [
                'class' => 'yii\gii\generators\crud\Generator',
                'templates' => [
                    'adminlte' => '@vendor/dmstr/yii2-adminlte-asset/gii/templates/crud/simple',
                ]
            ]
        ],
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
