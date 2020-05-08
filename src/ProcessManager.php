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

use Swoole\Process;
use Workerfy\Memory\AtomicManager;

class ProcessManager {

    use \Workerfy\Traits\SingletonTrait;

    private $process_lists = [];

	private $process_wokers = [];

	private $process_pid_map = [];

	private $master_pid;

    private $master_worker_id = 0;

    private $signal = [];

    private $is_daemon = false;

    private $is_exit = false;

    private $start_time;

    private $is_running = false;

    private $is_create_pipe = true;

    private $cli_pipe_fd;

    private $closure;

    public $onStart;
    public $onPipeMsg;
    public $onProxyMsg;
    public $onCliMsg;
    public $onCreateDynamicProcess;
    public $onDestroyDynamicProcess;
    public $onReportStatus;
    public $onHandleException;
    public $onExit;

    const NUM_PEISHU = 8;
    const REPORT_STATUS_TICK_TIME = 3;
    const MASTER_WORKER_NAME = 'master_worker';
    const CREATE_DYNAMIC_WORKER = 'create_dynamic_process_worker';
    const DESTROY_DYNAMIC_PROCESS = 'destroy_dynamic_process_worker';

    /**
     * ProcessManager constructor.
     * @param mixed ...$args
     */
	public function __construct(...$args) {
        \Swoole\Runtime::enableCoroutine(true);
        $this->onHandleException = function (\Throwable $e) {};
    }

    /**
     * addProcess
     * @param string $process_name
     * @param string $process_class
     * @param int $process_worker_num
     * @param bool $async
     * @param array $args
     * @param null $extend_data
     * @param bool $enable_coroutine
     * @throws \Exception
     */
	public function addProcess(
	    string $process_name,
        string $process_class,
        int $process_worker_num = 1,
        bool $async = true,
        array $args = [],
        $extend_data = null,
        bool $enable_coroutine = true
    ) {
        $key = md5($process_name);
        if(isset($this->process_lists[$key])) {
            throw new \Exception(__CLASS__ . " Error : you can not add the same process : $process_name", 1);
        }
        if(!$enable_coroutine) {
            $enable_coroutine = true;
        }
        if(!$async) {
            $async = true;
        }

        $cpu_num = swoole_cpu_num();

        if($process_worker_num > $cpu_num * (self::NUM_PEISHU)) {
            write_info("--------------【Warning】Params process_worker_num 大于最大的子进程限制数量=cpu_num * ".(self::NUM_PEISHU)."--------------");
            exit(0);
        }

        if(isset($args['max_process_num'])) {
            if($args['max_process_num'] > $cpu_num * (self::NUM_PEISHU)) {
                $args['max_process_num'] = $cpu_num * (self::NUM_PEISHU);
            }
        }else {
            $args['max_process_num'] = $cpu_num * (self::NUM_PEISHU);
        }

        $this->process_lists[$key] = [
            'process_name' => $process_name,
            'process_class' => $process_class,
            'process_worker_num' => $process_worker_num,
            'async' => $async,
            'args' => $args,
            'extend_data' => $extend_data,
            'enable_coroutine' => $enable_coroutine
        ];
    }

    /**
     * start
     * @return
     */
    public function start(bool $is_daemon = false) {
        try {
            if(!empty($this->process_lists)) {
                $this->daemon($is_daemon);
                $this->setMasterPid();
                if($this->closure instanceof \Closure) {
                    $this->closure->call($this);
                }
                foreach ($this->process_lists as $key => $list) {
                    $process_worker_num = $list['process_worker_num'] ?? 1;
                    for ($worker_id = 0; $worker_id < $process_worker_num; $worker_id++) {
                        try {
                            $process_name = $list['process_name'];
                            $process_class = $list['process_class'];
                            $async = $list['async'] ?? true;
                            $args = $list['args'] ?? [];
                            $extend_data = $list['extend_data'] ?? null;
                            $enable_coroutine = $list['enable_coroutine'] ?? true;
                            $process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
                            $process->setProcessWorkerId($worker_id);
                            $process->setMasterPid($this->master_pid);
                            $process->setStartTime();
                            if (!isset($this->process_wokers[$key][$worker_id])) {
                                $this->process_wokers[$key][$worker_id] = $process;
                            }
                            usleep(50000);
                        } catch (\Throwable $t) {
                            $this->onHandleException->call($this, $t);
                        }
                    }
                }
                foreach ($this->process_wokers as $key => $process_woker) {
                    foreach ($process_woker as $worker_id => $process) {
                        $process->start();
                        usleep(50000);
                    }
                }
                // process->start 后，父进程会强制要求pdo,redis等API must be called in the coroutine中
                $this->running();
                $this->installCliPipe();
                $this->installSigchldsignal();
                $this->installMasterStopSignal();
                $this->installMasterReloadSignal();
                $this->installRegisterShutdownFunction();
                $this->registerSignal();
                $this->swooleEventAdd();
                $this->installReportStatus();
                $this->setStartTime();
            }
            // 设置在process start之后
            $master_pid = $this->getMasterPid();
            $this->saveMasterPidTofile($master_pid);
            $this->saveStatusToFile();
            if($master_pid && is_callable($this->onStart)) {
                try {
                    $this->onStart && $this->onStart->call($this, $master_pid);
                } catch (\Throwable $throwable) {
                    throw $throwable;
                }
            }
            return $master_pid;
        } catch (\Throwable $throwable) {
            $this->onHandleException->call($this, $throwable);
        }
    }

