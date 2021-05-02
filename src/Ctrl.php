<?php
/**
+----------------------------------------------------------------------
| Daemon and Cli model about php process worker
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
 */

include_once __DIR__.'/WorkerfyConst.php';
include_once __DIR__.'/EachColor.php';

if(!version_compare(phpversion(),'7.1.0', '>=')) {
    write_info("【Warning】php version require >= php7.1+");
    exit(0);
}

if(!version_compare(swoole_version(),'4.4.5','>=')) {
    write_info("【Warning】swoole version require >= 4.4.5");
    exit(0);
}

if(!defined('START_SCRIPT_FILE')) {
    define("START_SCRIPT_FILE", $_SERVER['PWD'].'/'.$_SERVER['SCRIPT_FILENAME']);
    if(!is_file(START_SCRIPT_FILE))
    {
        write_info("【Warning】Please define Constants START_SCRIPT_FILE");
        exit(0);
    }
}

if(!defined('PID_FILE')) {
    write_info("【Warning】Please define Constants PID_FILE");
    exit(0);
}

if(!is_dir($pid_file_root = pathinfo(PID_FILE,PATHINFO_DIRNAME))) {
    mkdir($pid_file_root,0777,true);
}

if(!defined('CTL_LOG_FILE')) {
    define('CTL_LOG_FILE', getCtlLogFile());
    if(!file_exists(CTL_LOG_FILE)) {
        touch(CTL_LOG_FILE);
        chmod(CTL_LOG_FILE, 0666);
    }
}

if(!defined('STATUS_FILE')) {
    define('STATUS_FILE', str_replace('.pid', '.status', PID_FILE));
    if(!file_exists(STATUS_FILE)) {
        touch(STATUS_FILE);
        chmod(STATUS_FILE, 0666);
    }
}

$command = $_SERVER['argv'][1] ?? START;

function parseCliEnvParams() {
    $cli_params = [];
    $argv_arr = array_splice($_SERVER['argv'], 2);
    array_reduce($argv_arr, function($result, $item) use(&$cli_params) {
        if(in_array($item, ['-d', '-D'])) {
            putenv('daemon=1');
        }else {
            $item = ltrim($item, '--');
            list($param, $value) = explode('=', $item);
            if($param && $value)
            {
                $cli_params[$param] = $value;
            }
        }
    });

    return $cli_params;
}

$cli_params = parseCliEnvParams();

switch($command) {
    case START :
        start($cli_params);
        break;
    case STOP :
        stop($cli_params);
        break;
    case RELOAD :
        reload($cli_params);
        break;
    case RESTART:
        restart($cli_params);
        break;
    case STATUS :
        status($cli_params);
        break;
    case PIPE :
        pipe($cli_params);
        break;
    case ADD :
        add($cli_params);
        break;
    case REMOVE :
        remove($cli_params);
        break;
    default :
        write_info("【Warning】You must use 【start, stop, reload, status, pipe, add, remove】command");
        exit(0);
}

function start($cli_params) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            unlink(PID_FILE);
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
        if(\Swoole\Process::kill($master_pid, 0)) {
            write_info("【Warning】Master process has started, you can not start again");
            exit(0);
        }
    }

    $param_keys = array_keys($cli_params);
    foreach($cli_params as $param=>$value)
    {
        putenv("{$param}={$value}");
    }
    putenv('workerfy_cli_params='.json_encode($param_keys));
    // 定义是否守护进程模式
    defined('IS_DAEMON') or define('IS_DAEMON', getenv('daemon') ? true : false);

    // 通过cli命令行设置worker_num
    $worker_num = (int)getenv('worker_num');
    if(isset($worker_num) && $worker_num > 0) {
        define("WORKER_NUM", $worker_num);
    }

    write_info("【Info】Master && Children process ready to start, please wait a time ......",'green');

}

function stop($cli_params) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid) && $master_pid > 0) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("【Info】Master and children process start to stop, please wait a time",'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 30) {
                break;
            }
            sleep(1);
        }
        write_info("【Info】Master and children process has stopped",'green');
    }else {
        write_info("【Warning】Master Process of Pid={$master_pid} is not exist");
    }
    exit(0);
}

function reload($cli_params) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGUSR2);
        if($res) {
            write_info("【Info】Children process start to reload, please wait a time", 'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 10) {
                break;
            }
            sleep(1);
        }
        write_info("【Info】Children process has reloaded", 'green');
    }else {
        write_info("【Warning】Master Process of Pid={$master_pid} is not exist");
    }
    exit(0);
}

function restart($cli_params) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid) && $master_pid > 0) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("【Info】Master and children process start to stop, please wait a time",'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 30) {
                break;
            }
            sleep(1);
        }
        write_info("【Info】Master and children process has stopped",'green');
    }

    write_info("【Info】Master and children ready to restart, please wait a time",'green');

}

function status($cli_params) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
    }

    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("【Warning】Master Process of Pid={$master_pid} is not running");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    $ctl_pipe_file = getCtlPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("【Warning】 Master process is not enable cli pipe, so can not show status");
        exit(0);
    }
    $pipe = fopen($pipe_file,'r+');
    $pipe_msg = json_encode(['status', $ctl_pipe_file, ''], JSON_UNESCAPED_UNICODE);
    if(file_exists($ctl_pipe_file)) {
        unlink($ctl_pipe_file);
    }
    posix_mkfifo($ctl_pipe_file, 0777);
    $ctl_pipe = fopen($ctl_pipe_file, 'w+');
    stream_set_blocking($ctl_pipe, false);
    \Swoole\Timer::after(3000, function() {
        \Swoole\Event::exit();
    });
    \Swoole\Event::add($ctl_pipe, function() use($ctl_pipe) {
        $msg = fread($ctl_pipe, 8192);
        write_info($msg,'green');
        \Swoole\Event::exit();
    });
    sleep(1);
    fwrite($pipe, $pipe_msg);
    \Swoole\Event::wait();
    fclose($ctl_pipe);
    fclose($pipe);
    unlink($ctl_pipe_file);
    exit(0);
}

