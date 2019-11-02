<?php
defined('USER_NAME') or define('USER_NAME', 'workerfy');
defined('PASSWORD') or define('PASSWORD', '123456');

if(PHP_OS != 'Darwin') {
    defined('PROJECT_ROOT') or define('PROJECT_ROOT', '/home/wwwroot/workerfy/tests');
    defined('PID_ROOT') or define('PID_ROOT', '/home/wwwroot/workerfy/tests');
}else {
    defined('PROJECT_ROOT') or define('PROJECT_ROOT', '/Users/bingcool/wwwroot/workerfy/tests');
    defined('PID_ROOT') or define('PID_ROOT', '/Users/bingcool/wwwroot/workerfy/tests');
}

// 根据实际设置
define('PID_FILE_ROOT', PID_ROOT);

$http = new Swoole\Http\Server("*", 9502);

$http->set([
    'worker_num' => 1
]);

$http->on('start', function($server) {
    (PHP_OS != 'Darwin') && swoole_set_process_name('php-http-master-process');
});

$http->on('managerStart', function($server) {
    (PHP_OS != 'Darwin') && swoole_set_process_name('php-http-manager-process');
});

$http->on('workerStart',function($server, int $worker_id) {
    (PHP_OS != 'Darwin') && swoole_set_process_name('php-http-worker-process@'.$worker_id);
});

$http->on('request', function ($request, $response) use($http) {
	try {
        if($request->server['request_uri'] == '/favicon.ico') {
            return false;
        }

        $handle = new ActionHandle($request, $response);

        $action = isset($request->post['action']) ? $request->post['action'] : null;
        $script_filename = isset($request->post['script_filename']) ? trim($request->post['script_filename']) : null;
        $pid_filename = isset($request->post['pid_filename']) ? trim($request->post['pid_filename']) : null;
        $params = isset($request->post['params']) ? $request->post['params'] : null;

        if(empty($action) || empty($script_filename) || $pid_filename) {
            $handle->returnJson(-1,'query params action or script_filename or pid_filename is missing');
            return false;
        }

        if($params !== null && !is_array($params)) {
            $handle->returnJson(-1,'params must be a array type');
            return false;
        }

        $start_script_file_path = $handle->getStartScriptFile($script_filename);
        if(!file_exists($start_script_file_path)) {
            $handle->returnJson(-1,"{$start_script_file_path} 不存在");
            return false;
	    }

	    switch ($action) {
            case 'start':
                if(!$handle->isRunning($pid_filename)) {
                    $env_params = $this->parseParams($params);
                    if(!empty($env_params)) {
                        $command = "nohup php {$start_script_file_path} start -d {$env_params} >> /dev/null &";
                    }else {
                        $command = "nohup php {$start_script_file_path} start -d >> /dev/null &";
                    }
                    $ret = $handle->startProcess($command);
                    if(is_array($ret) && $ret['code'] == 0) {
                        sleep(2);
                        $handle->returnJson(0,'进程初始化启动');
                    }else {
                        $handle->returnJson(-1,'进程初始化启动失败');
                    }
                }else {
                    $handle->returnJson(-1,'进程已启动，无需再启动');
                }
                break;
            case 'stop':
                if($handle->isRunning($pid_filename)) {
                    $command = "nohup php $start_script_file_path stop >> /dev/null &";
                    $ret = $handle->stopProcess($command);
                    if(is_array($ret) && $ret['code'] == 0) {
                        sleep(2);
                        $handle->returnJson(0,'进程已接收停止指令');
                    }else {
                        $handle->returnJson(-1,'进程停止失败');
                    }
                }else {
                    $handle->returnJson(-1,'不存在该进程或pid文件不存在');
                }
                break;
            case 'running':
                $isRunning = $handle->isRunning($pid_filename);
                if($isRunning) {
                    $handle->returnJson(0,'进程running中');
                }else {
                    $handle->returnJson(-1,'进程是停止状态');
                }
                break;
            case 'status':
                $status_msg = $handle->processStatus($pid_filename);
                $status_info = json_decode($status_msg, true);
                if(isset($status_info['master']['master_pid']) && is_numeric($status_info['master']['master_pid'])) {
                    $master_pid = (int) $status_info['master']['master_pid'];
                    if(\Swoole\Process::kill($master_pid, 0)) {
                        $status_info['master']['running_status'] = 1;
                    }else {
                        $status_info['master']['running_status'] = 0;
                        if(isset($status_info['master']['children_process']) && !empty($status_info['master']['children_process'])) {
                            $status_info['master']['children_process'] = [];
                        }
                    }
                }
                $handle->returnJson(0,'进程状态信息', $status_info);
                break;
        }

        return true;

	}catch(\Throwable $throwable) {

	}


});


class ActionHandle {

    public $request;
    public $response;

    public function __construct($request, $response) {
        $this->request = $request;
        $this->response = $response;
    }

    public function startProcess(string $command) {
        $ret = \Co::exec($command);
        return $ret;
    }

    public function stopProcess(string $command) {
        $ret = \Co::exec($command);
        return $ret;
    }

    public function processStatus(string $pid_filename) {
        $status_msg = '{}';
        $status_file_path = $this->getStatusFile($pid_filename);
        if(file_exists($status_file_path)) {
            $status_msg = file_get_contents($status_file_path);
        }
        return $status_msg;
    }

    public function isRunning(string $pid_filename) {
        $pid_file_path = $this->getPidFile($pid_filename);
        if(file_exists($pid_file_path)) {
            $master_pid = file_get_contents($pid_file_path);
            if(is_numeric($master_pid)) {
                $master_pid = (int) $master_pid;
                if(\Swoole\Process::kill($master_pid, 0)) {
                    return true;
                }else {
                    return false;
                }
            }else {
                return false;
            }
        }
    }

    public function getStartScriptFile(string $script_filename) {
        $start_script_file_path = rtrim(PROJECT_ROOT, '/').'/'.trim($script_filename);
        return $start_script_file_path;
    }

    public function getCtlLogFile(string $pid_filename) {
        $pid_filename = str_replace('.pid','.log', $pid_filename);
        $ctl_log_file_path = rtrim(PID_FILE_ROOT, '/').'/'.trim($pid_filename);
        return $ctl_log_file_path;
    }

    public function getPidFile(string $pid_filename) {
        $pid_filename = str_replace('.pid','.pid', $pid_filename);
        $pid_file_path = rtrim(PID_FILE_ROOT, '/').'/'.trim($pid_filename);
        return $pid_file_path;
    }

    public function getStatusFile(string $pid_filename) {
        $pid_filename = str_replace('.pid','.status', $pid_filename);
        $status_file_path = rtrim(PID_FILE_ROOT, '/').'/'.trim($pid_filename);
        return $status_file_path;
    }

    public function parseParams($params) {
        $env_params = '';
        foreach($params as $name=>$value) {
            $name = trim($name);
            $value = trim($value);
            $env_params .= " -{$name}={$value}";
        }
        return $env_params;
    }

    public function returnJson($ret = 0, $msg = '', $data = []) {
        $result = [
            'ret' => $ret,
            'msg' => $msg,
            'data' => $data
        ];
        $this->response->header('Content-Type', 'application/json; charset=UTF-8');
        $json_str = json_encode($result, JSON_UNESCAPED_UNICODE);
        $this->response->write($json_str);
    }

}

$http->start();