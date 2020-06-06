<?php
namespace Workerfy\Tests\WhileTest;

class Worker extends \Workerfy\AbstractProcess {


    public $callNum = 0;

    public $startTime;
    public $runTime = 20;


    public function init() {
        var_dump('ggggggggggggggggggggggg');
        $this->startTime = time();
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
                    $db = \Workerfy\Tests\Db::getMasterMysql();
                    $query = $db->query("select sleep(1)");
                    $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
                    var_dump($res);
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