<?php

namespace Workerfy\Tests\MultiWorkerTraits;

use Workerfy\AbstractProcess;

class Worker0 {

    private $process;

    public function __construct(AbstractProcess $process) {
        $this->process = $process;
    }

    public function run() {
        var_dump($this->process->getProcessName());

    }

    //
    public function __call($method, $arguments)
    {
        if(method_exists($this->process, $method)) {
            return $this->process->{$method}($arguments);
        }
    }


}