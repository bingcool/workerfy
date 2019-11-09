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

class Context {
    /**
     * @return ArrayObject|null
     * @throws \Exception
     */
    public static function getContext() {
        if(\Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return $context;
        }else {
            throw new \Exception("Not in Coroutine Envirement");
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
     * @throws \Exception
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
     * @throws \Exception
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
}