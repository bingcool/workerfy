<?php
namespace Workerfy\Tests\Exec\ExecWorker;

use Workerfy\Command\CommandRunner;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        //拉起一个进程执行
        var_dump("exec process start");
        $execBinFile = 'php';

        while (1)
        {
            $runner = CommandRunner::getInstance('exec-test',3);
            try {
                if($runner->isNextHandle())
                {
                    var_dump('exec next');
                    // $runner can do next item
                    // 可以处理下一个的时候才从mq里面取出数据来消费，否则不要取数据
                    // todo

                    $params = [
                        '--type=exec',
                        '--name=bingcool-'.rand(1,1000)
                    ];
                    // 调用命令程序
                    list($command, $output, $return) = $runner->exec(
                        $execBinFile,
                        __DIR__ . '/../TestCommand.php',
                        $params,
                        true
                    );

                    // exec调用失败,需要重试机制延后处理$params
                    if($return !=0)
                    {
                        var_dump('exec failed');
                    }

                    if(isset($output[0]))
                    {
                        var_dump("exec end， pid={$output[0]}");
                    }
                }
            }catch (\Exception $e)
            {
                var_dump($e->getMessage());
            }

        }


    }


    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
    }
}