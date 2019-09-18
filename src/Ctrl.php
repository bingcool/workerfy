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

define('STOP', 'stop');
define('RELOAD', 'reload');
if(isset($argv[1]) && $argv[1] == STOP)  {
//    if(!defined(PID_FILE)) {
//        throw new \Exception('missing define PID_FILE');
//    }
        var_dump(PID_FILE);
    if(is_file(PID_FILE)) {
        $pid = file_get_contents(PID_FILE);
    }

    if(\Swoole\Process::kill(intval($pid), 0)) {
        \Swoole\Process::kill($pid, SIGKILL);
    }
    exit;
}


