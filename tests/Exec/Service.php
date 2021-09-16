<?php
namespace Workerfy\Tests\Exec;

use Workerfy\Tests\Exec\ExecWorker\Worker;

class Service {
    public static function test() {
        Worker::getProcessInstance()->reboot();
    }
}