function pipe($cli_params) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
    }
    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("【Warning】Master Process of Pid={$master_pid} is not exist");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("【Warning】 Master process is not enable cli pipe");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("【Warning】 Get file flock failed");
        exit(0);
    }
    $msg = $cli_params['msg'] ?? '';
    if($msg) {
        write_info("【Info】Start write message={$msg} to master",'green');
        fwrite($pipe, $msg);
    }else {
        write_info("【Warning】Please use pipe --msg=xxxxx");
    }
    fclose($pipe);
    exit(0);
}

function add($cli_params, int $wait_time = 5) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】Master pid is invalid");
            exit(0);
        }
    }
    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("【Warning】 Master Process of Pid={$master_pid} is not exist");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("【Warning】 Master process is not enable cli pipe, can not add process");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("【Warning】 Get file flock failed");
        exit(0);
    }
    $name = $cli_params['name'] ?? '';
    $num = $cli_params['num'] ?? 1;
    $pipe_msg = json_encode(['add' , $name, $num], JSON_UNESCAPED_UNICODE);
    if($name) {
        write_info("【Info】 Master process start to create dynamic process, please wait a time(about {$wait_time}s)",'green');
        fwrite($pipe, $pipe_msg);
    }else {
        write_info("【Warning】 Please use pipe --name=xxxxx -num=1");
    }
    flock($pipe, LOCK_UN);
    fclose($pipe);
    sleep($wait_time);
    write_info("【Info】 Dynamic process add successful, you can show status to see", 'green');
    exit(0);
}

function remove($cli_params, int $wait_time = 5) {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("【Warning】 Master pid is invalid");
            exit(0);
        }
    }
    if(!\Swoole\Process::kill($master_pid, 0)) {
        write_info("【Warning】 Master Process of Pid={$master_pid} is not exist");
        exit(0);
    }

    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("【Warning】 Master process is not enable cli pipe, can not remove process");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("【Warning】 Get file flock failed");
        exit(0);
    }
    $name = $cli_params['name'] ?? '';
    $num = $cli_params['num'] ?? 1;
    $pipe_msg = json_encode(['remove' , $name, $num], JSON_UNESCAPED_UNICODE);
    if(isset($name)) {
        write_info("【Info】 Master process start to remova all dynamic process, please wait a time(about {$wait_time}s)",'green');
        fwrite($pipe, $pipe_msg);
    }else {
        write_info("【Warning】 Please use pipe --name=xxxxx");
    }
    fclose($pipe);
    sleep($wait_time);
    write_info("【Info】 All process_name={$name} of dynamic process be removed, you can show status to see", 'green');
    exit(0);
}

function write_info($msg, $foreground = "red", $background = "black") {
    include_once __DIR__.'/EachColor.php';
    // Create new Colors class
    static $colors;
    if(!isset($colors)) {
        $colors = new \Workerfy\EachColor();
    }
    $formatMsg = "--------------{$msg} --------------";
    echo $colors->getColoredString($formatMsg, $foreground, $background) . "\n\n";
    if(defined("CTL_LOG_FILE")) {
        if(defined('MAX_LOG_FILE_SIZE')) {
             $max_log_file_size = MAX_LOG_FILE_SIZE;
        }else {
            $max_log_file_size = 10 * 1024 * 1024;
        }
        if(is_file(CTL_LOG_FILE) && filesize(CTL_LOG_FILE) > $max_log_file_size) {
            unlink(CTL_LOG_FILE);
        }
        $logFd = fopen(CTL_LOG_FILE,'a+');
        $date = date("Y-m-d H:i:s");
        $writeMsg = "【{$date}】".$msg."\n\r";
        fwrite($logFd, $writeMsg);
        fclose($logFd);
    }
}

/**
 * master 进程启动时创建注册的有名管道，在master中将入Event::add()事件监听
 * 终端或者外部程序只需要打开这个有名管道，往里面写数据，master的onCliMsg回调即可收到信息
 * @return string
 */
function getCliPipeFile() {
    $path_info = pathinfo(PID_FILE);
    $path_dir = $path_info['dirname'];
    $file_name = $path_info['basename'];
    $ext = $path_info['extension'];
    $pipe_file_name = str_replace($ext,'pipe', $file_name);
    $pipe_file = $path_dir.'/'.$pipe_file_name;
    return $pipe_file;
}

function getCtlPipeFile() {
    $path_info = pathinfo(PID_FILE);
    $path_dir = $path_info['dirname'];
    $pipe_file_name = 'ctl.pipe';
    $pipe_file = $path_dir.'/'.$pipe_file_name;
    return $pipe_file;
}

function getCtlLogFile() {
    $path_info = pathinfo(PID_FILE);
    $path_dir = $path_info['dirname'];
    $ctl_log_file = 'ctl.log';
    $ctl_log_file = $path_dir.'/'.$ctl_log_file;
    return $ctl_log_file;
}

