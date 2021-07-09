<?php

namespace Workerfy\Tests;

class Make
{
    public static function makeCommonDb()
    {
        $config = \Workerfy\ConfigLoad::getInstance()->getConfig()['mysql_db'];
        $db = new \Common\Library\Db\Mysql($config);
        return $db;
    }
}