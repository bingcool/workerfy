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

use ArrayObject;
use Exception;

class Context {
    /**
     * @throws Exception
     * @return ArrayObject|null
     */
    public static function getContext() {
        if(\Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return $context;
        }else {
            throw new \Exception("Not in Coroutine Envirment");
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public static function set($name, $value) {
        $context = self::getContext();
        if($context) {
            $context[$name] = $value;
            return true;
        }
        return false;
    }

    /**
     * @param $name
     * @return bool
     */
    public static function get($name) {
        $context = self::getContext();
        if($context) {
            return $context[$name];
        }
        return null;
    }

    /**
     * @param $name
     * @return bool
     */
    public static function has($name) {
        $context = self::getContext();
        if($context) {
            if(isset($context[$name])) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * @return mixed
     */
    public static function getCid() {
        return \Swoole\Coroutine::getCid();
    }

    /**
     * @param callable $func
     */
    public static function defer(callable $func) {
        \Swoole\Coroutine::defer($func);
    }
}