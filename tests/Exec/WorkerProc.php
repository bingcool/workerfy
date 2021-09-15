<?php
namespace Workerfy\Tests\Exec;

use Workerfy\Command\CommandRunner;

class WorkerProc extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        var_dump("proc process start");

        while (true)
        {
            // 设置$concurrent =1 就相当于阻塞模式了，轮训一个一个消费
            $runner = CommandRunner::getInstance('procOpen-test',5);
            try{
                if($runner->isNextHandle())
                {
                    var_dump('procOpen next');
                    // $runner can do next item
                    // 可以处理下一个的时候才从mq里面取出数据来消费，否则不要取数据
                    // todo

                    $execFile = 'php '.__DIR__.'/TestCommand.php';
                    $params = [
                        '--type=proc',
                        '--name=bingcool-'.rand(1,1000)
                    ];
                    // 调用命令程序
                    $runner->procOpen(function ($pipe0, $pipe1, $pipe2, $status) {
                        var_dump(fread($pipe1, 8192));
                        //var_dump($status);
                    } , $execFile, $params);
                }
            }catch (\Exception $e)
            {
                $this->onHandleException($e);
            }
            var_dump('proc end');
        }

    }


    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
    }
}