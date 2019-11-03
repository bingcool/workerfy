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

//日志错误目录
define('SYS_ERROR_LOG_ROOT', __DIR__);

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

        if(empty($action) || empty($script_filename) || empty($pid_filename)) {
            $handle->returnJson(1000, 'query params action or script_filename or pid_filename is missing');
            return false;
        }

        if($params !== null && !is_array($params)) {
            $handle->returnJson(1001, 'params must be a array type');
            return false;
        }

        $start_script_file_path = $handle->getStartScriptFile($script_filename);
        if(!file_exists($start_script_file_path)) {
            $handle->returnJson(1002, "{$start_script_file_path} 不存在");
            return false;
	    }

	    switch ($action) {
            case 'start':
                if(!$handle->isRunning($pid_filename)) {
                    $env_params = $handle->parseParams($params);
                    if(!empty($env_params)) {
                        $command = "nohup php {$start_script_file_path} start -d {$env_params} >> /dev/null &";
                    }else {
                        $command = "nohup php {$start_script_file_path} start -d >> /dev/null &";
                    }
                    $ret = $handle->startProcess($command);
                    if(is_array($ret) && $ret['code'] == 0) {
                        sleep(2);
                        $handle->returnJson(0, '进程初始化启动');
                    }else {
                        $handle->returnJson(1003, '进程初始化启动失败');
                    }
                }else {
                    $handle->returnJson(1004, '进程已启动，无需再启动');
                }
                break;
            case 'stop':
                if($handle->isRunning($pid_filename)) {
                    $command = "nohup php $start_script_file_path stop >> /dev/null &";
                    $ret = $handle->stopProcess($command);
                    if(is_array($ret) && $ret['code'] == 0) {
                        sleep(2);
                        $handle->returnJson(0, '进程已接收停止指令');
                    }else {
                        $handle->returnJson(1005, '进程停止失败');
                    }
                }else {
                    $handle->returnJson(1006, '不存在该进程或pid文件不存在');
                }
                break;
            case 'running':
                $isRunning = $handle->isRunning($pid_filename);
                $master_pid = isset($request->post['master_pid']) ? $request->post['master_pid'] : null;
                $running = false;
                if($master_pid !==null && !empty($master_pid)) {
                    if($master_pid > 0) {
                        $running = \Swoole\Process::kill($master_pid, 0);
                    }
                }
                
                if($isRunning) {
                    $handle->returnJson(0, '进程running中');
                }else {
                    if(isset($running) && $running == true) {
                        $handle->returnJson(0, '进程running中');
                    }else {
                        $handle->returnJson(1007, '进程是停止状态');
                    }
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
                $handle->returnJson(0, '进程状态信息', $status_info);
                break;
            case 'showlog':
                $line_contents = $handle->showLog($pid_filename);
                if(!empty($line_contents)) {
                    $handle->returnJson(0, '启动控制信息', $line_contents);
                }else {
                    $handle->returnJson(1008, '没有启动控制的log信息');
                }
        }

        return true;

	}catch(\Throwable $throwable) {   
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        $message = $throwable->getMessage();
        $error_msg = "【Error】{$message} in {$file} on line {$line}";     
	    if(defined('SYS_ERROR_LOG_ROOT')) {
            $date = date("Y_m_d", strtotime('now'));
            $pre_date = date("Y_m_d", strtotime('-1 day'));
            $pre_sys_error_log_file = rtrim(SYS_ERROR_LOG_ROOT, '/').'/sys_error_'.$pre_date.'.log';
            if(file_exits($pre_sys_error_log_file)) {
                unlink($pre_sys_error_log_file);
            }
            $sys_error_log_file = rtrim(SYS_ERROR_LOG_ROOT, '/').'/sys_error_'.$date.'.log';
            file_put_contents($sys_error_log_file, $error_msg);
        }

        $handle->returnJson(-1, $error_msg);
        return false;
	}

});


class ActionHandle {

    private $request;
    private $response;

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

    public function showLog(string $pid_filename, int $n = 100) {
        $ctl_log_file_path = $this->getCtlLogFile($pid_filename);
        $line_contents = $this->getLastLines($ctl_log_file_path, $n);
        return $line_contents;
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
        if(is_array($params)) {
            foreach($params as $name=>$value) {
                $name = trim($name);
                $value = trim($value);
                $env_params .= " -{$name}={$value}";
            }
        }
        return $env_params;
    }

    public function getLastLines(string $filename, int $count = 100) {
        if(!file_exists($filename) || !$fp = fopen($filename,'r')){
            return false;
        }
        $total_line = 0;
        if($fp){
            while(stream_get_line($fp,8192,"\n")){
                $total_line++;
            }
            fclose($fp);//关闭文件
        }
        $line_contents = [];
        if($total_line >= $count) {
            $start_line = $total_line - $count;
        }else {
            $start_line = 0;
            $count = $total_line;
        }
        $fp = new SplFileObject($filename, 'rb');
        $fp->seek($start_line); // 转到第N行, seek方法参数从0开始计数
        for($i = 0; $i <= $count; ++$i) {
            $line_contents[] = $fp->current(); // current()获取当前行内容
            $fp->next();// 下一行
        }

        return array_filter($line_contents);
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