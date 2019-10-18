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


    public $onStart;
    public $onPipeMsg;
    public $onProxyMsg;
    public $onCreateDynamicProcess;
    public $onDestroyDynamicProcess;
    public $onHandleException;
    public $onExit;

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
        if (isset($this->process_lists[$key])) {
            throw new \Exception(__CLASS__ . " Error : you can not add the same process : $process_name", 1);
        }
        if(!$enable_coroutine) {
            $enable_coroutine = true;
        }
        if(!$async) {
            $async = true;
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
    	if(!empty($this->process_lists)) {
            $this->daemon($is_daemon);
            if(!isset($this->master_pid)) {
                $this->master_pid = posix_getpid();
            }
            foreach($this->process_lists as $key => $list) {
    			$process_worker_num = $list['process_worker_num'] ?? 1;
    			for($worker_id = 0; $worker_id < $process_worker_num; $worker_id++) {
    				try {
	    				$process_name = $list['process_name'];
			        	$process_class = $list['process_class'];
			        	$async = $list['async'] ?? true;
			        	$args = $list['args'] ?? [];
			        	$extend_data = $list['extend_data'] ?? null;
			        	$enable_coroutine = $list['enable_coroutine'] ?? true;
		    			$process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
		    			$process->setProcessWorkerId($worker_id);
                        $process->setStartTime();
                        if(!isset($this->process_wokers[$key][$worker_id])) {
                            $this->process_wokers[$key][$worker_id] = $process;
                        }
                        usleep(50000);
	    			}catch(\Throwable $t) {
                        $this->onHandleException->call($this, $t);
	    			}
    			}
    		}
    		foreach($this->process_wokers as $key => $process_woker) {
    		    foreach($process_woker as $worker_id => $process) {
                    $process->start();
                }
            }
            $this->installSigchldsignal();
            $this->installMasterStopSignal();
            $this->installMasterReloadSignal();
            $this->installMasterStatusSignal();
            $this->registerSignal();
    		$this->swooleEventAdd();
    		$this->setStartTime();
    	}
    	// 设置在process start之后
    	$master_pid = $this->getMasterPid();
        if($master_pid && is_callable($this->onStart)) {
            $this->onStart && $this->onStart->call($this, $master_pid);
        }
        sleep(3);
    	return $master_pid;
    }

    /**
     * 主进程注册监听退出信号,逐步发送退出指令至子进程退出，子进程完全退出后，master进程最后退出
     * 每个子进程收到退出指令后，等待wait_time后正式退出，那么在这个wait_time过程
     * 子进程逻辑应该通过$this->isRebooting() || $this->isExiting()判断是否在退出状态中，这个状态中不能再处理新的任务数据
     */
    private function installMasterStopSignal() {
        \Swoole\Process::signal(SIGTERM, function($signo) {
            foreach($this->process_wokers as $key => $processes) {
                foreach($processes as $worker_id => $process) {
                    $process_name = $process->getProcessName();
                    $this->writeByProcessName($process_name, AbstractProcess::WORKERFY_PROCESS_EXIT_FLAG, $worker_id);
                }
                usleep(1000000);
            }
            $this->is_exit = true;
        });
    }

    /**
     * 父进程的status指令监听SIGUSR1信号
     */
    public function installMasterStatusSignal() {
        \Swoole\Process::signal(SIGUSR1, function($signo) {
            $master_info = $this->statusInfoFormat($this->getMasterWorkerName(), $this->getMasterWorkerId(), $this->getMasterPid(), 'running', $this->start_time);
            write_info($master_info, 'green');
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
                        $status = 'running';
                    }else {
                        $status = 'stoped';
                        unset($this->process_wokers[$key][$process_worker_id]);
                    }

                    $info = $this->statusInfoFormat($process_name, $worker_id, $pid, $status, $start_time, $reboot_count, $process_type);
                    if($status == 'stoped') {
                        write_info($info);
                    }else {
                        write_info($info,'green');
                    }
                }
                unset($processes);
                usleep(100000);
            }
        });
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
                usleep(1000000);
            }
            $this->is_exit = true;
        });
    }

    /**
     * installSigchldsignal 注册回收子进程信号
     */
    private function installSigchldsignal() {
        \Swoole\Process::signal(SIGCHLD, function($signo) {
  			//必须为false，非阻塞模式
		  	while($ret = \Swoole\Process::wait(false)) {
		      	$pid = $ret['pid'];
                $code = $ret['code'];
                switch ($code) {
                    // exit 信号
                    case 0       :
                    case SIGTERM :
                    case SIGKILL :
                        $process = $this->getProcessByPid($pid);
                        $process_name = $process->getProcessName();
                        $process_worker_id = $process->getProcessWorkerId();
                        $key = md5($process_name);
                        if(isset($this->process_wokers[$key][$process_worker_id])) {
                            unset($this->process_wokers[$key][$process_worker_id]);
                            if(count($this->process_wokers[$key]) == 0) {
                                unset($this->process_wokers[$key]);
                            }
                        }
                        if(count($this->process_wokers) == 0) {
                            try{
                                $this->onExit->call($this);
                            }catch (\Throwable $t) {
                                $this->onHandleException->call($this, $t);
                            }finally {
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
                                    $new_process->setProcessType($process_type);
                                    $new_process->setRebootCount($process_reboot_count);
                                    $new_process->setStartTime();
                                    if(!isset($this->process_wokers[$key][$process_worker_id])) {
                                        $this->process_wokers[$key][$process_worker_id] = $new_process;
                                    }
                                    $new_process->start();
                                }catch(\Throwable $t) {
                                    $this->onHandleException->call($this, $t);
                                }
                                $this->swooleEventAdd($new_process);
                            }
                        }
                        break;
                }
		  	}
		});
    }

    /**
     * @param null $process
     */
    private function swooleEventAdd($process = null) {
        if(isset($process)) {
            if($process instanceof AbstractProcess) {
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
                                $is_call_pipe = true;
                                if(is_array($msg) && count($msg) == 3) {
                                    list($action, $dynamic_process_name, $dynamic_process_num) = $msg;
                                    switch ($action) {
                                        case ProcessManager::CREATE_DYNAMIC_WORKER :
                                            $is_call_pipe = false;
                                            $this->onCreateDynamicProcess->call($this, $dynamic_process_name, $dynamic_process_num, $from_process_name, $from_process_worker_id);
                                            break;
                                        case ProcessManager::DESTROY_DYNAMIC_PROCESS:
                                            $is_call_pipe = false;
                                            $this->onDestroyDynamicProcess->call($this, $dynamic_process_name, $dynamic_process_num, $from_process_name, $from_process_worker_id);
                                            break;
                                    }
                                }
                                if($is_call_pipe === true) {
                                    $this->onPipeMsg->call($this, $msg, $from_process_name, $from_process_worker_id);
                                }
                            }else {
                                $this->onProxyMsg->call($this, $msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
                            }
                        }catch(\Throwable $t) {
                            $this->onHandleException->call($this, $t);
                        }
                    }
                });
            }else {
                $e =  new \Exception(__CLASS__.'::'.__FUNCTION__.' param $process must instance of AbstractProcess');
                $this->onHandleException->call($this, $e);
            }
        }else {
            foreach($this->process_wokers as $key => $processes) {
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
                                                $this->onCreateDynamicProcess->call($this, $dynamic_process_name, $dynamic_process_num, $from_process_name, $from_process_worker_id);
                                                break;
                                            case ProcessManager::DESTROY_DYNAMIC_PROCESS:
                                                $is_call_dynamic = true;
                                                $this->onDestroyDynamicProcess->call($this, $dynamic_process_name, $dynamic_process_num, $from_process_name, $from_process_worker_id);
                                                break;
                                        }
                                    }
                                    if($is_call_dynamic === false) {
                                        $this->onPipeMsg->call($this, $msg, $from_process_name, $from_process_worker_id);
                                    }
                                }else {
                                    $this->onProxyMsg->call($this, $msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
                                }
                            }catch(\Throwable $t) {
                                $this->onHandleException->call($this, $t);
                            }
                        }
                    });
                }
            }
        }
    }

    /**
     * dynamicCreateProcess 动态创建临时进程
     * @param string $process_name
     * @param int $process_num
     */
    public function createDynamicProcess(string $process_name, int $process_num = 2) {
        $key = md5($process_name);
        $process_worker_num = $this->process_lists[$key]['process_worker_num'];
        if($this->isMasterExiting()) {
            return;
        }
        $process_name = $this->process_lists[$key]['process_name'];
        $process_class = $this->process_lists[$key]['process_class'];
        if(isset($this->process_lists[$key]['dynamic_process_worker_num']) && $this->process_lists[$key]['dynamic_process_worker_num'] > 0) {
            $total_process_num = $process_worker_num + $this->process_lists[$key]['dynamic_process_worker_num'] + $process_num;
        }else {
            $total_process_num = $process_worker_num + $process_num;
            $this->process_lists[$key]['dynamic_process_worker_num'] = 0;
        }
        $running_process_worker_num = $process_worker_num + $this->process_lists[$key]['dynamic_process_worker_num'];
        $async = $this->process_lists[$key]['async'];
        $args = $this->process_lists[$key]['args'];
        $extend_data = $this->process_lists[$key]['extend_data'];
        $enable_coroutine = $this->process_lists[$key]['enable_coroutine'];
        for($worker_id = $running_process_worker_num; $worker_id < $total_process_num; $worker_id++) {
            try {
                // 动态创建成功，需要自加
                $this->process_lists[$key]['dynamic_process_worker_num']++;
                $process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
                $process->setProcessWorkerId($worker_id);
                $process->setProcessType(AbstractProcess::PROCESS_DYNAMIC_TYPE);// 动态进程类型=2
                $process->setStartTime();
                if(!isset($this->process_wokers[$key][$worker_id])) {
                    $this->process_wokers[$key][$worker_id] = $process;
                }
                $process->start();
            }catch(\Throwable $t) {
                unset($this->process_wokers[$key][$worker_id]);
                // 发生异常，需要自减
                $this->process_lists[$key]['dynamic_process_worker_num']--;
                $this->onHandleException->call($this, $t);

            }
            $this->swooleEventAdd($process);
            usleep(50000);
        }
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
                sleep(1);
            }
        }
    }

    /**
     * daemon
     * @param bool $is_daemon
     */
    public function daemon($is_daemon) {
        if($is_daemon) {
            $this->is_daemon = $is_daemon;
        }else {
            if(IS_DAEMON == true) {
                $this->is_daemon = IS_DAEMON;
            }
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
     * getProcessByName 通过名称获取一个进程
     * @param string $process_name
     * @param int $process_worker_id
     * @return mixed|null
     */
	public function getProcessByName(string $process_name, int $process_worker_id = 0) {
        $key = md5($process_name);
        if(isset($this->process_wokers[$key][$process_worker_id])){
            return $this->process_wokers[$key][$process_worker_id];
        }else if($process_worker_id == -1) {
            return $this->process_wokers[$key];
        }else {
            return null;
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
     * @throws \Exception
     */
    public function getPidByName(string $process_name, int $process_worker_id) {
        $process = $this->getProcessByName($process_name, $process_worker_id);
        if(method_exists($process, 'getPid')) {
            return $process->getPid();
        }else {
            throw new \Exception(get_class($process)."::getPid() method is not exist");
        }
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
     * @return bool
     */
    public function writeByProcessName(string $process_name, $data, int $process_worker_id = 0) {
        if($this->isMaster($process_name)) {
            return false;
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
            }
        }else {
            $e = new \Exception(__CLASS__.'::'.__FUNCTION__." second param process_name is empty");
            $this->onHandleException->call($this, $e);
        }
    }

    /**
     * @param $signal
     * @param callable $function
     */
    public function addSignal($signal, callable $function) {
        $this->signal[$signal] = [$signal, $function];
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
                }catch (\Exception $e) {
                    $this->onHandleException->call($this, $e);
                }
            }
        }
    }

    /**
     * setStartTime 设置启动时间
     */
    private function setStartTime() {
        $this->start_time = date('Y-m-d H:i:s', strtotime('now'));
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
            $cpu_num = swoole_cpu_num();
            $php_version = PHP_VERSION;
            $swoole_version = swoole_version();
            $info =
<<<EOF
 主进程status:
        |
        master_process: 进程名称name: $process_name, 进程编号worker_id: $worker_id, 进程Pid: $pid, 进程状态status：$status, 启动时间：$start_time
        children_num: $children_num
        cpu_num: $cpu_num
        php_version: $php_version
        swoole_version: $swoole_version
        
 
 子进程status:
EOF;
        }else {
            $info =
<<<EOF
        |
        【{$process_name}@{$worker_id}】children_process【{$process_type}】: 进程名称name: $process_name, 进程编号worker_id: $worker_id, 进程Pid: $pid, 进程状态status：$status, 启动(重启)时间：$start_time, 重启次数：$reboot_count
EOF;

        }
        return $info;
    }
}
