<?php
namespace Workerfy\Tests\GoWaitGroup;

class Worker extends \Workerfy\AbstractProcess {

    public function init() {
        defer(function() {
            //var_dump("coroutine_destruct");
        });
    }

    public function run() {
        // 阻塞串行执行
        if($this->getProcessWorkerId() == 0) {
            $this->blockRun();
        }

        // GoWaitGroup并发处理
        if($this->getProcessWorkerId() == 1) {
            $this->waitGroup();
        }

        // parallel并发
        if($this->getProcessWorkerId() == 2) {
            $this->parallel();
        }

        // coroutine Map并发
        if($this->getProcessWorkerId() == 3) {
            $this->swoole_map();
        }

    }

    public function waitGroup() {
        // 模拟处理业务
        sleep(1);

        \Workerfy\Coroutine\GoCoroutine::create(function ($name) {
            var_dump($name);
        }, 'bingcool');

        // GoWaitGroup实例化
        $waitGroup = new \Workerfy\Coroutine\GoWaitGroup();

        $start_time = microtime(true);

        $waitGroup->go(function($name) use($waitGroup) {
            $cli = new \Swoole\Coroutine\Http\Client('www.baidu.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                'Host' => "www.baidu.com",
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);


            $ret = $cli->get('/');

            $waitGroup->done('www.baidu.com', 'g1-test-wait-group');
        }, 'nnnnnn');

        $waitGroup->go(function () use ($waitGroup){
            $cli = new \Swoole\Coroutine\Http\Client('www.163.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            $waitGroup->done('www.163.com', 'g2-test-wait-group');
        });

        $result = $waitGroup->wait();

        var_dump($result);

        $end_time = microtime(true);

        $last_time = $end_time - $start_time;
        var_dump("waitGroup并发请求时长:". $last_time);
    }

    public function blockRun() {
        // 模拟处理业务
        sleep(1);

        $start_time = microtime(true);

        $result = [];

        // 当前是主协程，遇到IO,让出CPU控制权，在主协程是阻塞串行执行的
        $cli = new \Swoole\Coroutine\Http\Client('www.baidu.com', 80);
        $cli->set(['timeout' => 10]);
        $cli->setHeaders([
            'Host' => "www.baidu.com",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $ret = $cli->get('/');

        $result['www.baidu.com']= 'g1-test-block-run';

        $cli = new \Swoole\Coroutine\Http\Client('www.163.com', 80);
        $cli->set(['timeout' => 10]);
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
        var_dump("blockRun阻塞串行请求时长:". $last_time);
    }

    public function parallel() {

        sleep(1);

        $parallel = new \Workerfy\Coroutine\Parallel(5);
        $parallel->add(function () {
            $cli = new \Swoole\Coroutine\Http\Client('www.baidu.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                'Host'            => "www.baidu.com",
                "User-Agent"      => 'Chrome/49.0.2587.3',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            return 'parallel-1';
        },'key1');

        $parallel->add(function () {
            $cli = new \Swoole\Coroutine\Http\Client('www.163.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                "User-Agent" => 'Chrome/49.0.2587.3',
                'Accept' => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            return 'parallel-2';
        },'key2');

        $parallel->add(function () {
            $cli = new \Swoole\Coroutine\Http\Client('www.baidu.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                'Host'            => "www.baidu.com",
                "User-Agent"      => 'Chrome/49.0.2587.3',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            return 'parallel-3';
        },'key3');

        $parallel->add(function () {
            $cli = new \Swoole\Coroutine\Http\Client('www.baidu.com', 80);
            $cli->set(['timeout' => 10]);
            $cli->setHeaders([
                'Host'            => "www.baidu.com",
                "User-Agent"      => 'Chrome/49.0.2587.3',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml',
                'Accept-Encoding' => 'gzip',
            ]);
            $ret = $cli->get('/');
            return 'parallel-4';
        },'key4');

        //$parallel->ignoreCallbacks(['key3']);

        $start_time = microtime(true);

        $result = $parallel->wait(5);

        $end_time = microtime(true);

        $time = $end_time - $start_time;

        var_dump('parallel并发请求时长:'.$time);

        var_dump($result);

    }

    protected function swoole_map()
    {
        sleep(1);
        $startTime = microtime(true);

        $result = [];
        \Swoole\Coroutine\map([1,2], function ($n) use(&$result) {
            var_dump($n);
           switch ($n) {
               case 1:
                   $cli = new \Swoole\Coroutine\Http\Client('www.baidu.com', 80);
                   $cli->set(['timeout' => 10]);
                   $cli->setHeaders([
                       'Host'            => "www.baidu.com",
                       "User-Agent"      => 'Chrome/49.0.2587.3',
                       'Accept'          => 'text/html,application/xhtml+xml,application/xml',
                       'Accept-Encoding' => 'gzip',
                   ]);
                   $ret = $cli->get('/');
                   $result['map1'] = 'www.baidu.com';
               break;

               case 2:
                   $cli = new \Swoole\Coroutine\Http\Client('www.163.com', 80);
                   $cli->set(['timeout' => 10]);
                   $cli->setHeaders([
                       "User-Agent" => 'Chrome/49.0.2587.3',
                       'Accept' => 'text/html,application/xhtml+xml,application/xml',
                       'Accept-Encoding' => 'gzip',
                   ]);
                   $ret = $cli->get('/');
                   $result['map2'] = 'www.163.com';
               break;
           }
        }, 5);

        $endTime = microtime(true);
        $time = $endTime - $startTime;

        var_dump('swooleMap并发请求时长:'.$time);
        var_dump($result);

    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
        parent::onHandleException($throwable);

    }
}