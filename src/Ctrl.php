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

define('START', 'start');
define('STOP', 'stop');
define('RELOAD', 'reload');
define('STATUS', 'status');
define('PIPE', 'pipe');
define('ADD','add');
define('REMOVE', 'remove');

if(!defined('PID_FILE')) {
    write_info("--------------【Warning】Please define Constans PID_FILE --------------");
    exit(0);
}

$command = $argv[1] ?? START;

$new_argv = $argv;

$argv_arr = array_splice($new_argv, 2);
unset($new_argv);

array_reduce($argv_arr, function($result, $item) {
    if(in_array($item, ['-d', '-D'])) {
        putenv('daemon=1');
    }else {
        $item = ltrim($item, '-');
        putenv($item);
    }
});

$is_daemon = getenv('daemon') ? true : false;

// 定义是否守护进程模式
defined('IS_DAEMON') or define('IS_DAEMON', $is_daemon);

switch($command) {
    case START :
        start();
        break;
    case STOP :
        stop();
        break;
    case RELOAD :
        reload();
        break;
    case STATUS :
        status();
        break;
    case PIPE :
        pipe();
        break;
    case ADD :
        add();
        break;
    case REMOVE :
        remove();
        break;
    default :
        write_info("--------------【Warning】you must use 【start, stop, reload, status, pipe】command --------------");
        exit(0);
}

function start() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
        // 已经启动了，不再重新启动
        if(\Swoole\Process::kill($master_pid, 0)) {
            write_info("--------------【Warning】master process has started, you can not start again --------------");
            exit(0);
        }
    }
    // 通过cli命令行设置worker_num
    $worker_num = (int)getenv('worker_num');
    if(isset($worker_num) && $worker_num > 0) {
        define("WORKER_NUM", $worker_num);
    }

}

function stop() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("--------------【Info】master and children process start to stop, please wait a time --------------",'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 30) {
                break;
            }
            sleep(1);
        }
        write_info("--------------【Info】master and children process has stopped --------------",'green');
    }else {
        write_info("--------------【Warning】pid={$master_pid} 的进程不存在 --------------");
    }
    exit(0);
}

function reload() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGUSR2);
        if($res) {
            write_info("--------------【Info】children process start to reload, please wait a time --------------", 'green');
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 10) {
                break;
            }
            sleep(1);
        }
        write_info("--------------【Info】children process has reloaded --------------", 'green');
    }else {
        write_info("--------------【Warning】pid={$master_pid} 的进程不存在，没法自动reload子进程 --------------");
    }
    exit(0);

}

function status() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("--------------【Warning】master pid is invalid --------------");
            exit(0);
        }
    }
    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGUSR1);
    }else {
        write_info("--------------【Warning】pid={$master_pid} 的进程不存在，无法获取进程状态 --------------");
    }
    exit(0);
}

function pipe() {
    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe--------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $msg = getenv("msg");
    if($msg) {
        write_info("--------------【Info】start write mseesge to master --------------",'green');
        fwrite($pipe, $msg);
    }else {
        write_info("--------------【Warning】please use pipe -msg=xxxxx --------------");
    }
    fclose($pipe);
    exit(0);
}

function add(int $wait_time = 5) {
    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe--------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $name = getenv("name");
    $num = getenv('num') ?? 1;
    $pipe_msg = json_encode(['add' , $name, $num], JSON_UNESCAPED_UNICODE);
    if(isset($name)) {
        write_info("--------------【Warning】master process start to create dynamic process, please wait a time(about {$wait_time}s) --------------",'green');
        fwrite($pipe, $pipe_msg);
    }else {
        write_info("--------------【Warning】please use pipe -name=xxxxx -num=1 --------------");
    }
    flock($pipe, LOCK_UN);
    fclose($pipe);
    sleep($wait_time);
    exit(0);
}

function remove(int $wait_time = 5) {
    $pipe_file = getCliPipeFile();
    if(filetype($pipe_file) != 'fifo' || !file_exists($pipe_file)) {
        write_info("--------------【Warning】 Master process is not enable cli pipe--------------");
        exit(0);
    }
    $pipe = fopen($pipe_file,'w+');
    if(!flock($pipe, LOCK_EX)) {
        write_info("--------------【Warning】 Get file flock fail --------------");
        exit(0);
    }
    $name = getenv("name");
    $num = getenv('num') ?? 1;
    $pipe_msg = json_encode(['remove' , $name, $num], JSON_UNESCAPED_UNICODE);
    if(isset($name)) {
        write_info("--------------【Info】master process start to remova all dynamic process, please wait a time(about {$wait_time}s) --------------",'green');
        fwrite($pipe, $pipe_msg);
    }else {
        write_info("--------------【Warning】please use pipe -name=xxxxx --------------");
    }
    fclose($pipe);
    sleep($wait_time);
    exit(0);
}

function write_info($msg, $foreground = "red", $background = "black") {
    include_once __DIR__.'/EachColor.php';
    // Create new Colors class
    static $colors;
    if(!isset($colors)) {
        $colors = new \Workerfy\EachColor();
    }
    echo $colors->getColoredString($msg, $foreground, $background) . "\n\n";
    if(defined("CTL_LOG_FILE")) {
        if(defined("MAX_LOG_FILE_SIZE")) {
             $max_log_file_size = MAX_LOG_FILE_SIZE;
        }else {
            $max_log_file_size = 5 * 1024 * 1024;
        }
        if(is_file(CTL_LOG_FILE) && filesize(CTL_LOG_FILE) > $max_log_file_size) {
            unlink(CTL_LOG_FILE);
        }
        $log_fd = fopen(CTL_LOG_FILE,'a+');
        $date = date("Y-m-d H:i:s");
        $write_msg = "【{$date}】".$msg."\n\r";
        fwrite($log_fd, $write_msg);
        fclose($log_fd);
    }
}

function getCliPipeFile() {
    $path_info = pathinfo(PID_FILE);
    $path_dir = $path_info['dirname'];
    $file_name = $path_info['basename'];
    $ext = $path_info['extension'];
    $pipe_file_name = str_replace($ext,'pipe', $file_name);
    $pipe_file = $path_dir.'/'.$pipe_file_name;
    return $pipe_file;
}

/**
 * 是否是在主进程环境中
 * @return bool
 */
function inMasterProcessEnv() {
    $pid = posix_getpid();
    if($pid == MASTER_PID) {
        return true;
    }
    return false;
}

/**
 * 是否是在子进程环境中
 * @return bool
 */
function inChildrenProcessEnv() {
    return !inMasterProcessEnv();
}


