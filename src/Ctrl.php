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
    if(is_file(PID_FILE)) {
        $master_pid = file_get_contents(PID_FILE);
        if(is_numeric($master_pid)) {
            $master_pid = (int) $master_pid;
        }else {
            throw new \Exception("master pid is invalid");
        }
    }

    if(\Swoole\Process::kill($master_pid, 0)) {
        $res = \Swoole\Process::kill($master_pid, SIGTERM);
        if($res) {
            write_info("-----------master start to stop, please wait a time-----------");
        }
        $start_stop_time = time();
        while(\Swoole\Process::kill($master_pid, 0)) {
            // 超过20s
            if(time() - $start_stop_time > 20) {
                break;
            }
            sleep(5);
        }
        write_info("-----------master has stopped-----------");
    }

    exit;
}


