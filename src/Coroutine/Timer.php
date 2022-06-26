<?php
/**
 * +----------------------------------------------------------------------
 * | swoolefy framework bases on swoole extension development, we can use it easily!
 * +----------------------------------------------------------------------
 * | Licensed ( https://opensource.org/licenses/MIT )
 * +----------------------------------------------------------------------
 * | @see https://github.com/bingcool/swoolefy
 * +----------------------------------------------------------------------
 */

namespace Workerfy\Coroutine;

use Swoole\Coroutine\Channel;

class Timer
{
    /**
     * @param int $timeMs
     * @param callable $callable
     * @return Channel
     */
    public static function tick(int $timeMs, callable $callable)
    {
        $channel = new Channel(1);
        $second  = round($timeMs / 1000, 3);
        if ($second < 0.001) {
            $second = 0.001;
        }
        GoCoroutine::go(function ($second, $callable) use ($channel) {
            while (true) {
                $value = $channel->pop($second);
                if($value !== false) {
                    break;
                }
                GoCoroutine::go(function ($callable) {
                    $callable();
                }, $second, $callable);
            }
        }, $second, $callable);

        return $channel;
    }

    /**
     * cancel tick timer
     *
     * @param Channel $channel
     * @return mixed
     */
    public static function cancel(Channel $channel)
    {
        return $channel->push(1);
    }

    /**
     * @param int $timeMs
     * @param callable $callable
     * @return Channel
     */
    public static function after(int $timeMs, callable $callable)
    {
        $channel = new Channel(1);
        $second  = round($timeMs / 1000, 3);
        if ($second < 0.001) {
            $second = 0.001;
        }
        GoCoroutine::go(function ($second, $callable) use ($channel) {
            while (!$channel->pop($second)) {
                GoCoroutine::go(function ($callable) {
                    $callable();
                }, $second, $callable);
                break;
            }
        }, $second, $callable);

        return $channel;
    }

}