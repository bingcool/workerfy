<?php
return [
    'mysql_db' => [
        // 主机
        'host' => '127.0.0.1',
        // 端口
        'port' => 3306,
        // 数据库名
        'database' => 'bingcool',
        // 用户名
        'username' => 'root',
        // 密码
        'password' => '123456',
        // 数据库编码默认采用utf8
        'charset'  => 'utf8mb4',
    ],

    'predis' => [
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ],

    'redis' => [
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]
];