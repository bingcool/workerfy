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
        $timeChannel = new Channel(1);
        $second  = round($timeMs / 1000, 3);
        if ($second < 0.001) {
            $second = 0.001;
        }
        GoCoroutine::go(function ($second, $callable) use ($timeChannel) {
            while (true) {
                $value = $timeChannel->pop($second);
                if($value !== false) {
                    $timeChannel->close();
                    break;
                }
                try {
                    GoCoroutine::go(function ($callable) use($timeChannel) {
                        $callable($timeChannel);
                    }, $callable);
                }catch (\Throwable $exception)
                {

                }
            }
        }, $second, $callable);

        return $timeChannel;
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
        $timeChannel = new Channel(1);
        $second  = round($timeMs / 1000, 3);
        if ($second < 0.001) {
            $second = 0.001;
        }
        GoCoroutine::go(function ($second, $callable) use ($timeChannel) {
            while (!$timeChannel->pop($second)) {
                try {
                    GoCoroutine::go(function ($callable) use($timeChannel) {
                        $callable($timeChannel);
                    }, $callable);
                }catch (\Throwable $exception) {
                    throw $exception;
                } finally {

                }
                break;
            }
        }, $second, $callable);

        return $timeChannel;
    }

}