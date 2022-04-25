<?php

namespace Workerfy\Tests;

class Make
{
    public static function makeCommonDb()
    {
        $config = \Workerfy\ConfigLoader::getInstance()->getConfig()['mysql_db'];
        $db = new \Common\Library\Db\Mysql($config);
        return $db;
    }

    public static function makePredis()
    {
        $config = \Workerfy\ConfigLoader::getInstance()->getConfig()['predis'];
        $redis = new \Common\Library\Cache\Predis([
            'scheme' => $config['scheme'],
            'host'   => $config['host'],
            'port'   => $config['port'],
        ]);
        return $redis;
    }

    public static function makeRedis()
    {
        $config = \Workerfy\ConfigLoader::getInstance()->getConfig()['redis'];
        $redis = new \Common\Library\Cache\Redis();
        $redis->connect($config['host'],$config['port']);
        return $redis;
    }

    public static function makeQueueRedis()
    {
        $config = \Workerfy\ConfigLoader::getInstance()->getConfig()['redis_queue'];
        $redis = new \Common\Library\Cache\Redis();
        $redis->connect($config['host'],$config['port']);
        return $redis;
    }

    public static function makeMysql()
    {
        $config = \Workerfy\ConfigLoader::getInstance()->getConfig()['mysql_db'];
        $mysql = new \Common\Library\Db\Mysql($config);
        return $mysql;
    }

    public static function makeCurl()
    {
        $curlClient = new \Common\Library\HttpClient\CurlHttpClient();
        return $curlClient;
    }

}