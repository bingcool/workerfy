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

use Swoole\Event;
use Swoole\Process;

abstract class AbstractProcess {

    private $swooleProcess;
    private $process_name;
    private $async = null;
    private $args = [];
    private $extend_data;
    private $enable_coroutine = false;
    private $pid;
    private $process_worker_id = 0;
    private $is_reboot = false;

    const SWOOLEFY_PROCESS_KILL_FLAG = "process::worker::action::restart";

    /**
     * AbstractProcess constructor.
     * @param string $processName
     * @param bool   $async
     * @param array  $args
     * @param null   $extend_data
     * @param bool   $enable_coroutine
     */
    public function __construct(string $process_name, bool $async = true, array $args = [], $extend_data = null, bool $enable_coroutine = true) {
        $this->async = $async;
        $this->args = $args;
        $this->extend_data = $extend_data;
        $this->process_name = $process_name;
        $this->enable_coroutine = $enable_coroutine;
        if(version_compare(swoole_version(),'4.3.0','>=')) {
            $this->swooleProcess = new \Swoole\Process([$this,'__start'], false, 2, $enable_coroutine);
        }else {
            $this->swooleProcess = new \Swoole\Process([$this,'__start'], false, 2);
        }
    }

    /**
     * getProcess 获取process进程对象
     * @return object
     */
    public function getProcess() {
        return $this->swooleProcess;
    }

    /**
     * __start 创建process的成功回调处理
     * @param  Process $swooleProcess
     * @return void
     */
    public function __start(Process $swooleProcess) {
        \Swoole\Runtime::enableCoroutine(true);

        $this->pid = $this->swooleProcess->pid;

        if($this->async){
            Event::add($this->swooleProcess->pipe, function() {
                $msg = $this->swooleProcess->read(64 * 1024);
                if(is_string($msg)) {
                    $message = json_decode($msg, true);
                    list($msg, $from_process_name, $from_process_worker_id) = $message;
                }
                if($msg && isset($from_process_name) && isset($from_process_worker_id)) {
                    try {
                        if($msg == self::SWOOLEFY_PROCESS_KILL_FLAG) {
                            $this->reboot();
                            return;
                        }else {
                            $this->onPipeMsg($msg, $from_process_name, $from_process_worker_id);
                        }
                    }catch(\Throwable $t) {
                        throw new \Exception($t->getMessage());
                    }
                }
            });
        }

        Process::signal(SIGTERM, function ($signo) {
            try{
                $this->onShutDown();
            }catch (\Throwable $t){
                throw new \Exception($t->getMessage());
            }finally {
                Event::del($this->swooleProcess->pipe);
                Process::signal(SIGTERM, null);
                Event::exit();
            }
        });

        $this->swooleProcess->name('php-process-worker:'.$this->getProcessName().'@'.$this->getProcessWorkerId());

        try{
            $this->run();
        }catch(\Throwable $t) {
            throw new \Exception($t->getMessage());
        }
    }

    /**
     * writeByProcessName 向某个进程写数据
     * @param  string $name
     * @param  string $data
     * @return boolean
     */
    public function writeByProcessName(string $process_name, string $data, int $process_worker_id = 0, bool $is_use_master_proxy = false) {
        $processManager = \Workerfy\processManager::getInstance();
        $isMaster = $processManager->isMaster(md5($process_name));
        $from_process_name = $this->getProcessName();
        $from_process_worker_id = $this->getProcessWorkerId();

        if($isMaster) {
            $process_worker_id = 0;
            $message = json_encode([$data, $from_process_name, $from_process_worker_id, $processManager->getMasterWorkerName(), $process_worker_id], JSON_UNESCAPED_UNICODE);
            $this->getSwooleProcess()->write($message);
            return true;
        }

        $process_workers = [];
        $to_process = $processManager->getProcessByName($process_name, $process_worker_id);
        if(is_object($to_process) && $to_process instanceof AbstractProcess) {
            $process_workers = [$process_worker_id => $to_process];
        }else if(is_array($to_process)) {
            $process_workers = $to_process;
        }

        foreach($process_workers as $process_worker_id => $process) {
            $to_process_name = $process->getProcessName();
            $to_process_worker_id = $process->getProcessWorkerId();
            $message = json_encode([$data, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id], JSON_UNESCAPED_UNICODE);
            if($is_use_master_proxy) {
                $this->getSwooleProcess()->write($message);
            }else {
                $process->getSwooleProcess()->write($message);
            }
        }
    }

    /**
     * start
     * @return void
     */
    public function start() {
        $this->swooleProcess->start();
    }

    /**
     * setProcessWorkerId
     * @param int $id
     */
    public function setProcessWorkerId(int $id) {
        $this->process_worker_id = $id;
    }

    /**
     * @return Process
     */
    public function getSwooleProcess() {
        return $this->swooleProcess;
    }

    /**
     * getProcessWorkerId
     * @return int
     */
    public function getProcessWorkerId() {
        return $this->process_worker_id;
    }

    /**
     * getPid
     * @return int
     */
    public function getPid() {
        return $this->swooleProcess->pid;
    }

    /**
     * @return bool
     */
    public function isStart() {
        if(isset($this->pid) && $this->pid > 0) {
            return true;
        }
        return false;
    }

    /**
     * getProcessName
     * @return string
     */
    public function getProcessName() {
        return $this->process_name;
    }

    /**
     * getArgs 获取变量参数
     * @return mixed
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * @return null
     */
    public function getExtendData() {
        return $this->extend_data;
    }

    /**
     * isAsync
     * @return boolean
     */
    public function isAsync() {
        return $this->async;
    }

    /**
     * 是否启用协程
     */
    public function isEnableCoroutine() {
        return $this->enable_coroutine;
    }

    /**
     * reboot 自动重启
     * @return
     */
    public function reboot() {
        $pid = $this->getPid();
        if(Process::kill($pid, 0)) {
            $this->is_reboot = true;
            Process::kill($pid, SIGTERM);
        }
    }

    /**
     * 直接退出进程
     */
    public function exit(int $pid = null) {
        if(!$pid) {
            $pid = $this->getPid();
        }
        if(Process::kill($pid, 0)) {
            if(!$this->is_reboot) {
                $this->onShutDown();
            }
            Process::kill($pid, SIGKILL);
        }
    }

    /**
     * run 进程创建后的run方法
     * @param  Process $process
     * @return void
     */
    public abstract function run();

    /**
     * @return mixed
     */
    public function onShutDown() {}

    /**
     * @param       $str
     * @param mixed ...$args
     * @return mixed
     */
    public function onPipeMsg(string $msg, string $from_process_name, int $from_process_worker_id) {}


}