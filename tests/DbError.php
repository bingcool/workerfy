<?php
/**
 * 本程序demo主要测试在父进程创建的Db实例，会不会在子进程复用同一个连接
 */
\Swoole\Runtime::enableCoroutine(true);

include "Db.php";

// 需要运行在协程中
Swoole\Timer::set([
    'enable_coroutine' => false,
]);

go(function () {
    $db = \Workerfy\Tests\Make::makeMysql();
    $query = $db->query("select sleep(3)");
    $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
    var_dump($res);
});

Swoole\Timer::tick(5*1000, function($timer_id) {
    // 单独一个mysql连接
    go(function () {
        $db = \Workerfy\Tests\Make::makeMysql();
        $query = $db->query("select sleep(3)");
        $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
        var_dump($res);
    });
});

$process = new Swoole\Process(function (Swoole\Process $worker) {
    // 子进程单独一个mysql连接，与父进程不互相影响，可以通过show processlist;或者show status like 'Threads%';看到不同连接
        var_dump('process start');
        while(1) {
            $db = \Workerfy\Tests\Make::makeMysql();
            $query = $db->query("select sleep(5)");
            $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
            var_dump($res);
        }
}, false, 2, true);

$pid = $process->start();

// 不要使用Swoole\Process::wait()， 否则会影响父进程与子进程复用同一个连接，不使用就不会影响
//Swoole\Process::wait();