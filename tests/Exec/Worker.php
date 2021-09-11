<?php
namespace Workerfy\Tests\Exec;

use Workerfy\Command\CommandRunner;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        var_dump("process start");
        //sleep(2);
        $result = \Swoole\Coroutine\System::getaddrinfo('www.baidu.com');

        var_dump($result);

        //
//        go(function () {
//            //list($command, $output, $return) = CommandRunner::exec("/bin/echo",['hello']);
//            //var_dump($command);
//
//            $return = CommandRunner::procOpen(function ($pipe0, $pipe1, $pipe2) {
//                fwrite($pipe0, 'bingcool');
//                //var_dump(fread($pipe1, 8192), fread($pipe2, 8192));
//                return 'zhongguo';
//            } ,"php --ri swoole");
//
//            var_dump($return);
//
//        });
//
//        var_dump($this->getProcessWorkerId());

        //拉起一个进程阻塞执行
        var_dump("start exec");
        $execBinFile = 'php';
        var_dump('pid='.$this->getPid());

        $isContinue = true;
        while (1)
        {
            $runner = CommandRunner::getInstance('test1');
            // $runner can do next item
            if($runner->isNextHandle())
            {
                // 可以处理下一个的时候才从mq里面取出数据来消费，否则不要取数据
                // todo
                $params = ['name-'.rand(1,1000)];

                list($command, $output, $return) = $runner->exec(
                    $execBinFile,
                    __DIR__.'/TestCommand.php',
                    $params,
                    true
                );

                if(isset($output[0]))
                {
                    var_dump("exec end， pid={$output[0]}");
                }
            }

        }

        //var_dump("exec end");

        //Service::test();

    }
}