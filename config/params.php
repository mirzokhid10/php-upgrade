<?php

$param_local = require __DIR__ . '/params_local.php';

$default = [
    'adminEmail' => 'akbarshox90@gmail.com',
    'senderEmail' => 'akbarshox90@gmail.com',
    'senderName' => 'Aktiv Express',
];

return array_merge($default, $param_local);