    /**
     * 主进程注册监听退出信号,逐步发送退出指令至子进程退出，子进程完全退出后，master进程最后退出
     * 每个子进程收到退出指令后，等待wait_time后正式退出，那么在这个wait_time过程
     * 子进程逻辑应该通过$this->isRebooting() || $this->isExiting()判断是否在退出状态中，这个状态中不能再处理新的任务数据
     */
    private function installMasterStopSignal() {
        if(!$this->is_daemon) {
            // Ctrl+C 退出，master如果使用了协程，可能会出现Segmentation fault，因为是在退出阶段，对业务影响不大，可以忽略
            \Swoole\Process::signal(SIGINT, $this->signalHandle());
        }

        \Swoole\Process::signal(SIGTERM, $this->signalHandle());
    }

    /**
     * 终止进程处理函数
     * @return \Closure
     */
    private function signalHandle() {
        return function($signal) {
            switch ($signal) {
                case SIGINT:
                case SIGTERM:
                    foreach ($this->process_wokers as $key => $processes) {
                        foreach ($processes as $worker_id => $process) {
                            $process_name = $process->getProcessName();
                            $this->writeByProcessName($process_name, AbstractProcess::WORKERFY_PROCESS_EXIT_FLAG, $worker_id);
                        }
                    }
                    $this->is_exit = true;
                    break;
            }
        };
    }

    /**
     * 父进程的status通过fifo有名管道信号回传
     */
    private function masterStatusToCliFifoPipe($ctl_pipe_file) {
        $ctl_pipe = fopen($ctl_pipe_file,'w+');
        $master_info = $this->statusInfoFormat($this->getMasterWorkerName(), $this->getMasterWorkerId(), $this->getMasterPid(), 'running', $this->start_time);
        fwrite($ctl_pipe, $master_info);
        foreach($this->process_wokers as $key => $processes) {
            ksort($processes);
            foreach($processes as $process_worker_id => $process) {
                $process_name = $process->getProcessName();
                $worker_id = $process->getProcessWorkerId();
                $pid = $process->getPid();
                $start_time = $process->getStartTime();
                $reboot_count = $process->getRebootCount();
                $process_type = $process->getProcessType();
                if($process_type == AbstractProcess::PROCESS_STATIC_TYPE) {
                    $process_type = AbstractProcess::PROCESS_STATIC_TYPE_NAME;
                }else {
                    $process_type = AbstractProcess::PROCESS_DYNAMIC_TYPE_NAME;
                }

                if(\Swoole\Process::kill($pid, 0)) {
                    $this->rebootOrExitHandle();
                    $status = 'running';
                }else {
                    $status = 'stop';
                }
                $info = $this->statusInfoFormat($process_name, $worker_id, $pid, $status, $start_time, $reboot_count, $process_type);
                if($status == 'stop') {
                    write_info($info);
                }else {
                    @fwrite($ctl_pipe, $info);
                }
            }
            @fclose($ctl_pipe);
            unset($processes);
        }
    }

    /**
     * 主进程注册监听自定义的SIGUSR2作为通知子进程重启的信号
     * 每个子进程收到重启指令后，等待wait_time后正式退出，那么在这个wait_time过程
     * 子进程逻辑应该通过$this->isRebooting() || $this->isExiting()判断是否在重启状态中，这个状态中不能再处理新的任务数据
     */
    private function installMasterReloadSignal() {
        \Swoole\Process::signal(SIGUSR2, function($signo) {
            foreach($this->process_wokers as $key => $processes) {
                foreach($processes as $worker_id => $process) {
                    $process_name = $process->getProcessName();
                    $this->writeByProcessName($process_name, AbstractProcess::WORKERFY_PROCESS_REBOOT_FLAG, $worker_id);
                }
            }
            $this->is_exit = true;
        });
    }

    /**
     * installSigchldsignal 注册回收子进程信号
     */
    private function installSigchldsignal() {
        \Swoole\Process::signal(SIGCHLD, function($signo) {
  			$this->rebootOrExitHandle();
		});
    }

    /**
     * @param string $name
     */
    public function setCliMasterName($name = '') {
        $this->closure = function() use($name) {
            if($name) {
                cli_set_process_title($name);
            }
        };
    }

