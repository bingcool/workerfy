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
define('PIPE', "pipe");

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
    $file = fopen(PIPE_FIFO,'w+');
    $msg = getenv("msg");
    if($msg) {
        write_info("--------------【Info】start write mseesge to master --------------",'green');
        fwrite($file, $msg);
    }else {
        write_info("--------------【Warning】please use pipe -msg=xxxxx --------------");
    }
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
}


