<?php
namespace Workerfy\Tests\Daemon;

use Workerfy\ProcessManager;

class Monitor extends \Workerfy\AbstractProcess {

    public function run() {
        sleep(5);
        $this->notifyMasterDynamicCreateProcess();

        sleep(10);

        $this->notifyMasterDestroyDynamicProcess();

        $this->exit();
    }
}