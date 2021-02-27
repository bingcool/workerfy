<?php
namespace Workerfy\Tests\Binlog;

use Workerfy\AbstractProcess;
use GuzzleHttp\Client;
use Yurun\Util\Swoole\Guzzle\SwooleHandler;
use GuzzleHttp\DefaultHandler;

class Worker extends AbstractProcess {

    public $url;

    public function init()
    {
        $this->url = 'http://127.0.0.1:9502/index/testJson';

    }

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        $Pdo = \Workerfy\Tests\DB::getMasterMysql();

        $stmt = $Pdo->query('show master status');

        $rows = $stmt->fetchAll(); //获取所有
        var_dump($rows);

    }
}