    /**
     * rebootOrExitHandle 信号处理函数
     */
    protected function rebootOrExitHandle() {
        //必须为false，非阻塞模式
        while($ret = \Swoole\Process::wait(false)) {
            if(!is_array($ret) || !isset($ret['pid'])) {
                return;
            }
            $pid = $ret['pid'];
            $code = $ret['code'];
            try {
                switch ($code) {
                    // exit 信号
                    case 0       :
                    case SIGTERM :
                    case SIGKILL :
                        $process = $this->getProcessByPid($pid);
                        $process_name = $process->getProcessName();
                        $process_worker_id = $process->getProcessWorkerId();
                        $key = md5($process_name);
                        if (isset($this->process_wokers[$key][$process_worker_id])) {
                            unset($this->process_wokers[$key][$process_worker_id]);
                            if (count($this->process_wokers[$key]) == 0) {
                                unset($this->process_wokers[$key]);
                            }
                        }
                        if (count($this->process_wokers) == 0) {
                            try {
                                is_callable($this->onExit) && $this->onExit->call($this);
                            } catch (\Throwable $throwable) {
                                $this->onHandleException->call($this, $throwable);
                            } finally {
                                $this->saveStatusToFile();
                                exit(0);
                            }
                        }
                        break;
                    // reboot 信号
                    case SIGUSR1  :
                    default  :
                        if(!(\Swoole\Process::kill($pid, 0))) {
                            $process = $this->getProcessByPid($pid);
                            $process_name = $process->getProcessName();
                            $process_type = $process->getProcessType();
                            $process_worker_id = $process->getProcessWorkerId();
                            $process_reboot_count = $process->getRebootCount() + 1;
                            $key = md5($process_name);
                            $list = $this->process_lists[$key];
                            \Swoole\Event::del($process->getSwooleProcess()->pipe);
                            unset($this->process_wokers[$key][$process_worker_id]);
                            if(is_array($list)) {
                                try {
                                    $process_name = $list['process_name'];
                                    $process_class = $list['process_class'];
                                    $async = $list['async'] ?? true;
                                    $args = $list['args'] ?? [];
                                    $extend_data = $list['extend_data'] ?? null;
                                    $enable_coroutine = $list['enable_coroutine'] ?? false;
                                    $new_process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
                                    $new_process->setProcessWorkerId($process_worker_id);
                                    $new_process->setMasterPid($this->master_pid);
                                    $new_process->setProcessType($process_type);
                                    $new_process->setRebootCount($process_reboot_count);
                                    $new_process->setStartTime();
                                    $this->process_wokers[$key][$process_worker_id] = $new_process;

                                    $new_process->start();

                                    $this->swooleEventAdd($new_process);

                                } catch (\Throwable $throwable) {
                                    if(isset($this->process_wokers[$key][$process_worker_id])) {
                                        unset($this->process_wokers[$key][$process_worker_id]);
                                    }
                                    $this->onHandleException->call($this, $throwable);
                                }

                            }
                        }
                        break;
                }
            } catch (\Throwable $throwable) {
                $this->onHandleException->call($this, $throwable);
            }
        }
    }

    /**
     * @param null $process
     */
    private function swooleEventAdd($process = null) {
        $process_workers = [];
        if(isset($process)) {
            if($process instanceof AbstractProcess) {
                $process_name = $process->getProcessName();
                $process_worker_id = $process->getProcessWorkerId();
                $key = md5($process_name);
                $process_workers[$key][$process_worker_id] = $process;
            }else {
                $this->onHandleException->call($this, new \Exception(__CLASS__.'::'.__FUNCTION__.' param $process must instance of AbstractProcess'));
                return false;
            }
        }else {
            $process_workers = $this->process_wokers;
        }

        foreach($process_workers as $key => $processes) {
            foreach($processes as $worker_id => $process) {
                $swooleProcess = $process->getSwooleProcess();
                \Swoole\Event::add($swooleProcess->pipe, function($pipe) use ($swooleProcess) {
                    $msg = $swooleProcess->read(64 * 1024);
                    if(is_string($msg)) {
                        $message = json_decode($msg, true);
                        list($msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id) = $message;
                    }
                    if($msg && isset($from_process_name) && isset($from_process_worker_id) && isset($to_process_name) && isset($to_process_worker_id) ) {
                        try {
                            if($to_process_name == $this->getMasterWorkerName()) {
                                $is_call_dynamic = false;
                                if(is_array($msg) && count($msg) == 3) {
                                    list($action, $dynamic_process_name, $dynamic_process_num) = $msg;
                                    switch ($action) {
                                        case ProcessManager::CREATE_DYNAMIC_WORKER :
                                            $is_call_dynamic = true;
                                            if(is_callable($this->onCreateDynamicProcess)) {
                                                $this->onCreateDynamicProcess->call($this, $dynamic_process_name, $dynamic_process_num, $from_process_name, $from_process_worker_id);
                                            }else {
                                                $this->createDynamicProcess($dynamic_process_name, $dynamic_process_num);
                                            }
                                            break;
                                        case ProcessManager::DESTROY_DYNAMIC_PROCESS:
                                            $is_call_dynamic = true;
                                            if(is_callable($this->onDestroyDynamicProcess)) {
                                                $this->onDestroyDynamicProcess->call($this, $dynamic_process_name, $dynamic_process_num, $from_process_name, $from_process_worker_id);
                                            }else {
                                                $this->destroyDynamicProcess($dynamic_process_name);
                                            }
                                            break;
                                    }
                                }
                                if($is_call_dynamic === false) {
                                    if(is_callable($this->onPipeMsg)) {
                                        $this->onPipeMsg->call($this, $msg, $from_process_name, $from_process_worker_id);
                                    }else {
                                        $this->writeByProcessName($from_process_name, $msg, $from_process_worker_id);
                                    }
                                }
                            }else {
                                if(is_callable($this->onProxyMsg)) {
                                    $this->onProxyMsg->call($this, $msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
                                }else {
                                    $this->writeByMasterProxy($msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
                                }
                            }
                        }catch(\Throwable $throwable) {
                            $this->onHandleException->call($this, $throwable);
                        }
                    }
                });
            }
        }

    }

    /**
     * @param int $master_pid
     */
    public function saveMasterPidTofile(int $master_pid) {
        @file_put_contents(PID_FILE, $master_pid);
    }

    /**
     * @param $status
     */
    public function saveStatusToFile(array $status = []) {
        if(empty($status)) {
            $status = $this->getProcessStatus();
        }
        @file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));
    }

