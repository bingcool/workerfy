<?php
namespace Workerfy\Tests\Exec;

class Service {
    public static function test() {
        Worker::processInstance()->reboot();
    }
}