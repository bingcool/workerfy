<?php
namespace Workerfy\Tests\Exec\Process;

use Symfony\Component\Process\Process;

class SymfonyProcess extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        var_dump("proc symfony process start");

        try {

            $execFile = 'php '.__DIR__.'/../TestCommand.php';

            $process = new Process($execFile);
            //$process->disableOutput();
            $process->start();

            $pid = $process->getPid();
            var_dump('pid='.$pid);

            $time = date('Y-m-d H:i:s');
            var_dump('time='.$time);
            echo $process->getOutput();

            var_dump('kkkkkkkkkkkk');

        }catch (\Exception $e)
        {
            //var_dump($e->getMessage());
            $this->onHandleException($e);
        }

    }


    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
    }
}