<?php
namespace Workerfy\Tests\Daemon;

class Worker1 extends \Workerfy\AbstractProcess {

	public function run($swooleProcess) {
		var_dump("woker".rand(1,1000));
	}

}