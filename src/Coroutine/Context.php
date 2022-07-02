<?php
/**
 * +----------------------------------------------------------------------
 * | Daemon and Cli model about php process worker
 * +----------------------------------------------------------------------
 * | Licensed ( https://opensource.org/licenses/MIT )
 * +----------------------------------------------------------------------
 * | Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
 * +----------------------------------------------------------------------
 */

namespace Workerfy\Coroutine;

use ArrayObject;
use Workerfy\Exception\RuntimeException;

class Context
{
    /**
     * @return ArrayObject
     * @throws RuntimeException
     */
    public static function getContext()
    {
        if (\Swoole\Coroutine::getCid() > 0) {
            $context = \Swoole\Coroutine::getContext();
            return $context;
        } else {
            throw new RuntimeException("Not in Coroutine Environment");
        }
    }

    /**
     * @param $name
     * @param $value
     * @return bool
     * @throws RuntimeException
     */
    public static function set($name, $value)
    {
        $context = self::getContext();
        if ($context) {
            $context[$name] = $value;
            return true;
        }
        return false;
    }

    /**
     * @param $name
     * @return bool|null
     * @throws RuntimeException
     */
    public static function get($name)
    {
        $context = self::getContext();
        if ($context) {
            return $context[$name];
        }
        return null;
    }

    /**
     * @param $name
     * @return bool
     * @throws RuntimeException
     */
    public static function has($name)
    {
        $context = self::getContext();
        if ($context) {
            if (isset($context[$name])) {
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * @return int
     */
    public static function getCid()
    {
        return \Swoole\Coroutine::getCid();
    }

    /**
     * @param callable $func
     * @return void
     */
    public static function defer(callable $func)
    {
        \Swoole\Coroutine::defer($func);
    }
}