    /**
     * dynamicCreateProcess 动态创建临时进程
     * @param string $process_name
     * @param int $process_num
     */
    public function createDynamicProcess(string $process_name, int $process_num = 2) {
        if($this->isMasterExiting()) {
            write_info("--------------【Warning】 master进程正在处于exiting退出状态，不能再动态创建子进程 --------------");
            return false;
        }

        $key = md5($process_name);
        $this->getDynamicProcessNum($process_name);

        if($process_num <= 0) {
            $process_num = 1;
        }
        
        $process_worker_num = $this->process_lists[$key]['process_worker_num'];
        $process_name = $this->process_lists[$key]['process_name'];
        $process_class = $this->process_lists[$key]['process_class'];
        if(isset($this->process_lists[$key]['dynamic_process_worker_num']) && $this->process_lists[$key]['dynamic_process_worker_num'] > 0) {
            $total_process_num = $process_worker_num + $this->process_lists[$key]['dynamic_process_worker_num'] + $process_num;
        }else {
            $total_process_num = $process_worker_num + $process_num;
            $this->process_lists[$key]['dynamic_process_worker_num'] = 0;
        }
        // 总的进程数，大于设置的进程数
        if($total_process_num > $this->process_lists[$key]['args']['max_process_num']) {
            $total_process_num = $this->process_lists[$key]['args']['max_process_num'];
        }
        $running_process_worker_num = $process_worker_num + $this->process_lists[$key]['dynamic_process_worker_num'];
        $async = $this->process_lists[$key]['async'];
        $args = $this->process_lists[$key]['args'];
        $extend_data = $this->process_lists[$key]['extend_data'];
        $enable_coroutine = $this->process_lists[$key]['enable_coroutine'];
        // 超出限定总数，禁止动态创建
        if($running_process_worker_num >= $total_process_num) {
            write_info("--------------【Warning】 子进程已达到最大的限制数量({$total_process_num}个)，禁止动态创建子进程 --------------");
            return false;
        }

        for($worker_id = $running_process_worker_num; $worker_id < $total_process_num; $worker_id++) {
            try {
                $process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
                $process->setProcessWorkerId($worker_id);
                $process->setMasterPid($this->master_pid);
                $process->setProcessType(AbstractProcess::PROCESS_DYNAMIC_TYPE);// 动态进程类型=2
                $process->setStartTime();
                $this->process_wokers[$key][$worker_id] = $process;
                $process->start();
                $this->swooleEventAdd($process);
            }catch(\Throwable $throwable) {
                unset($this->process_wokers[$key][$worker_id], $process);
                $this->onHandleException->call($this, $throwable);
            }
        }
        $this->getDynamicProcessNum($process_name);
    }

    /**
     * destroyDynamicProcess 销毁动态创建的进程
     * @param string $process_name
     * @param int $process_num
     */
    public function destroyDynamicProcess(string $process_name, $process_num = -1) {
        $process_workers = $this->getProcessByName($process_name, -1);
        $key = md5($process_name);
        foreach($process_workers as $worker_id=>$process) {
            if($process->isDynamicProcess()) {
                $this->writeByProcessName($process_name, AbstractProcess::WORKERFY_PROCESS_EXIT_FLAG, $worker_id);
                // 动态进程销毁，需要自减
                $this->process_lists[$key]['dynamic_process_worker_num']--;
            }
        }
    }

    /**
     * getDynamicProcessNum
     * @param  string $process_name
     * @return int
     */
    public function getDynamicProcessNum(string $process_name) {
        $dynamic_process_num = 0;
        $key = md5($process_name);
        $process_workers = $this->getProcessByName($process_name, -1);
        foreach($process_workers as $worker_id=>$process) {
            if($process->isDynamicProcess()) {
                ++$dynamic_process_num;
            }
        }
        
        $this->process_lists[$key]['dynamic_process_worker_num'] = $dynamic_process_num;

        return $dynamic_process_num;
    }

