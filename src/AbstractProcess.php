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
    private $is_dynamic_destroy = false; // 动态进程正在销毁时，原则上在一定时间内不能动态创建进程
    private $reboot_count = 0; //自动重启次数
    private $start_time; // 启动(重启)时间

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
                            $is_call_pipe = true;
                            if(is_string($msg)) {
                                switch($msg) {
                                    case self::WORKERFY_PROCESS_REBOOT_FLAG :
                                        $is_call_pipe = false;
                                        $this->reboot();
                                        break;
                                    case self::WORKERFY_PROCESS_EXIT_FLAG :
                                        $is_call_pipe = false;
                                        if($from_process_name == ProcessManager::MASTER_WORKER_NAME) {
                                            $this->exit(true);
                                        }else {
                                            $this->exit();
                                        }
                                        break;
                                }
                            }

                            if($is_call_pipe === true) {
                                $this->onPipeMsg($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master);
                            }

                        }catch(\Throwable $throwable) {
                            throw $throwable;
                        }
                    }
                });
            }

            // exit
            Process::signal(SIGTERM, function ($signo) {
                Event::del($this->swooleProcess->pipe);
                Event::exit();
                if(method_exists($this,'__destruct')) {
                    $this->__destruct();
                }
                $this->swooleProcess->exit(SIGTERM);
            });

            // reboot
            Process::signal(SIGUSR1, function ($signo) {
                Event::del($this->swooleProcess->pipe);
                Event::exit();
                if(method_exists($this,'__destruct')) {
                    $this->__destruct();
                }
                $this->swooleProcess->exit(SIGUSR1);
            });

            if(PHP_OS != 'Darwin') {
                $this->swooleProcess->name('php-process-worker:'.$this->getProcessName().'@'.$this->getProcessWorkerId());
            }
            try{
                $this->init();
                $this->run();
            }catch(\Throwable $throwable) {
                throw $throwable;
            }

        }catch(\Throwable $t) {
            $this->onHandleException($t);
        }
    }

    /**
     * writeByProcessName worker进程向某个进程写数据
     * @param  string $name
     * @param  mixed $data
     * @param  int    $process_worker_id
     * @return boolean
     */
    public function writeByProcessName(string $process_name, $data, int $process_worker_id = 0, bool $is_use_master_proxy = false) {
        $processManager = \Workerfy\processManager::getInstance();
        $isMaster = $processManager->isMaster($process_name);
        $from_process_name = $this->getProcessName();
        $from_process_worker_id = $this->getProcessWorkerId();
        
        if($from_process_name == $process_name && $process_worker_id == $from_process_worker_id) {
            $error = "Error:write message to self worker";
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
     * @param mixed $data
     * @param int $process_worker_id
     * @return bool
     */
    public function writeToMasterProcess(string $process_name, $data, int $process_worker_id = 0) {
        $is_use_master_proxy = false;
        return $this->writeByProcessName($process_name, $data, $process_worker_id, $is_use_master_proxy);
    }

    /**
     * writeToWorkerByMasterProxy 向master进程写代理数据，master在代理转发worker进程
     * @param string $process_name
     * @param mixed $data
     * @param int $process_worker_id
     */
    public function writeToWorkerByMasterProxy(string $process_name, $data, int $process_worker_id = 0) {
        $is_use_master_proxy = true;
        $this->writeByProcessName($process_name, $data, $process_worker_id, $is_use_master_proxy);
    }

    /**
     * notifyMasterCreateDynamicProcess 通知master进程动态创建进程
     * @param string $dynamic_process_name
     * @param int $dynamic_process_num
     */
    public function notifyMasterCreateDynamicProcess(string $dynamic_process_name, int $dynamic_process_num = 2) {
        if(!$this->is_dynamic_destroy) {
            $data = [
                ProcessManager::CREATE_DYNAMIC_WORKER,
                $dynamic_process_name,
                $dynamic_process_num
            ];
            $this->writeToMasterProcess(ProcessManager::MASTER_WORKER_NAME, $data);
        }
    }

    /**
     * notifyMasterDestroyDynamicProcess 通知master销毁动态创建的进程
     * @param string $dynamic_process_name
     * @param int $dynamic_process_num
     */
    public function notifyMasterDestroyDynamicProcess(string $dynamic_process_name, int $dynamic_process_num = -1) {
        if(!$this->is_dynamic_destroy) {
            // 销毁默认是销毁所有动态创建的进程，没有部分销毁,$dynamic_process_num设置没有意义
            $dynamic_process_num = -1;
            $data = [
                ProcessManager::DESTROY_DYNAMIC_PROCESS,
                $dynamic_process_name,
                $dynamic_process_num
            ];
            $this->writeToMasterProcess(ProcessManager::MASTER_WORKER_NAME, $data);
            // 发出销毁指令后，需要在一定时间内避免继续调用动态创建和动态销毁通知这两个函数，因为进程销毁时存在wait_time
            $this->isDynamicDestroy(true);
            if(isset($this->getArgs()['dynamic_destroy_process_time'])) {
                $dynamic_destroy_process_time = $this->getArgs()['dynamic_destroy_process_time'];
                // 最大时间不能太长
                if($dynamic_destroy_process_time > 1800) {
                    $dynamic_destroy_process_time = 1800;
                }else {
                    $dynamic_destroy_process_time = $this->wait_time + 5;
                }
            }else {
                $dynamic_destroy_process_time = $this->wait_time + 5;
            }
            // 等待
            \Swoole\Coroutine::sleep($dynamic_destroy_process_time);
            $this->isDynamicDestroy(false);
        }
    }

    /**
     * 是否正在动态进程销毁中状态
     * @param bool $is_destroy
     */
    public function isDynamicDestroy(bool $is_destroy) {
        $this->is_dynamic_destroy = $is_destroy;
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
     * @param int $count
     */
    public function setRebootCount(int $count) {
        $this->reboot_count = $count;
    }

    /**
     * @return int
     */
    public function getRebootCount() {
        return $this->reboot_count;
    }

    /**
     * @return mixed
     */
    public function getExitTimerId() {
        return $this->exit_timer_id;
    }

    /**
     * setStartTime
     */
    public function setStartTime() {
        $this->start_time = date('Y-m-d H:i:s', strtotime('now'));
    }

    /**
     * @return mixed
     */
    public function getStartTime() {
        return $this->start_time;
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
                        $this->runtimeCoroutineWait();
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
            throw new \Exception("Dynamic Process can not reboot");
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
                        $this->runtimeCoroutineWait();
                        write_info($this->getProcessName().'@'.$this->getProcessWorkerId().' exit');
                        $this->onShutDown();
                    }
                }catch (\Throwable $throwable) {
                    throw $throwable;
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
                    $this->runtimeCoroutineWait();
                    $this->onShutDown();
                }catch (\Throwable $throwable) {
                    throw $throwable;
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
     * 对于运行态的协程，还没有执行完的，设置一个再等待时间$re_wait_time
     * @param int $re_wait_time
     */
    private function runtimeCoroutineWait($re_wait_time = 8) {
        // 当前运行的coroutine
        $runCoroutineNum = $this->getCurrentRunCoroutineNum();
        // 除了主协程，还有其他协程没唤醒，则再等待
        if($runCoroutineNum > 1) {
            if($this->wait_time < 5)  {
                $re_wait_time = 5;
            }
            \Swoole\Coroutine::sleep($re_wait_time);
        }
    }

    /**
     * 初始化函数
     */
    public function init() {}

    /**
     * run 进程创建后的run方法
     * @param  Process $process
     * @return void
     */
    public abstract function run();

    /**
     * @param mixed $msg
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param bool $is_proxy_by_master
     */
    public function onPipeMsg($msg, string $from_process_name, int $from_process_worker_id, bool $is_proxy_by_master) {}

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