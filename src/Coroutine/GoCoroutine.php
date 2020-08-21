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
use Workerfy\Log\LogManager;

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
    public static function go(callable $callback, ...$params) {
        Coroutine::create(function(...$params) use($callback){
            try{
                $args = func_get_args();
                call_user_func($callback, ...$args);
            }catch(\Throwable $throwable) {
                $logger = LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
                $logger->error(sprintf("%s on File %s on Line %d", $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
            }
        }, ...$params);
    }

    /**
     * @param callable $callback
     */
    public static function create(callable $callback,...$params) {
        $args = func_get_args();
        self::go($callback, ...$args);
    }

}