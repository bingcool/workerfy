<?php

if(WORKERFY_ENV == 'dev')
{
    $dc = include "dc-dev.php";
}else if(WORKERFY_ENV == 'test')
{
    $dc = include "dc-test.php";
}else
{
    $dc = include "dc-prd.php";
}

$config = [
    'mysql_db' => [
        // 服务器地址
        'hostname'        => $dc['mysql_db']['host'],
        // 数据库名
        'database'        => $dc['mysql_db']['database'],
        // 用户名
        'username'        => $dc['mysql_db']['username'],
        // 密码
        'password'        => $dc['mysql_db']['password'],
        // 端口
        'hostport'        => $dc['mysql_db']['port'],
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => [],
        // 数据库编码默认采用utf8
        'charset'         => $dc['mysql_db']['charset'],
        // 数据库表前缀
        'prefix'          => '',
        // fetchType
        'fetch_type' => \PDO::FETCH_ASSOC,
        // 是否需要断线重连
        'break_reconnect' => true,
        // 是否支持事务嵌套
        'support_savepoint' => false,
        // sql执行日志条目设置,不能设置太大,适合调试使用,设置为0，则不使用
        'spend_log_limit' => 30,
        // 是否开启dubug
        'debug' => 1
    ],

    'predis' => [
        'scheme' => $dc['predis']['scheme'],
        'host'   => $dc['predis']['host'],
        'port'   => $dc['predis']['port'],
    ],

    'redis' => [
        'host'   => $dc['redis']['host'],
        'port'   => $dc['redis']['port'],
        'timeout' => 2.0
    ],

    'redis_queue' => [
        'host'   => $dc['redis']['host'],
        'port'   => $dc['redis']['port'],
        'timeout' => 2.0
    ]
];

return $config;