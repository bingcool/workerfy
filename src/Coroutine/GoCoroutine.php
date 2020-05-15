<?php
/**
+----------------------------------------------------------------------
| Daemon and Cli model about php process worker
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
 */

namespace Workerfy\Coroutine;

use Swoole\Coroutine;

class GoCoroutine {

    /**
     * @return mixed
     */
    public static function getCid() {
        return Coroutine::getCid();
    }

    /**
     * @param callable $callback
     */
    public static function go(callable $callback) {
        Coroutine::create(function() use($callback){
            try{
                $callback();
            }catch(\Throwable $throwable) {
                $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE);
                $logger->error(sprintf("%s on File %s on Line %d", $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
            }
        });
    }
}