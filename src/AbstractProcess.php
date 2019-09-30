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
use Workerfy\ProcessManager;

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
    private $is_exit = false;
    private $is_force_exit = false;
    private $process_type = 1;// 1-静态进程，2-动态进程
    private $wait_time = 30;
    private $reboot_timer_id;
    private $exit_timer_id;
    private $coroutine_id;//当前进程的主协程id

    const PROCESS_STATIC_TYPE = 1; //静态进程
    const PROCESS_DYNAMIC_TYPE = 2; //动态进程
    const WORKERFY_PROCESS_REBOOT_FLAG = "process::worker::action::reboot";
    const WORKERFY_PROCESS_EXIT_FLAG = "process::worker::action::exit";

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
        if(isset($args['wait_time']) && is_numeric($args['wait_time'])) {
            $this->wait_time = $args['wait_time'];
        }
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
     * @return mixed
     */
    public function getCoroutineId() {
        return $this->coroutine_id;
    }

    /**
     * __start 创建process的成功回调处理
     * @param  Process $swooleProcess
     * @return void
     */
    public function __start(Process $swooleProcess) {
        \Swoole\Runtime::enableCoroutine(true);
        $this->pid = $this->swooleProcess->pid;
        $this->coroutine_id = \Co::getCid();
        try {
            if($this->async){
                Event::add($this->swooleProcess->pipe, function() {
                    $msg = $this->swooleProcess->read(64 * 1024);
                    if(is_string($msg)) {
                        $message = json_decode($msg, true);
                        @list($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master) = $message;
                        if(!isset($is_proxy_by_master) || is_null($is_proxy_by_master) || $is_proxy_by_master === false) {
                            $is_proxy_by_master = false;
                        }else {
                            $is_proxy_by_master = true;
                        }
                    }
                    if($msg && isset($from_process_name) && isset($from_process_worker_id)) {
                        try {
                            switch ($msg) {
                                case self::WORKERFY_PROCESS_REBOOT_FLAG :
                                    $this->reboot();
                                    break;
                                case self::WORKERFY_PROCESS_EXIT_FLAG :
                                    if($from_process_name == ProcessManager::MASTER_WORKER_NAME) {
                                        $this->exit(true);
                                    }else {
                                        $this->exit();
                                    }
                                    break;
                                default :
                                    $this->onPipeMsg($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master);
                                    break;
                            }
                        }catch(\Throwable $t) {
                            throw new \Exception($t->getMessage());
                        }
                    }
                });
            }

            // reboot
            Process::signal(SIGUSR1, function ($signo) {
                Event::del($this->swooleProcess->pipe);
                Event::exit();
                $this->swooleProcess->exit(SIGUSR1);
            });

            // exit
            Process::signal(SIGTERM, function ($signo) {
                Event::del($this->swooleProcess->pipe);
                Event::exit();
                $this->swooleProcess->exit(SIGTERM);
            });

            if(PHP_OS != 'Darwin') {
                $this->swooleProcess->name('php-process-worker:'.$this->getProcessName().'@'.$this->getProcessWorkerId());
            }
            try{
                $this->run();
            }catch(\Throwable $t) {
                throw new \Exception($t->getMessage());
            }

        }catch(\Throwable $t) {
            $this->onHandleException($t);
        }
    }

    /**
     * writeByProcessName worker进程向某个进程写数据
     * @param  string $name
     * @param  string $data
     * @return boolean
     */
    public function writeByProcessName(string $process_name, string $data, int $process_worker_id = 0, bool $is_use_master_proxy = false) {
        $processManager = \Workerfy\processManager::getInstance();
        $isMaster = $processManager->isMaster($process_name);
        $from_process_name = $this->getProcessName();
        $from_process_worker_id = $this->getProcessWorkerId();
        
        if($from_process_name == $process_name && $process_worker_id == $from_process_worker_id) {
            $error = "Error:write message to self worker";
            write_info($error);
            throw new \Exception($error);
        }

        if($isMaster) {
            $to_process_worker_id = 0;
            $message = json_encode([$data, $from_process_name, $from_process_worker_id, $processManager->getMasterWorkerName(), $to_process_worker_id], JSON_UNESCAPED_UNICODE);
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
            // 进程处于rebooting|Exiting时，不再发msg
            if($process->isRebooting() || $process->isExiting()) {
                continue;
            }
            $message = json_encode([$data, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id], JSON_UNESCAPED_UNICODE);
            if($is_use_master_proxy) {
                $this->getSwooleProcess()->write($message);
            }else {
                $is_proxy = false;
                $message = json_encode([$data, $from_process_name, $from_process_worker_id, $is_proxy], JSON_UNESCAPED_UNICODE);
                $process->getSwooleProcess()->write($message);
            }
        }
    }

    /**
     * writeToMasterProcess 直接向master进程写数据
     * @param string $process_name
     * @param string $data
     * @param int $process_worker_id
     * @return bool
     */
    public function writeToMasterProcess(string $process_name, string $data, int $process_worker_id = 0) {
        $is_use_master_proxy = false;
        return $this->writeByProcessName($process_name, $data, $process_worker_id, $is_use_master_proxy);
    }

    /**
     * writeToWorkerByMasterProxy 向master进程写代理数据，master在代理转发worker进程
     * @param string $process_name
     * @param string $data
     * @param int $process_worker_id
     */
    public function writeToWorkerByMasterProxy(string $process_name, string $data, int $process_worker_id = 0) {
        $is_use_master_proxy = true;
        $this->writeByProcessName($process_name, $data, $process_worker_id, $is_use_master_proxy);
    }

    /**
     * notifyMasterCreateDynamicProcess 通知master进程动态创建进程
     * notifyMasterDynamicCreateProcess
     */
    public function notifyMasterCreateDynamicProcess() {
        $this->writeToMasterProcess(ProcessManager::MASTER_WORKER_NAME, ProcessManager::CREATE_DYNAMIC_WORKER);
    }

    /**
     * notifyMasterDestroyDynamicProcess 通知master销毁动态创建的进程
     * notifyMasterDestroyDynamicProcess
     */
    public function notifyMasterDestroyDynamicProcess() {
        $this->writeToMasterProcess(ProcessManager::MASTER_WORKER_NAME, ProcessManager::DESTROY_DYNAMIC_PROCESS);
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
     * @param int $process_type
     */
    public function setProcessType(int $process_type = 1) {
        $this->process_type = $process_type;
    }

    /**
     * @return int
     */
    public function getProcessType() {
        return $this->process_type;
    }

    /**
     * @param int $wait_time
     */
    public function setWaitTime(float $wait_time = 30) {
        $this->wait_time = $wait_time;
    }

    /**
     * getWaitTime
     */
    public function getWaitTime() {
        return $this->wait_time;
    }

    /**
     * isRebooting
     * @return bool
     */
    public function isRebooting() {
        return $this->is_reboot;
    }

    /**
     * isExiting
     * @return bool
     */
    public function isExiting() {
        return $this->is_exit;
    }

    /**
     * 静态创建进程，属于进程池进程，可以自重启，退出
     * @return bool
     */
    public function isStaticProcess() {
        if($this->process_type == 1) {
            return true;
        }
        return false;
    }

    /**
     * 动态创建进程,工作完只能退出，不能重启
     * @return bool
     */
    public function isDynamicProcess() {
        return !$this->isStaticProcess();
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
     * @return mixed
     */
    public function getRebootTimerId() {
        return $this->reboot_timer_id;
    }

    /**
     * @return mixed
     */
    public function getExitTimerId() {
        return $this->exit_timer_id;
    }

    /**
     * reboot 自动重启
     * @param float $wait_time
     * @return bool
     */
    public function reboot(float $wait_time = null) {
        if($this->isStaticProcess()) {
            // 设置强制退出后，不能再设置reboot
            if($this->is_force_exit) {
                return false;
            }
            // 自定义等待重启时间
            if($wait_time) {
                $this->wait_time = $wait_time;
            }

            $pid = $this->getPid();
            if(Process::kill($pid, 0) && $this->is_reboot === false && $this->is_exit === false) {
                $this->is_reboot = true;
                $timer_id = \Swoole\Timer::after($this->wait_time * 1000, function() use($pid) {
                    try {
                        $this->onShutDown();
                    }catch (\Throwable $throwable) {
                        throw new \Exception($throwable->getMessage());
                    }finally {
                        $this->kill($pid, SIGUSR1);
                    }
                });
                $this->reboot_timer_id = $timer_id;
            }
            return true;
        }else {
            throw new \Exception("DynamicProcess can not reboot");
        }
    }

    /**
     * 直接退出进程
     * @param bool $is_force 是否强制退出
     * @param float  $wait_time
     * @return bool
     */
    public function exit(bool $is_force = false, float $wait_time = null) {
        $pid = $this->getPid();
        $is_process_exist = Process::kill($pid, 0);
        // 自定义退出等待时间
        $wait_time && $this->wait_time = $wait_time;
        if($is_process_exist && $is_force) {
            $this->is_exit = true;
            $this->is_force_exit = true;
            // 强制退出时，如果设置了reboot的定时器，需要清除
            $this->clearRebootTimer();
            $timer_id = \Swoole\Timer::after($this->wait_time * 1000, function() use($pid) {
                try {
                    if(!$this->is_reboot) {
                        write_info($this->getProcessName().'@'.$this->getProcessWorkerId().' exit');
                        $this->onShutDown();
                    }
                }catch (\Throwable $throwable) {
                    throw new \Exception($throwable->getMessage());
                }finally {
                    $this->kill($pid, SIGTERM);
                }
            });
            $this->exit_timer_id = $timer_id;
            return true;
        }

        if($is_process_exist && $this->is_exit === false && $this->is_reboot === false) {
            $this->is_exit = true;
            $timer_id = \Swoole\Timer::after($this->wait_time * 1000, function() use($pid) {
                try {
                    $this->onShutDown();
                }catch (\Throwable $throwable) {
                    throw new \Exception($throwable->getMessage());
                }finally {
                    $this->kill($pid, SIGTERM);
                }
            });
            $this->exit_timer_id = $timer_id;
            return true;
        }
    }

    /**
     * 强制退出时，需要清理reboot的定时器
     * clearRebootTimer
     * @return void
     */
    public function clearRebootTimer() {
        if(isset($this->reboot_timer_id) && !empty($this->reboot_timer_id)) {
            \Swoole\Timer::clear($this->reboot_timer_id);
            $this->is_reboot = false;
        }
    }

    /**
     * isForceExit
     * @return boolean
     */
    public function isForceExit() {
        return $this->is_force_exit;
    }

    /**
     * @param $pid
     * @param $signal
     */
    public function kill($pid, $signal) {
        if(Process::kill($pid, 0)){
            Process::kill($pid, $signal);
        }
    }

    /**
     * getCurrentRunCoroutineNum 获取当前进程中正在运行的协程数量，可以通过这个值判断比较，防止协程过多创建，可以设置sleep等待
     * @return int
     */
    public function getCurrentRunCoroutineNum() {
        $coroutine_info = \Swoole\Coroutine::stats();
        if(isset($coroutine_info['coroutine_num'])) {
            return $coroutine_info['coroutine_num'];
        }
    }

    /**
     * getCurrentCcoroutineLastCid 获取当前进程的协程cid已分配到哪个值，可以根据这个值设置进程reboot,防止cid超出最大数
     * @return int
     */
    public function getCurrentCcoroutineLastCid() {
        $coroutine_info = \Swoole\Coroutine::stats();
        if(isset($coroutine_info['coroutine_last_cid'])) {
            return $coroutine_info['coroutine_last_cid'];
        }
    }

    /**
     * run 进程创建后的run方法
     * @param  Process $process
     * @return void
     */
    public abstract function run();

    /**
     * @param string $msg
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param bool $is_proxy_by_master
     */
    public function onPipeMsg(string $msg, string $from_process_name, int $from_process_worker_id, bool $is_proxy_by_master) {}

    /**
     * onShutDown
     * @return mixed
     */
    public function onShutDown() {}

    /**
     * onHandleException
     * @param  $throwable
     * @return mixed
     */
    public function onHandleException($throwable) {}

}