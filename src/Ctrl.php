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
define('STRAER', 'start');
define('STOP', 'stop');
define('RELOAD', 'reload');

include __DIR__.'/Function.php';

$command = $argv[1] ?? STRAER;
$is_daemon = (isset($argv[2]) && in_array($argv[2], ['-d', '-D'])) ? true : false;

// 定义是否守护进程模式
defined('IS_DAEMON') or define('IS_DAEMON', $is_daemon);

switch($command) {
    case STRAER :
        start();
        break;
    case STOP :
        stop();
        break;
    case RELOAD :
        reload();
        break;
    default :
        write_info("you must use command");
        exit(0);
}
function start() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("master pid is invalid");
            exit(0);
        }
        // 已经启动了，不再重新启动
        if(\Swoole\Process::kill($master_pid, 0)) {
            write_info("master has started, you can not start again");
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
            write_info("master pid is invalid");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("-----------master start to stop, please wait a time-----------");
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 60) {
                break;
            }
            sleep(1);
        }
        write_info("-----------master has stopped-----------");
    }
    exit(0);
}

function reload() {
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            write_info("master pid is invalid");
            exit(0);
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGUSR2);
        if($res) {
            write_info("-----------master start to reload, please wait a time-----------");
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            if(time() - $start_stop_time > 30) {
                break;
            }
            sleep(1);
        }
        write_info("-----------master has reload-----------");
    }
    exit(0);

}


