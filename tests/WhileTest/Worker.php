<?php
namespace Workerfy\Tests\WhileTest;

class Worker extends \Workerfy\AbstractProcess {


    public $callNum = 0;

    public $startTime;
    public $runTime = 20;


    public function init() {
        var_dump('ggggggggggggggggggggggg');
        $this->startTime = time();
        register_shutdown_function(function () {
            var_dump('shutdown shutdown shutdown');
        });
    }


    public function run() {
        while (1) {
            // 在while中捕捉异常一定要try catch 不能抛出异常，否则就会停止继续循环，直接处理$this->onHandleException($exception);
            try {
                if($this->isRebooting() == true) {
                    return ;
                }

                if(time() - $this->startTime > $this->runTime) {
                    $this->reboot(5);
                }
                // 模拟处理业务
                \Co::sleep(1);
                $process_name = $this->getProcessName().'@'.$this->getProcessWorkerId();
                var_dump($process_name);
                try {
                    var_dump('vvvvvv');
                }catch (\Exception $exception) {
                    throw $exception;
                }
            }catch (\Throwable $throwable) {
                // 业务信息记录(关键信息)

                // 异常记录
                $this->onHandleException($throwable);
            }

            usleep(20000);
        }

    }

}