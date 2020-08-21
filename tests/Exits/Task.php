<?php
namespace Workerfy\Tests\Exits;

class Task {

    public static function test() {
        var_dump('test');
        throw new \Exception("task task task tasktask");
    }
}