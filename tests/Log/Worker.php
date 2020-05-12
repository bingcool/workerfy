<?php
namespace Workerfy\tests\Log;

use http\Exception\RuntimeException;

class Worker extends \Workerfy\AbstractProcess {


    public $callNum = 0;

    public $startTime;
    public $runTime = 20;


    public function init() {
        var_dump('ggggggggggggggggggggggg');
        $this->startTime = time();
    }


    public function run() {

        defer(function () {
            $cid = \Co::getCid();
            var_dump($cid);
        });

        throw new \RuntimeException("错误");

    }

}