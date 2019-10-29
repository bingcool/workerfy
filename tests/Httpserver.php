<?php

define('USER_NAME', 'bingcool');
define('PASSWORD', '123456');

if(PHP_OS != 'Darwin') {
    define("WWW_ROOT", '/home/wwwroot/workerfy/tests');
}else {
    define("WWW_ROOT", '/Users/bingcool/wwwroot/workerfy/tests');
}

$http = new Swoole\Http\Server("*", 9502);
$http->set([
    'worker_num' => 1
]);


$http->on('request', function ($request, $response) {
	if($request->server['request_uri'] == '/favicon.ico') {
		return;
	}

	$action = $request->get['action'];

	if($action == 'start') {
		$command = 'nohup php '.WWW_ROOT.'/Status/Master.php start >> /dev/null &';
		var_dump($command);
		$ret = \Swoole\Coroutine::exec($command);
		var_dump($ret);
	    $response->end('start');
	}elseif ($action == 'stop') {
		$command = 'nohup php '.WWW_ROOT.'/Status/Master.php stop >> /dev/null &';
		$ret = \Swoole\Coroutine::exec($command);
		var_dump($ret);
	    $response->end('stop');
	}else {
        @$response->end('please add param action');
    }

});

$http->start();