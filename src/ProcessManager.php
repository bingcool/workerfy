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

	private $process_lists = [];

	private $process_wokers = [];

	private $process_pid_map = [];

	use \Workerfy\Traits\SingletonTrait;

	public function __construct(...$args) {}

	public function addProcess(string $process_name, string $process_class, int $process_worker_num = 1, bool $async = true, array $args = [], $extend_data = null, bool $enable_coroutine = false) {   
        $key = md5($process_name);
        if(isset($this->process_lists[$key])) {
            throw new \Exception(__CLASS__." Error : you can not add the same process : $processName", 1);
        }

        $this->process_lists[$key] = [
        	'process_name' => $process_name,
        	'process_class' => $process_class,
        	'process_worker_num' =>$process_worker_num,
        	'async' => $async,
        	'args' => $args,
        	'extend_data' => $extend_data,
        	'enable_coroutine' => $enable_coroutine
        ];

        $this->singal();
    }

    /**
     * start
     * @return
     */
    public function start() {
    	if(!empty($this->process_lists)) {
    		foreach($this->process_lists as $key => $list) {
    			$process_worker_num = $list['process_worker_num'] ?? 1;
    			for($i = 1; $i <= $process_worker_num; $i++) {
    				try {
	    				$process_name = $list['process_name'];
			        	$process_class = $list['process_class'];
			        	$async = $list['async'] ?? true;
			        	$args = $list['args'] ?? [];
			        	$extend_data = $list['extend_data'] ?? null;
			        	$enable_coroutine = $list['enable_coroutine'] ?? false;

		    			$process = new $process_class($process_name, $async, $args, $extend_data, $enable_coroutine);

		    			$process->setProcessWorkerId($i);
		    			$process->start();

		    			!isset($this->process_wokers[$key]) && $this->process_wokers[$key] = $process;
	    			}catch(\Throwable $t) {
	    				throw new \Exception($t->getMessage());
	    			}
    			}
    		}
    	}
    }

    /**
     * singal
     * @return
     */
    private function singal() {
    	Process::signal(SIGCHLD, function($singal) {
  			//必须为false，非阻塞模式
		  	while($ret = Process::wait(false)) {
		      	echo "PID={$ret['pid']}\n";
		  	}
		});
    }

    /**
     * setProcess 设置一个进程
     * @param string          $process_name
     * @param AbstractProcess $process
     */
    public static function setProcess(string $process_name, \Workerfy\AbstractProcess $process) {
        $key = md5($process_name);
        $process_worker_num = $this->process_lists[$key]['process_worker_num'] ?? 0;
        $this->process_lists[$key] = [
        	'process_name' => $process->getProcessName(),
        	'process_class' => get_class($process),
        	'process_worker_num' => $process_worker_num + 1,
        	'async' => $process->isAsync(),
        	'args' => $process->getArgs(),
        	'extend_data' => $process->getExtendData(),
        	'enable_coroutine' => $process->isEnableCoroutine()
        ];
		!isset($this->process_wokers[$key]) && $this->process_wokers[$key] = $process;    
	}

    /**
	 * getProcessByName 通过名称获取一个进程
	 * @param  string $process_name
	 * @return mixed
	 */
	public static function getProcessByName(string $process_name) {
        $key = md5($process_name);
        if(isset($this->process_wokers[$key])){
            return $this->process_wokers[$key];
        }else{
            return null;
        }
    }

    /**
     * getProcessByPid 通过进程id获取进程
     * @param  int    $pid
     * @return mixed
     */
    public static function getProcessByPid(int $pid) {
    	$p = null;
       	foreach ($this->process_wokers as $key => $process) {
       		if($process->getPid() == $pid) {
       			$p = $process;
       			break;
       		}
       	}
       	return $p;
    }
}
