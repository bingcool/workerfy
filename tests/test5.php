<?php
$http = new swoole_http_server("0.0.0.0", 9502);

$http->set([
    'reactor_num' => 2,
    'worker_num' => 1,
    'max_request' => 100000,
    'task_tmpdir' => '/dev/shm',
    // http无状态，使用1或3
    //'dispatch_mode' => 7,
    'reload_async' => true,
    'daemonize' => 0,
    'enable_coroutine' => 1,
]);

$http->on('workerStart', function ($serv, $id)
{
    //var_dump($serv);
});

$http->on('request', function ($request, Swoole\Http\Response $response) use ($http) {
    if($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
        $response->end();
        return;
    }

    $controller = new Controller();
    $controller->test();

    $response->write("hello!");
});

$http->start();

class BController {
    public function __destruct()
    {
        var_dump('destruct_cid:'.\co::getCid());
    }
}



class Controller extends BController {

    public function test()
    {
        var_dump('worker_cid:'.\co::getCid());

        go(function() {
            var_dump('go_cid:'.\co::getCid());
        });

    }
}