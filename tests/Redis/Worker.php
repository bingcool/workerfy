<?php
namespace Workerfy\Tests\Redis;

class Worker extends \Workerfy\AbstractProcess {

    public function init() {
        defer(function() {
            var_dump("coroutine_destruct");
        });
    }

    public function run() {
        // 模拟处理业务
        sleep(1);
        $redis = \Workerfy\Tests\Redis::getMasterRedis();
        $redis->set("name", "bingcool-".rand(1,1000));
        $value = $redis->get('name');
        var_dump($value);
    }
}