    /**
     * daemon
     * @param bool $is_daemon
     */
    private function daemon($is_daemon) {
        if(defined('IS_DAEMON') && IS_DAEMON == true) {
            $this->is_daemon = IS_DAEMON;
        }

        if($is_daemon) {
            $this->is_daemon = $is_daemon;
        }

        if($this->is_daemon) {
            if(!isset($this->start_daemon)) {
                \Swoole\Process::daemon();
                $this->start_daemon = true;
            }
        }
    }

    /**
     * @return int
     */
    public function getMasterPid() {
        return $this->master_pid;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function isMaster(string $process_name) {
        if($process_name == $this->getMasterWorkerName()) {
            return true;
        }
        return false;
    }

    /**
     * getProcessStatus 获取进程状态信息
     * @return array
     */
    public function getProcessStatus(int $running_status = 1) {
        $status = [];
        $children_num = 0;
        foreach($this->process_wokers as $key=>$processes) {
            $children_num += count($processes);
        }
        $cpu_num = swoole_cpu_num();
        $php_version = PHP_VERSION;
        $swoole_version = swoole_version();
        $enable_cli_pipe = is_resource($this->cli_pipe_fd) ? 1 : 0;
        list($msg_sysvmsg_info, $sysKernel) = $this->getSysvmsgInfo();
        $swoole_table_info = $this->getSwooleTableInfo(false);
        $status['master'] = [
            'start_script_file' => START_SCRIPT_FILE,
            'pid_file' => PID_FILE,
            'running_status' => $running_status,
            'master_pid' => $this->getMasterPid(),
            'cpu_num' => $cpu_num,
            'php_version' => $php_version,
            'swoole_version' => $swoole_version,
            'enable_cli_pipe' => $enable_cli_pipe,
            'msg_sysvmsg_kernel' => $sysKernel,
            'msg_sysvmsg_info' => $msg_sysvmsg_info,
            'swoole_table_info' => $swoole_table_info,
            'children_num' => $children_num,
            'children_process' => [],
            'stop_time' => !$running_status ? date("Y-m-d H:i:s") : '',
            'report_time' => date("Y-m-d H:i:s")
        ];
        // 运行中的总的子进程
        $running_children_num = 0;
        // 获取子进程status
        $children_status = [];
        foreach($this->process_wokers as $key => $processes) {
            ksort($processes);
            foreach($processes as $process_worker_id => $process) {
                $process_name = $process->getProcessName();
                $worker_id = $process->getProcessWorkerId();
                $pid = $process->getPid();
                $start_time = $process->getStartTime();
                $reboot_count = $process->getRebootCount();
                $process_type = $process->getProcessType();
                if($process_type == AbstractProcess::PROCESS_STATIC_TYPE) {
                    $process_type = AbstractProcess::PROCESS_STATIC_TYPE_NAME;
                }else {
                    $process_type = AbstractProcess::PROCESS_DYNAMIC_TYPE_NAME;
                }
                if(\Swoole\Process::kill($pid, 0)) {
                    // 循环上报状态的时候可以处理一些僵尸进程
                    $this->rebootOrExitHandle();
                    $process_status = 'running';
                    $children_status[$process_name][$worker_id] = [
                        'process_name' => $process_name,
                        'worker_id' => $worker_id,
                        'pid' => $pid,
                        'process_type' => $process_type,
                        'start_time' => $start_time,
                        'reboot_count' => $reboot_count,
                        'status' => $process_status
                    ];
                    $running_children_num++;
                }
            }
            $status['master']['children_process'] = $children_status;
            unset($processes);
        }
        $status['master']['children_num'] = $running_children_num;
        return $status;
    }

    /**
     * installReportStatus
     */
    private function installReportStatus() {
        $default_tick_time = self::REPORT_STATUS_TICK_TIME;
        if(defined('WORKERFY_REPORT_TICK_TIME')) {
            $tick_time = WORKERFY_REPORT_TICK_TIME;
        }else {
            $tick_time = $default_tick_time;
        }
        if($tick_time < $default_tick_time) {
            $tick_time = $default_tick_time;
        }
        // 必须设置不使用协程，否则master进程存在异步IO,后面子进程reboot()时
        //出现unable to create Swoole\Process with async-io threads
        \Swoole\Timer::set([
            'enable_coroutine' => false,
        ]);
        $timer_id = \Swoole\Timer::tick($tick_time * 1000, function($timer_id) {
            try {
                $status = $this->getProcessStatus();
                // save status
                file_put_contents(STATUS_FILE, json_encode($status, JSON_UNESCAPED_UNICODE));
                // callable todo
                if(is_callable($this->onReportStatus)) {
                    $this->onReportStatus->call($this, $status);
                }
            }catch (\Throwable $throwable) {
                $this->onHandleException($throwable);
            }
        });
        // master destroy before clear timer_id
        if($timer_id) {
            register_shutdown_function(function() use($timer_id) {
                \Swoole\Timer::clear($timer_id);
            });
        }
    }

    /**
     * getProcessByName 通过名称获取一个进程
     * @param string $process_name
     * @param int $process_worker_id
     * @throws
     * @return mixed|null
     */
	public function getProcessByName(string $process_name, int $process_worker_id = 0) {
        $key = md5($process_name);
        if(isset($this->process_wokers[$key][$process_worker_id])){
            return $this->process_wokers[$key][$process_worker_id];
        }else if($process_worker_id == -1) {
            return $this->process_wokers[$key];
        }else {
            throw new \Exception("进程={$process_name}, worker_id={$process_worker_id} is missing ");
        }
    }

    /**
     * getProcessByPid 通过进程id获取进程
     * @param  int    $pid
     * @return mixed
     */
    public function getProcessByPid(int $pid) {
    	$p = null;
       	foreach ($this->process_wokers as $key => $processes) {
            foreach ($processes as $worker_id => $process) {
                if($process->getPid() == $pid) {
                    $p = $process;
                    break;
                }
            }
            if($p) {
                break;
            }
       	}
       	return $p;
    }

    /**
     * @param string $process_name
     * @param int $process_worker_id
     * @return mixed
     */
    public function getPidByName(string $process_name, int $process_worker_id) {
        $process = $this->getProcessByName($process_name, $process_worker_id);
        return is_object($process) ? $process->getPid() : null;
    }

    /**
     * getProcessWorkerId
     * @return int
     */
    public function getMasterWorkerId() {
        return $this->master_worker_id;
    }

    /**
     * getMasterWorkerName
     * @return string
     */
    public function getMasterWorkerName() {
        return ProcessManager::MASTER_WORKER_NAME;
    }

    /**
     * master是否正在退出状态中，这个状态中，不再接受处理动态创建进程
     * @return bool
     */
    public function isMasterExiting() {
        return $this->is_exit;
    }

    /**
     * @param string $process_name
     * @param mixed $data
     * @param int $process_worker_id
     * @throws
     * @return bool
     */
    public function writeByProcessName(string $process_name, $data, int $process_worker_id = 0) {
        if($this->isMaster($process_name)) {
            throw new \Exception("Master process can not write msg to master process self");
        }
        if(!$this->isRunning()) {
            throw new \Exception("Master process is not start, you can not use writeByProcessName(), please checkout it");
        }
        $process_workers = [];
        $process = $this->getProcessByName($process_name, $process_worker_id);
        if(is_object($process) && $process instanceof AbstractProcess) {
            $process_workers = [$process_worker_id => $process];
        }else if(is_array($process)) {
            $process_workers = $process;
        }
        $is_proxy = false;
        $message = json_encode([$data, $this->getMasterWorkerName(), $this->getMasterWorkerId(), $is_proxy], JSON_UNESCAPED_UNICODE);
        foreach($process_workers as $process_worker_id => $process) {
            $process->getSwooleProcess()->write($message);
        }
    }

    /**
     * master代理转发
     * @param mixed $data
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param string $to_process_name
     * @param int $to_process_worker_id
     * @return bool
     */
    public function writeByMasterProxy($data, string $from_process_name, int $from_process_worker_id, string $to_process_name, int $to_process_worker_id) {
        if($this->isMaster($to_process_name)) {
            return false;
        }
        $process_workers = [];
        $process = $this->getProcessByName($to_process_name, $to_process_worker_id);
        if(is_object($process) && $process instanceof AbstractProcess) {
            $process_workers = [$to_process_worker_id => $process];
        }else if(is_array($process)) {
            $process_workers = $process;
        }
        $proxy = true;
        $message = json_encode([$data, $from_process_name, $from_process_worker_id, $proxy], JSON_UNESCAPED_UNICODE);
        foreach($process_workers as $process_worker_id => $process) {
            $process->getSwooleProcess()->write($message);
        }
    }

    /**
     * 广播消息至worker
     * @param string|null $process_name
     * @param mixed $data
     */
    public function broadcastProcessWorker(string $process_name = null, $data = '') {
        $message = json_encode([$data, $this->getMasterWorkerName(), $this->getMasterWorkerId()], JSON_UNESCAPED_UNICODE);
        if($process_name) {
            $key = md5($process_name);
            if(isset($this->process_wokers[$key])) {
                $process_workers = $this->process_wokers[$key];
                foreach($process_workers as $process_worker_id => $process) {
                    $process->getSwooleProcess()->write($message);
                }
            }else {
                $exception = new \Exception(__CLASS__.'::'.__FUNCTION__." not exist {$process_name}, please check it");
            }
        }else {
            $exception = new \Exception(__CLASS__.'::'.__FUNCTION__." second param process_name is empty");
        }

        if(isset($exception) && $exception instanceof \Throwable) {
            $this->onHandleException->call($this, $exception);
        }
    }

    /**
     * @param $signal
     * @param callable $function
     */
    public function addSignal($signal, callable $function) {
        // forbidden over has registered signal
        if(!in_array($signal, [SIGTERM, SIGUSR2, SIGUSR1, SIGCHLD])) {
            $this->signal[$signal] = [$signal, $function];
        }
    }

    /**
     * registerSignal
     */
    private function registerSignal() {
        if(!empty($this->signal)) {
            foreach($this->signal as $signal_info) {
                list($signal, $function) = $signal_info;
                try {
                    \Swoole\Process::signal($signal, $function);
                }catch (\Throwable $throwable) {
                    $this->onHandleException->call($this, $throwable);
                }
            }
        }
    }

    /**
     * @param bool $is_create_pipe
     */
    public function createCliPipe(bool $is_create_pipe = false) {
        $this->is_create_pipe = $is_create_pipe;
    }

    /**
     * 创建管道
     * @throws null
     */
    private function installCliPipe() {
        if(!$this->is_create_pipe) {
            return false;
        }

        $pipe_file = $this->getCliPipeFile();
        if(file_exists($pipe_file)) {
            unlink($pipe_file);
        }

        if(!posix_mkfifo($pipe_file, 0777)) {
            throw new \Exception("Create Cli Pipe failed");
        }

        $this->cli_pipe_fd = fopen($pipe_file, 'w+');
        is_resource($this->cli_pipe_fd) && stream_set_blocking($this->cli_pipe_fd, false);
        \Swoole\Event::add($this->cli_pipe_fd, function() {
            try{
                $msg = fread($this->cli_pipe_fd, 8192);
                $is_call_clipipe = true;
                if(($pipe_msg_arr = json_decode($msg, true)) !== null) {
                    if(is_array($pipe_msg_arr) && count($pipe_msg_arr) == 3) {
                        list($action, $process_name, $num) = $pipe_msg_arr;
                        switch($action) {
                            case 'add' :
                                !isset($num) && $num = 1;
                                $is_call_clipipe = false;
                                $this->addProcessByCli($process_name, $num);
                                break;
                            case 'remove' :
                                $is_call_clipipe = false;
                                $this->removeProcessByCli($process_name, $num);
                                break;
                            case 'status' :
                                $is_call_clipipe = false;
                                $this->masterStatusToCliFifoPipe($process_name);
                                break;
                        }
                    }
                }
                if($is_call_clipipe === true && $this->onCliMsg instanceof \Closure) {
                    $this->onCliMsg->call($this, $msg);
                }
            }catch (\Throwable $throwable) {
                $this->onHandleException->call($this, $throwable);
            }
        });
    }

    /**
     * addProcessByCli
     * @param string $process_name
     * @param int $num
     */
    private function addProcessByCli(string $process_name, int $num = 1) {
        $key = md5($process_name);
        if(isset($this->process_lists[$key])) {
            $this->createDynamicProcess($process_name, $num);
        }else {
            write_info("--------------【Warning】Not exist children_process_name = {$process_name}, add failed --------------");
        }

    }

    /**
     * removeProcessByCli
     * @param string $process_name
     * @param int $num
     */
    private function removeProcessByCli(string $process_name, int $num = 1) {
        $key = md5($process_name);
        if(isset($this->process_lists[$key])) {
            $this->destroyDynamicProcess($process_name, $num);
        }else {
            write_info("--------------【Warning】Not exist children_process_name = {$process_name}, remove failed --------------");
        }
    }

    /**
     * getCliPipeFile
     * @return string
     */
    public function getCliPipeFile() {
        if(function_exists('getCliPipeFile')) {
            $pipe_file = getCliPipeFile();
        }else {
            $path_info = pathinfo(PID_FILE);
            $path_dir = $path_info['dirname'];
            $file_name = $path_info['basename'];
            $ext = $path_info['extension'];
            $pipe_file_name = str_replace($ext,'pipe', $file_name);
            $pipe_file = $path_dir.'/'.$pipe_file_name;
        }
        return $pipe_file;
    }

    /**
     * 获取cli命令行传入的参数选项
     * @param string $name
     * @return array|false|string
     */
    public function getCliEnvParam(string $name) {
        $value = @getenv($name);
        if($value !== false) {
            return $value;
        }
        return null;
    }

    /**
     * installRegisterShutdownFunction
     */
    private function installRegisterShutdownFunction() {
        register_shutdown_function(function() {
            // close pipe fofo
            if(is_resource($this->cli_pipe_fd)) {
                \Swoole\Event::del($this->cli_pipe_fd);
                fclose($this->cli_pipe_fd);
                @unlink($this->getCliPipeFile());
            }
            // remove sysvmsg queue
            $sysvmsgManager = \Workerfy\Memory\SysvmsgManager::getInstance();
            $sysvmsgManager->destroyMSgQueue();
            unset($sysvmsgManager);
            // remove signal
            @\Swoole\Process::signal(SIGUSR1, null);
            @\Swoole\Process::signal(SIGUSR2, null);
            @\Swoole\Process::signal(SIGTERM, null);
            write_info("--------------【Warning】 master进程stop, master_pid={$this->master_pid}--------------");

        });
    }

    /**
     * setMasterPid
     */
    private function setMasterPid() {
        if(!isset($this->master_pid)) {
            $this->master_pid = posix_getpid();
        }
        cli_set_process_title("php-master:".START_SCRIPT_FILE);
        defined('MASTER_PID') OR define('MASTER_PID', $this->master_pid);
    }

    /**
     * setStartTime 设置启动时间
     */
    private function setStartTime() {
        $this->start_time = date('Y-m-d H:i:s', strtotime('now'));
    }

    /**
     * flag start
     * @return bool
     */
    protected function running() {
        $this->is_running = true;
    }

    /**
     * master && children process是否启动
     * @return bool
     */
    public function isRunning() {
        if(isset($this->is_running) && $this->is_running === true) {
            return true;
        }
        return false;
    }

    /**
     * getSwooleTableInfo
     * @return string
     */
    public function getSwooleTableInfo(bool $simple = true) {
        $swoole_table_info = "Disable swoole table(没启用)";
        if(defined('ENABLE_WORKERFY_SWOOLE_TABLE') && ENABLE_WORKERFY_SWOOLE_TABLE == 1) {
            $tableManager = \Workerfy\Memory\TableManager::getInstance();
            if($simple) {
                $all_table_name = $tableManager->getAllTableName();
                if(!empty($all_table_name) && is_array($all_table_name)) {
                    $all_table_name_str = implode($all_table_name, ',');
                    $swoole_table_info = "[{$all_table_name_str}]";
                }
            }else {
                $all_table_info = $tableManager->getAllTableKeyMapRowValue();
                if(!empty($all_table_info)) {
                    $swoole_table_info = $all_table_info;
                }else {
                    $swoole_table_info = "swoole table(已启用),但没有设置table_name";
                }
            }

        }
        return $swoole_table_info;
    }

    /**
     * getSysvmsgInfo
     */
    public function getSysvmsgInfo() {
        $msg_sysvmsg_info = 'Disable sysvmsg(没启用)';
        $sysvmsgManager = \Workerfy\Memory\SysvmsgManager::getInstance();
        if(defined('ENABLE_WORKERFY_SYSVMSG_MSG') && ENABLE_WORKERFY_SYSVMSG_MSG == 1) {
            $msg_queue_info = $sysvmsgManager->getAllMsgQueueWaitToPopNum();
            if(!empty($msg_queue_info)) {
                $msg_sysvmsg_info = '';
                foreach($msg_queue_info as $info) {
                    list($msg_queue_name, $wait_to_read_num) = $info;
                    $msg_sysvmsg_info .= "[队列名称:$msg_queue_name,消息数量:$wait_to_read_num]".',';
                }
                $msg_sysvmsg_info = trim($msg_sysvmsg_info, ',');
            }
        }
        $sysKernelInfo = array_values($sysvmsgManager->getSysKernelInfo(true));
        list($msgmax, $msgmnb, $msgmni) = $sysKernelInfo;
        $sysKernel = "[单个消息体最大字节msgmax:{$msgmax},队列的最大容量msgmnb:{$msgmnb},队列最大个数:{$msgmni}]";
        return [$msg_sysvmsg_info, $sysKernel];
    }

    /**
     * statusInfoFormat
     * @param $process_name
     * @param $worker_id
     * @param $pid
     * @param $status
     * @param null $start_time
     * @param int $reboot_count
     * @return string
     */
    private function statusInfoFormat($process_name, $worker_id, $pid, $status, $start_time = null, $reboot_count = 0, $process_type = '') {
        if($process_name == $this->getMasterWorkerName()) {
            $children_num = 0;
            foreach($this->process_wokers as $key=>$processes) {
                $children_num += count($processes);
            }
            $start_script_file = START_SCRIPT_FILE;
            $pid_file = PID_FILE;
            $cpu_num = swoole_cpu_num();
            $php_version = PHP_VERSION;
            $swoole_version = swoole_version();
            $enable_cli_pipe = is_resource($this->cli_pipe_fd) ? 1 : 0;
            list($msg_sysvmsg_info, $sysKernel)= $this->getSysvmsgInfo();
            $swoole_table_info = $this->getSwooleTableInfo();

            $info =
<<<EOF
 主进程status:
        | 
        进程名称name: $process_name
        进程编号worker_id: $worker_id
        进程master_id: $pid
        进程状态status：$status
        启动时间start_time：$start_time
        start_script_file: $start_script_file
        pid_file: $pid_file
        children_num: $children_num
        cpu_num: $cpu_num
        php_version: $php_version
        swoole_version: $swoole_version
        enable_cli_pipe: $enable_cli_pipe
        sysvmsg_kernel: $sysKernel
        sysvmsg_status: $msg_sysvmsg_info
        swoole_table_name: $swoole_table_info
        
 
 子进程status:
        |
EOF;
        }else {
            $info =
<<<EOF
        
        【{$process_name}@{$worker_id}】【{$process_type}】: 进程名称name: $process_name, 进程编号worker_id: $worker_id, 进程Pid: $pid, 进程状态status：$status, 启动(重启)时间：$start_time, 重启次数：$reboot_count
EOF;

        }
        return $info;
    }

}
