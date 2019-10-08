<?php
Swoole\Runtime::enableCoroutine();

$serv = new \swoole_http_server("*", 9501, SWOOLE_PROCESS);

$serv->set([
    'worker_num' =>1
]);

$serv->on('request', function ($req, $resp) {
    // 创建一个channel
    if(isset($req->get['test'])) {
        $chan = new chan(2);
        go(function () use($chan) {
            sleep(2);//这里模拟请求mysql获取数据，阻塞2s，发生协程调度;
            $chan->push(['mysql'=>'mysql-test']);
        });

        go(function () use($chan) {
            sleep(1);//这里模拟请求redis获取数据，阻塞1s，发生协程调度;
            $chan->push(['redis'=>'redis-test']);
        });

        $result = [];
        for ($i = 0; $i < 2; $i++) {
            //这里将会已协程方式在等待数据返回
            //如果在规定时间内数据还没有返回，不会阻塞进程，进程可以继续处理http请求
            $result += $chan->pop(3);
        }
        $resp->end(json_encode($result));

    }else {
        $resp->end(json_encode(['no_block'=>1]));
    }

});

$serv->start();