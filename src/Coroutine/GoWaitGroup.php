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

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Workerfy\AbstractProcess;

class GoWaitGroup
{
    /**
     * @var int
     */
    private $count = 0;

    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var array
     */
    private $result = [];

    /**
     * WaitGroup constructor
     */
    public function __construct()
    {
        $this->channel = new Channel;
    }

    /**
     * go function
     */
    public function go(\Closure $callBack, ...$params)
    {
        $exception = '';
        Coroutine::create(function (...$params) use ($callBack, &$exception) {
            try {
                $this->count++;
                call_user_func($callBack, ...$params);
            } catch (\Throwable $throwable) {
                $this->count--;
                $processInstance = AbstractProcess::getProcessInstance();
                if ($processInstance instanceof AbstractProcess) {
                    AbstractProcess::getProcessInstance()->onHandleException($throwable);
                } else {
                    $exception = $throwable;
                }
            }
        }, ...$params);

        if ($exception instanceof \Throwable) {
            throw $exception;
        }
    }

    /**
     * 可以通过 use 关键字传入外部变量
     *  $country = 'China';
     *   $callBack1 = function() use($country) {
     *      sleep(3);
     *      return [
     *          'tengxun'=> 'tengxun'
     *      ];
     *      };
     *
     *   $callBack2 = function() {
     *      sleep(3);
     *      return [
     *           'baidu'=> 'baidu'
     *      ];
     *   };
     *
     *   $callBack3 = function() {
     *      sleep(1);
     *      return [
     *          'ali'=> 'ali'
     *      ];
     *   };
     *
     *   call callable
     *   $result = GoWaitGroup::multiCall([
     *      'key1' => $callBack1,
     *      'key2' => $callBack2,
     *      'key3' => $callBack3
     *   ]);
     *
     *   var_dump($result);
     *
     * @param array $callBacks
     * @param float $timeOut
     * @return array
     */
    public static function multiCall(array $callBacks, float $timeOut = 3.0)
    {
        $goWait = new static();
        foreach ($callBacks as $key => $callBack) {
            Coroutine::create(function () use ($key, $callBack, $goWait) {
                try {
                    $goWait->count++;
                    $goWait->initResult($key, null);
                    $result = call_user_func($callBack);
                    $goWait->done($key, $result, 3.0);
                } catch (\Throwable $throwable) {
                    $goWait->count--;
                    $processInstance = AbstractProcess::getProcessInstance();
                    if ($processInstance instanceof AbstractProcess) {
                        AbstractProcess::getProcessInstance()->onHandleException($throwable);
                    }
                }
            });
        }
        $result = $goWait->wait($timeOut);
        return $result;
    }

    /**
     * start
     * @return int
     */
    public function start()
    {
        $this->count++;
        return $this->count;
    }

    /**
     * done
     * @return void
     */
    public function done(string $key = null, $data = null, float $timeout = -1)
    {
        if (!empty($key) && !empty($data)) {
            $this->result[$key] = $data;
        }
        $this->channel->push(1, $timeout);
    }

    /**
     * @param string $key
     * @param null $data
     * @return void
     */
    public function initResult(string $key, $data = null)
    {
        $this->result[$key] = $data;
    }

    /**
     * wait
     * @return array
     */
    public function wait(float $timeout = 0)
    {
        while ($this->count-- > 0) {
            $this->channel->pop($timeout);
        }
        $result = $this->result;
        $this->reset();
        return $result;
    }

    /**
     * reset
     * @return void
     */
    public function reset()
    {
        $this->result = [];
        $this->count = 0;
    }

}