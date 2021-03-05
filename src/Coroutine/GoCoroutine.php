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
     * @throws Throwable
     */
    public static function go(callable $callback, ...$params) {
        $exception = '';
        Coroutine::create(function(...$params) use($callback, &$exception){
            try {
                call_user_func($callback, ...$params);
            }catch(\Throwable $throwable) {
                $logger = LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
                $logger->error(sprintf("%s on File %s on Line %d on trace %s", $throwable->getMessage(), $throwable->getFile(), $throwable->getLine(), $throwable->getTraceAsString()));
                $exception = $throwable;
            }
        }, ...$params);

        if($exception instanceof \Throwable)
        {
            throw $exception;
        }
    }

    /**
     * @param callable $callback
     */
    public static function create(callable $callback, ...$params) {
        self::go($callback, ...$params);
    }

}