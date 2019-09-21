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

class ProcessManager {

    use \Workerfy\Traits\SingletonTrait;

    private $process_lists = [];

	private $process_wokers = [];

	private $process_pid_map = [];

	private $master_pid;

    private $master_worker_id = 0;

    private $signal = [];

    public $onStart;
    public $onPipeMsg;
    public $onProxyMsg;
    public $onDynamicCreateProcess;
    public $onDestroyDynamicProcess;
    public $onExit;

    const MASTER_WORKER_NAME = 'master_worker';
    const AUTO_CREATE_WORKER = 'auto_create_worker';
    const DESTROY_DYNAMIC_PROCESS = 'destroy_dynamic_process';

    /**
     * ProcessManager constructor.
     * @param mixed ...$args
     */
	public function __construct(...$args) {
        \Swoole\Runtime::enableCoroutine(true);
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
            if($is_daemon) {
                $this->daemon();
            }
            if(!isset($this->master_pid)) {
                $this->master_pid = posix_getpid();
            }

    		foreach($this->process_lists as $key => $list) {
    			$process_worker_num = $list['process_worker_num'] ?? 1;
    			for($i = 0; $i < $process_worker_num; $i++) {
    				try {
	    				$process_name = $list['process_name'];
			        	$process_class = $list['process_class'];
			        	$async = $list['async'] ?? true;
			        	$args = $list['args'] ?? [];
			        	$extend_data = $list['extend_data'] ?? null;
			        	$enable_coroutine = $list['enable_coroutine'] ?? true;
		    			$process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
		    			$process->setProcessWorkerId($i);
                        if(!isset($this->process_wokers[$key][$i])) {
                            $this->process_wokers[$key][$i] = $process;
                        }
                        usleep(50000);
	    			}catch(\Throwable $t) {
	    				throw new \Exception($t->getMessage());
	    			}
    			}
    		}
    		foreach($this->process_wokers as $key => $process_woker) {
    		    foreach($process_woker as $worker_id => $process) {
                    $process->start();
                }
            }
            $this->signal();
    		$this->registerSignal();
    		$this->swooleEventAdd();
    	}
    	$master_pid = $this->getMasterPid();
        if($master_pid && is_callable($this->onStart)) {
            $this->onStart && $this->onStart->call($this, $master_pid);
        }
    	return $master_pid;
    }

    /**
     * signal
     */
    private function signal() {
        \Swoole\Process::signal(SIGCHLD, function($signo) {
  			//必须为false，非阻塞模式
		  	while($ret = \Swoole\Process::wait(false)) {
		      	$pid = $ret['pid'];
                $signal = $ret['signal'];
                switch ($signal) {
                    case 0  :
                    case 15 :
                        if(!(\Swoole\Process::kill($pid, 0))) {
                            $process = $this->getProcessByPid($pid);
                            $process_name = $process->getProcessName();
                            $process_worker_id = $process->getProcessWorkerId();
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
                                    $process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
                                    $process->setProcessWorkerId($process_worker_id);
                                    if(!isset($this->process_wokers[$key][$process_worker_id])) {
                                        $this->process_wokers[$key][$process_worker_id] = $process;
                                    }
                                    $process->start();
                                }catch(\Throwable $t) {
                                    throw new \Exception($t->getMessage());
                                }
                                $this->swooleEventAdd($process);
                            }
                        }
                        break;
                    case 9 :
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
                                throw new \Exception($t->getMessage());
                            }finally {
                                exit;
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
                                switch ($msg) {
                                    case ProcessManager::AUTO_CREATE_WORKER :
                                        $this->onDynamicCreateProcess->call($this, $msg, $from_process_name, $from_process_worker_id);
                                        break;
                                    case ProcessManager::DESTROY_DYNAMIC_PROCESS:
                                        $this->onDestroyDynamicProcess->call($this, $msg, $from_process_name, $from_process_worker_id);
                                        break;
                                    default:
                                        $this->onProxyMsg->call($this, $msg, $from_process_name, $from_process_worker_id);
                                        break;
                                }
                            }else {
                                $this->onPipeMsg->call($this, $msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
                            }
                        }catch(\Throwable $t) {

                        }
                    }
                });
            }else {
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' param $process must instance of AbstractProcess');
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
                                    switch ($msg) {
                                        case ProcessManager::AUTO_CREATE_WORKER :
                                            $this->onDynamicCreateProcess->call($this, $msg, $from_process_name, $from_process_worker_id);
                                            break;
                                        case ProcessManager::DESTROY_DYNAMIC_PROCESS:
                                            $this->onDestroyDynamicProcess->call($this, $msg, $from_process_name, $from_process_worker_id);
                                            break;
                                        default:
                                            $this->onProxyMsg->call($this, $msg, $from_process_name, $from_process_worker_id);
                                            break;
                                    }
                                }else {
                                    $this->onProxyMsg->call($this, $msg, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id);
                                }
                            }catch(\Throwable $t) {

                            }
                        }
                    });
                }
            }
        }
    }

    /**
     * dynamicCreateProcess 动态创建临时进程
     */
    public function dynamicCreateProcess(string $process_name, int $process_num = 2) {
        $key = md5($process_name);
        $process_name = $this->process_lists[$key]['process_name'];
        $process_class = $this->process_lists[$key]['process_class'];
        $process_worker_num = $this->process_lists[$key]['process_worker_num'];
        $total_process_num = $process_worker_num + $process_num;
        $async = $this->process_lists[$key]['async'];
        $args = $this->process_lists[$key]['args'];
        $extend_data = $this->process_lists[$key]['extend_data'];
        $enable_coroutine = $this->process_lists[$key]['enable_coroutine'];

        if(count($this->process_wokers[$key]) > $process_worker_num) {
            return;
        }
        for($worker_id = $process_worker_num; $worker_id <  $total_process_num; $worker_id++) {
            try {
                $process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);
                $process->setProcessWorkerId($worker_id);
                $process->setProcessType(2);// 动态进程类型=2
                if (!isset($this->process_wokers[$key][$worker_id])) {
                    $this->process_wokers[$key][$worker_id] = $process;
                }
                $process->start();
            }catch(\Throwable $t) {
                throw new \Exception($t->getMessage());
            }
            $this->swooleEventAdd($process);
            usleep(50000);
        }
    }

    /**
     * destroyDynamicProcess 销毁动态创建的进程
     */
    public function destroyDynamicProcess(string $process_name) {
        $process_workers = $this->getProcessByName($process_name, -1);
        $dynamicProcess = [];
        foreach($process_workers as $worker_id=>$process) {
            if($process->isDynamicProcess()) {
                //$this->writeByProcessName($process_name, AbstractProcess::SWOOLEFY_PROCESS_EXIT_FLAG, $worker_id);
                $process->exit();
            }
        }
    }

    /**
     * daemon
     */
    public function daemon() {
        if(!isset($this->start_daemon)) {
            \Swoole\Process::daemon();
            $this->start_daemon = true;
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
     * @param string $process_name
     * @param string $data
     * @param int $process_worker_id
     * @return bool
     */
    public function writeByProcessName(string $process_name, string $data, int $process_worker_id = 0) {
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
     * @param string $data
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param string $to_process_name
     * @param int $to_process_worker_id
     * @return bool
     */
    public function writeByMasterProxy(string $data, string $from_process_name, int $from_process_worker_id, string $to_process_name, int $to_process_worker_id) {
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
     * @param string $data
     * @param string|null $process_name
     */
    public function broadcastProcessWorker(string $process_name = null, string $data = '') {
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
            throw new \Exception(__CLASS__.'::'.__FUNCTION__." second param process_name is empty");
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
     * @throws \Exception
     */
    private function registerSignal() {
        if(!empty($this->signal)) {
            foreach($this->signal as $signal_info) {
                list($signal, $function) = $signal_info;
                try {
                    \Swoole\Process::signal($signal, $function);
                }catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        }
    }

}
