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
use Workerfy\Coroutine\GoCoroutine;

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
        int $loop_type = self::loopChannelType,
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
            throw new \Exception("Cron_name=$cron_name had exist, you can not set same again");
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
     * @param string $cron_name
     * @param string $expression
     * @param callable $func
     * @param float $msec
     * @return mixed
     */
	protected function tickLoop(string $cron_name, string $expression, callable $func, float $msec) {
        $channel = new \Swoole\Coroutine\Channel(1);
        GoCoroutine::go(function ($cron_name, $expression, $func, $msec) use($channel) {
            if(is_array($func)) {
                $timer_id = \Swoole\Timer::tick($msec, $func, $expression);
            }else {
                $timer_id = \Swoole\Timer::tick($msec, function ($timer_id, $expression, $cron_name) use ($func) {
                    $this->loopHandle($cron_name, $expression, $func);
                }, $expression, $cron_name);
            }
            $channel->push($timer_id);
        }, $cron_name, $expression, $func, $msec);

        $timer_id = $channel->pop();
        $this->timer_ids[$cron_name] = $timer_id;
        $channel->close();
        unset($channel);
        return $timer_id;
    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param callable $func
     * @param float $msec
     * @return \Swoole\Coroutine\Channel
     */
    protected function channelLoop(string $cron_name, string $expression, callable $func, float $msec) {
        $channel = new \Swoole\Coroutine\Channel(1);
        $second = round($msec / 1000, 3);
        if($second < 0.001) {
            $second = 0.001;
        }
        $this->channels[$cron_name] = $channel;
        GoCoroutine::go(function($cron_name, $expression, $func, $second) use($channel) {
            while(!$channel->pop($second)) {
                GoCoroutine::go(function($cron_name, $expression, $func) {
                    $this->loopHandle($cron_name, $expression, $func);
                }, $cron_name, $expression, $func);
            }
        }, $cron_name, $expression, $func, $second);

        return $channel;
    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param callable $func
     * @throws \Throwable
     */
    protected function loopHandle(string $cron_name, string $expression, callable $func) {
        $expression_key = md5($expression);
        $cron = CronExpression::factory($expression);
        $now_time = time();
        $cron_next_datetime = strtotime($cron->getNextRunDate()->format('Y-m-d H:i:s'));
        if($cron->isDue()) {
            if(!isset($this->cron_next_datetime[$cron_name][$expression_key])) {
                $this->expression[$cron_name][$expression_key] = $expression;
                $this->cron_next_datetime[$cron_name][$expression_key] = $cron_next_datetime;
            }

            if(($now_time >= $this->cron_next_datetime[$cron_name][$expression_key] && $now_time < ($cron_next_datetime - $this->offset_second))) {
                $this->cron_next_datetime[$cron_name][$expression_key] = $cron_next_datetime;
                if ($func instanceof \Closure) {
                    try {
                        call_user_func($func, $cron_name, $cron);
                    }catch (\Throwable $throwable) {
                        throw $throwable;
                    }
                }
            }

            // 防止万一出现的异常出现，比如没有命中任务， 19:05:00要命中的，由于其他网络或者服务器其他原因，阻塞了,造成延迟，现在时间已经到了19::05:05
            if($now_time > $this->cron_next_datetime[$cron_name][$expression_key] || $now_time >= $cron_next_datetime) {
                $this->cron_next_datetime[$cron_name][$expression_key] = $cron_next_datetime;
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
     * @param string $cron_name
     * @return mixed|null
     */
    public function getChannelByName(string $cron_name) {
        return $this->channels[$cron_name] ?? null;
    }

    /**
     * @param string $cron_name
     */
    public function cancelCrontabTask(string $cron_name) {
        $loop_type = $this->getLoopType($cron_name) ?? null;
        if($loop_type == self::loopChannelType) {
            $channel = $this->channels[$cron_name];
            $channel->push(true);
            $channel->close();
            unset($this->channels[$cron_name]);
        }else if($loop_type = self::loopTickType) {
            $tick_id = $this->timer_ids[$cron_name];
            \Swoole\Timer::clear($tick_id);
            unset($this->timer_ids[$cron_name]);
        }

        write_info("tickName={$cron_name} has been cancel");

        if(count($this->channels) == 0 && count($this->timer_ids) == 0) {
            write_info("Process had not exit tick task");
        }
    }

    /**
     * @param string $cron_name
     * @return mixed
     */
    public function getLoopType(string $cron_name) {
        if($cron_name) {
            if(isset($this->cron_tasks[$cron_name])) {
                list($expression, $func, $loop_type) = $this->cron_tasks[$cron_name];
                return $loop_type;
            }
        }
        return null;
    }

    /**
     * 是否存在正在执行任务
     * @return bool
     */
    public function hasRunningCrontabTask() {
        if(count($this->channels) > 0 || count($this->timer_ids) > 0) {
            return true;
        }
        return false;
    }

}