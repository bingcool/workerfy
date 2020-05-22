<?php
namespace Workerfy\Tests\Exec;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        var_dump("process start");
        //sleep(2);
        $result = \Swoole\Coroutine\System::getaddrinfo('www.baidu.com');

        var_dump($result);

        //
        go(function () {
            list($command, $output, $return) = $this->exec("/bin/echo",['hello']);
            var_dump($command);
        });

        var_dump($this->getProcessWorkerId());
        //$this->getProcess()->exec('/bin/echo', ['hello']);

        var_dump("exec ");

        Service::test();

    }
}