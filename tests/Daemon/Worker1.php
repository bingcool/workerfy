<?php
namespace Workerfy\Tests\Daemon;

use Workerfy\ProcessManager;

class Worker1 extends \Workerfy\AbstractProcess {

	public function run() {
	    $start_time = time();
		while(true) {
		    //var_dump("jjjjj");
            $pid = ProcessManager::getInstance()->getPidByName($this->getProcessName(), $this->getProcessWorkerId());
		    \Co::sleep(2);
		    if(time() -$start_time > 6) {
		        break;
            }
            var_dump("run start-".rand(1,1000),'cid-'.\Co::getCid());
        }

        //$this->reboot();
	}

	public function onShutDown() {
        //parent::onShutDown(); // TODO: Change the autogenerated stub
        var_dump("process shutdown-".$this->getProcessName().$this->getProcessWorkerId().'---cid:'.\Co::getCid());
        //var_dump("children-process shutdown,pid={$this->getPid()}, peocess_name={$this->getProcessName()}.'@'.{$this->getProcessWorkerId()}");
    }

}