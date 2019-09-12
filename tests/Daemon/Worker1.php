<?php
namespace Workerfy\Tests\Daemon;

use Workerfy\ProcessManager;

class Worker1 extends \Workerfy\AbstractProcess {

	public function run() {
	    $start_time = time();
		while(true) {
			// var_dump(date("Y-m-d H:i:s"));
			// go(function() {
			// 	sleep(3);
			// });
			// var_dump(date("Y-m-d H:i:s"));
		    //var_dump("jjjjj");
            $pid = ProcessManager::getInstance()->getPidByName($this->getProcessName(), $this->getProcessWorkerId());
		    \Co::sleep(2);
            //var_dump(date("Y-m-d H:i:s"));
		    if(time() -$start_time > 1) {
                break;
            }
            //var_dump("run start-".rand(1,1000),'cid-'.\Co::getCid());
        }

        //$this->writeByProcessName(ProcessManager::getInstance()->getMasterWorkerName(), 'hello hhhhhhhh');
        if($this->getProcessWorkerId() == 1) {
            $this->writeByProcessName('worker', 'hello hhhhhhhh', 0,false);
        }

        //$this->exit();
        //$this->reboot();
	}

	public function onShutDown() {
        //parent::onShutDown(); // TODO: Change the autogenerated stub
        var_dump("process shutdown-".$this->getProcessName().$this->getProcessWorkerId().'---cid:'.\Co::getCid());
        //var_dump("children-process shutdown,pid={$this->getPid()}, peocess_name={$this->getProcessName()}.'@'.{$this->getProcessWorkerId()}");
    }

    public function onPipeMsg(string $msg, string $from_process_name, int $from_process_worker_id) {
	    var_dump('worker_id-'.$this->getProcessWorkerId().'-from-worker_id:'.$from_process_worker_id);
    }

}