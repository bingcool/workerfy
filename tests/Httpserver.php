<?php

define('USER_NAME', 'bingcool');
define('PASSWORD', '123456');
define('SUPERVISOR_INCLUDE_PATH', '/Users/bingcool/wwwroot/workerfy/tests');

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
		$command = 'nohup php /home/wwwroot/workerfy/tests/Status/Master.php start -d >> /dev/null &';
		$ret = \Swoole\Coroutine::exec($command);
		var_dump($ret);
	    $response->end('start');
	}elseif ($action == 'stop') {
		$command = 'nohup php /home/wwwroot/workerfy/tests/Status/Master.php stop >> /dev/null &';
		$ret = \Swoole\Coroutine::exec($command);
		var_dump($ret);
	    $response->end('stop');
	}

});

$http->start();