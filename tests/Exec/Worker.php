<?php
namespace Workerfy\Tests\Exec;

use Workerfy\CommandRunner;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        var_dump("process start");
        //sleep(2);
        $result = \Swoole\Coroutine\System::getaddrinfo('www.baidu.com');

        var_dump($result);

        //
        go(function () {
            //list($command, $output, $return) = CommandRunner::exec("/bin/echo",['hello']);
            //var_dump($command);

            $return = CommandRunner::procOpen(function ($pipe0, $pipe1, $pipe2) {
                fwrite($pipe0, 'bingcool');
                var_dump(fread($pipe1, 8192), fread($pipe2, 8192));
                return 'zhongguo';
            } ,"php --ri swoole");

            var_dump($return);

        });

        var_dump($this->getProcessWorkerId());
        //$this->getProcess()->exec('/bin/echo', ['hello']);

        //var_dump("exec ");

        //Service::test();

    }
}