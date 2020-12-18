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

namespace Workerfy;

class CommandRunner {

    /**
     * 禁止swoole提供的process->exec，因为swoole的process->exec调用的程序会替换当前子进程
     * @param $execFile
     * @param array $args
     * @return array
     */
    public static function exec($execFile, array $args = []) {
        $params = '';
        if($args) {
            $params = implode(' ', $args);
        }
        $command = $execFile.' '.$params;
        exec($command,$output,$return);
        return [$command, $output ?? '', $return ?? ''];
    }

    /**
     * @param callable $callable
     * @param $execFile
     * @param array $args
     * @return mixed
     * @throws \Throwable
     */
    public static function procOpen(callable $callable, $execFile, array $args = []) {
        $params = '';
        if($args) {
            $params = implode(' ', $args);
        }
        $command = $execFile.' '.$params;
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $proc_process = proc_open($command, $descriptors, $pipes);
        // 注意：$callable 里面禁止再创建协程，因为$proc_process协程绑定在当前协程
        try {
            array_push($pipes, $command);
            return call_user_func_array($callable, $pipes);
        }catch (\Throwable $e) {
            throw $e;
        }finally {
            proc_close($proc_process);
        }
    }
}