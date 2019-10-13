<?php
namespace Workerfy\Tests\GoWaitGroup;

class Worker extends \Workerfy\AbstractProcess {

    public function init() {
        defer(function() {
            //var_dump("coroutine_destruct");
        });
    }

    public function run() {
        // GoWaitGroup并发处理
        if($this->getProcessWorkerId() == 0) {
            $this->waitGroup();
        }

        // 阻塞串行执行
        if($this->getProcessWorkerId() == 1) {
            $this->blockRun();
        }

    }

    public function waitGroup() {
        // 模拟处理业务
        sleep(1);

        // GoWaitGroup实例化
        $wait_group = new \Workerfy\Coroutine\GoWaitGroup();

        $start_time = microtime(true);

        $wait_group->go(function () use($wait_group) {
            $cli = new \Swoole\Coroutine\Http\Client('www.qq.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                'Host' => "www.qq.com",
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            $wait_group->done('www.qq.com', 'g1-test-wait-group');
        });

        $wait_group->go(function () use ($wait_group){
            $cli = new \Swoole\Coroutine\Http\Client('www.163.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                'Host' => "www.163.com",
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            $wait_group->done('www.163.com', 'g2-test-wait-group');
        });
        $result = $wait_group->wait();

        $end_time = microtime(true);

        $last_time = $end_time - $start_time;
        var_dump("wait-group-last-time:". $last_time);

        var_dump($result);
    }

    public function blockRun() {
        // 模拟处理业务
        sleep(1);

        $start_time = microtime(true);

        $result = [];

        // 当前是主协程，遇到IO,让出CPU控制权，在主协程是阻塞串行执行的
        $cli = new \Swoole\Coroutine\Http\Client('www.qq.com', 80);
        $cli->set(['timeout' => 1]);
        $cli->setHeaders([
            'Host' => "www.qq.com",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $ret = $cli->get('/');

        $result['www.qq.com']= 'g1-test-block-run';

        $cli = new \Swoole\Coroutine\Http\Client('www.163.com', 80);
        $cli->set(['timeout' => 1]);
        $cli->setHeaders([
            'Host' => "www.163.com",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $ret = $cli->get('/');

        $result['www.163.com']= 'g2-test-block-run';

        $end_time = microtime(true);

        $last_time = $end_time - $start_time;
        var_dump("block-last-time:". $last_time);

        var_dump($result);


    }
}