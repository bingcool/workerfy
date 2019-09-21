<?php
namespace Workerfy\Tests;

class Db {

    public static $master_mysql = [];

    public static $slave_mysql = [];

    public static $master_redis = [];


    public static function getMasterMysql() {
        $cid = \Co::getCid();
        if(isset(self::$master_mysql[$cid]['master_mysql'])) {
            return self::$master_mysql[$cid]['master_mysql'];
        }else {
            try {
                $user = 'bingcool';
                $pass = '123456';
                $pdo = new \PDO('mysql:host=123.207.19.149;dbname=bingcool', $user, $pass);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                self::$master_mysql[$cid]['master_mysql'] = $pdo;
            }catch (PDOException $e) {
                print "Error!: " . $e->getMessage() . "<br/>";
            }
        }

    }
}