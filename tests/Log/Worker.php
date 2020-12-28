<?php
namespace Workerfy\tests\Log;

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

        \Workerfy\Coroutine\GoCoroutine::go(function ($name, $sex) {
            var_dump($name, $sex);

        }, $name ='bingcool', $sex=1);

        throw new \RuntimeException("错误");

    }

}