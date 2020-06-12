<?php
/**
+----------------------------------------------------------------------
| swoolefy framework bases on swoole extension development, we can use it easily!
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
*/

namespace Workerfy\Crontab;

use Cron\CronExpression;

use \Exception;

class CrontabManager {

    use \Workerfy\Traits\SingletonTrait;

    const loopChannelType = 0;
    const loopTickType = 1;

	private $cron_tasks = [];

	private $cron_next_datetime = [];

	private $expression = [];

	private $offset_second = 1;

	private $timer_ids = [];

	private $channels = [];

    /**
     * CrontabManager constructor.
     * @throws \Exception
     */
	protected function __construct() {
	    if(function_exists('inChildrenProcessEnv')) {
	        if(inChildrenProcessEnv() === false) {
	            throw new \Exception(__CLASS__." only use in children worker process");
            }
        }
    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param mixed  $func
     * @param int    $type
     * @param int    $msec
     * @throws Exception
     * @return mixed
     */
	public function addRule(
	    string $cron_name,
        string $expression,
        callable $func,
        int $loop_type = 1,
        int $msec = 1 * 1000) {

	    if(!class_exists('Cron\\CronExpression')) {
	        throw new \Exception("If you want to use crontab, you need to install 'composer require dragonmantank/cron-expression' ");
        }

	    if(!CronExpression::isValidExpression($expression)) {
            throw new \Exception("Crontab expression format is wrong, please check it");
        }

	    if(!is_callable($func)) {
            throw new \Exception("Params func must be callable");
        }

	    if(!in_array($loop_type, [self::loopChannelType, self::loopTickType])) {
            throw new \Exception("Params of type error");
        }

        if(!isset($this->cron_tasks[$cron_name])) {
            $this->cron_tasks[$cron_name] = [$expression, $func, $loop_type];
        }else {
            throw new \Exception("cron_name=$cron_name has been seted, you can not set again!");
        }

        if($loop_type == self::loopChannelType) {
            $channel = $this->channelLoop($cron_name, $expression, $func, $msec);
            return $channel;
        }else {
            $timer_id = $this->tickLoop($cron_name, $expression, $func, $msec);
            return $timer_id;
        }

    }

    /**
     * @param $cron_name
     * @param $expression
     * @param $func
     * @param $msec
     * @return mixed
     */
	protected function tickLoop($cron_name, $expression, $func, $msec) {
        if(is_array($func)) {
            $timer_id = \Swoole\Timer::tick($msec, $func, $expression);
        }else {
            $timer_id = \Swoole\Timer::tick($msec, function ($timer_id, $expression, $cron_name) use ($func) {
                $this->loopHandle($expression, $func, $cron_name);
            }, $expression, $cron_name);
        }
        $this->timer_ids[$cron_name] = $timer_id;

        return $timer_id;
    }

    /**
     * @param $cron_name
     * @param $expression
     * @param $func
     * @param $msec
     * @return \Swoole\Coroutine\Channel
     */
    protected function channelLoop($cron_name, $expression, $func, $msec) {
        $channel = new \Swoole\Coroutine\Channel(1);
        $second = round($msec / 1000, 3);
        if($second < 0.001) {
            $second = 0.001;
        }
        $this->channels[$cron_name] = $channel;
        \Swoole\Coroutine::create(function() use($cron_name, $channel, $expression, $func, $second) {
            while(!$channel->pop($second)) {
                $this->loopHandle($expression, $func, $cron_name);
            }
        });

        return $channel;
    }

    /**
     * @param $expression
     * @param $func
     * @param $cron_name
     * @throws \Throwable
     */
    protected function loopHandle($expression, $func, $cron_name) {
        $expression_key = md5($expression);
        $cron = CronExpression::factory($expression);
        $now_time = time();
        $cron_next_datetime = strtotime($cron->getNextRunDate()->format('Y-m-d H:i:s'));
        if($cron->isDue()) {
            if(!isset($this->cron_next_datetime[$expression_key])) {
                $this->expression[$expression_key] = $expression;
                $this->cron_next_datetime[$expression_key] = $cron_next_datetime;
            }

            if(($now_time >= $this->cron_next_datetime[$expression_key] && $now_time < ($cron_next_datetime - $this->offset_second))) {
                $this->cron_next_datetime[$expression_key] = $cron_next_datetime;
                if ($func instanceof \Closure) {
                    try {
                        call_user_func($func, $cron_name, $cron);
                    }catch (\Throwable $throwable) {
                        throw $throwable;
                    }
                }
            }

            // 防止万一出现的异常出现，比如没有命中任务， 19:05:00要命中的，由于其他网络或者服务器其他原因，阻塞了,造成延迟，现在时间已经到了19::05:05
            if ($now_time > $this->cron_next_datetime[$expression_key] || $now_time >= $cron_next_datetime) {
                $this->cron_next_datetime[$expression_key] = $cron_next_datetime;
            }
        }
    }

    /**
     * @param string|null $cron_name
     * @return array|mixed|null
     */
    public function getCronTaskByName(string $cron_name = null) {
        if($cron_name) {
            if(isset($this->cron_tasks[$cron_name])) {
                return $this->cron_tasks[$cron_name];
            }
            return null;
        }
        return $this->cron_tasks;
    }

    /**
     * @param string|null $cron_name
     * @return mixed|null
     */
    public function getTimerIdByName(string $cron_name = null) {
        if($cron_name) {
            if(isset($this->timer_ids[$cron_name])) {
                return $this->timer_ids[$cron_name];
            }
            return null;
        }
    }

    /**
     * @param $cron_name
     * @return mixed|null
     */
    public function getChannelByName($cron_name) {
        return $this->channels[$cron_name] ?? null;
    }

    /**
     * @param $cron_name
     */
    public function cancelCrontabTask($cron_name) {
        $loop_type = $this->getLoopType($cron_name) ?? null;
        if($loop_type == self::loopChannelType) {
            $channel = $this->channels[$cron_name];
            $channel->push(true);
        }else if($loop_type = self::loopTickType) {
            $tick_id = $this->timer_ids[$cron_name];
            \Swoole\Timer::clear($tick_id);
        }
    }

    /**
     * @param $cron_name
     * @return mixed
     */
    public function getLoopType($cron_name) {
        if($cron_name) {
            if(isset($this->cron_tasks[$cron_name])) {
                list($expression, $func, $loop_type) = $this->cron_tasks[$cron_name];
                return $loop_type;
            }
        }
    }

}