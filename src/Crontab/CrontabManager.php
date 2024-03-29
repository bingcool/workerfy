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

namespace Workerfy\Crontab;

use Cron\CronExpression;
use Workerfy\Coroutine\GoCoroutine;
use Workerfy\Exception\CrontabException;

class CrontabManager
{

    use \Workerfy\Traits\SingletonTrait;

    /**
     * @var int
     */
    const loopChannelType = 0;

    /**
     * @var int
     */
    const loopTickType = 1;

    /**
     * @var array
     */
    private $cronTasks = [];

    /**
     * @var array
     */
    private $cronNextDatetimeArr = [];

    /**
     * @var array
     */
    private $expression = [];

    /**
     * @var int
     */
    private $offsetSecond = 1;

    /**
     * @var array
     */
    private $timerIds = [];

    /**
     * @var array
     */
    private $channels = [];

    /**
     * CrontabManager constructor.
     * @throws CrontabException
     */
    protected function __construct()
    {
        if (function_exists('in_children_process_env')) {
            if (in_children_process_env() === false) {
                throw new CrontabException(__CLASS__ . " only use in children worker process");
            }
        }
    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param mixed $func
     * @param int $type
     * @param int $timeMs
     * @return mixed
     */
    public function addRule(
        string $cron_name,
        string $expression,
        callable $func,
        int $loop_type = self::loopChannelType,
        int $timeMs = 1 * 1000)
    {
        if (!class_exists('Cron\\CronExpression')) {
            throw new CrontabException("If you want to use Cron Expression, you need to install 'composer require dragonmantank/cron-expression' ");
        }

        if (!CronExpression::isValidExpression($expression)) {
            throw new CrontabException("Cron Expression format is wrong, please check it");
        }

        if (!is_callable($func)) {
            throw new CrontabException("Params function must be callable type");
        }

        if (!in_array($loop_type, [self::loopChannelType, self::loopTickType])) {
            throw new CrontabException("Params of loop_type error");
        }

        if (!isset($this->cronTasks[$cron_name])) {
            $this->cronTasks[$cron_name] = [$expression, $func, $loop_type];
        } else {
            throw new CrontabException("Cron Name=$cron_name haded exist, forbidden set same cron name again");
        }

        if ($loop_type == self::loopChannelType) {
            $channel = $this->channelLoop($cron_name, $expression, $func, $timeMs);
            return $channel;
        } else {
            $timerId = $this->tickLoop($cron_name, $expression, $func, $timeMs);
            return $timerId;
        }

    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param callable $func
     * @param float $timeMs
     * @return mixed
     */
    protected function tickLoop(string $cron_name, string $expression, callable $func, float $timeMs)
    {
        $channel = new \Swoole\Coroutine\Channel(1);
        GoCoroutine::go(function ($cron_name, $expression, $func, $msec) use ($channel) {
            $timerId = \Swoole\Timer::tick($msec, function ($timerId, $expression, $cron_name) use ($func) {
                $this->loopHandle($cron_name, $expression, $func);
            }, $expression, $cron_name);
            $channel->push($timerId);
        }, $cron_name, $expression, $func, $timeMs);

        $timerId = $channel->pop();
        $this->timerIds[$cron_name] = $timerId;
        $channel->close();
        unset($channel);
        return $timerId;
    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param callable $func
     * @param float $timeMs
     * @return \Swoole\Coroutine\Channel
     */
    protected function channelLoop(string $cron_name, string $expression, callable $func, float $timeMs)
    {
        $channel = \Workerfy\Coroutine\Timer::tick($timeMs, function () use($cron_name, $expression, $func) {
            $this->loopHandle($cron_name, $expression, $func);
        });

        $this->channels[$cron_name] = $channel;

        return $channel;
    }

    /**
     * @param string $cron_name
     * @param string $expression
     * @param callable $func
     * @throws \Throwable
     */
    protected function loopHandle(string $cron_name, string $expression, callable $func)
    {
        if(class_exists(CronExpression::class,'factory')) {
            $cron = CronExpression::factory($expression);
        }else {
            $cron = new CronExpression($expression);
        }

        $nowTime       = time();
        $expressionKey = md5($expression);

        $cronNextDatetime = strtotime($cron->getNextRunDate()->format('Y-m-d H:i:s'));
        if ($cron->isDue()) {
            if (!isset($this->cronNextDatetimeArr[$cron_name][$expressionKey])) {
                $this->expression[$cron_name][$expressionKey]          = $expression;
                $this->cronNextDatetimeArr[$cron_name][$expressionKey] = $cronNextDatetime;
            }

            if (($nowTime >= $this->cronNextDatetimeArr[$cron_name][$expressionKey] && $nowTime < ($cronNextDatetime - $this->offsetSecond))) {
                $this->cronNextDatetimeArr[$cron_name][$expressionKey] = $cronNextDatetime;
                if ($func instanceof \Closure) {
                    try {
                        call_user_func($func, $cron_name, $cron);
                    } catch (\Throwable $throwable) {
                        throw $throwable;
                    }
                }
            }

            if ($nowTime > $this->cronNextDatetimeArr[$cron_name][$expressionKey] || $nowTime >= $cronNextDatetime) {
                $this->cronNextDatetimeArr[$cron_name][$expressionKey] = $cronNextDatetime;
            }
        }
    }

    /**
     * @param string|null $cron_name
     * @return array|mixed|null
     */
    public function getCronTaskByName(?string $cron_name = null)
    {
        if ($cron_name) {
            if (isset($this->cronTasks[$cron_name])) {
                return $this->cronTasks[$cron_name];
            }
        }
        return $this->cronTasks;
    }

    /**
     * @param string|null $cron_name
     * @return mixed|null
     */
    public function getTimerIdByName(string $cron_name = null)
    {
        if ($cron_name) {
            if (isset($this->timerIds[$cron_name])) {
                return $this->timerIds[$cron_name];
            }
        }
    }

    /**
     * @param string $cron_name
     * @return \Swoole\Coroutine\Channel|null
     */
    public function getChannelByName(string $cron_name)
    {
        return $this->channels[$cron_name] ?? null;
    }

    /**
     * @param string $cron_name
     * @return void
     */
    public function cancelCronTask(string $cron_name)
    {
        $loopType = $this->getLoopType($cron_name) ?? null;
        if ($loopType == self::loopChannelType) {
            $channel = $this->channels[$cron_name];
            \Workerfy\Coroutine\Timer::cancel($channel);
            unset($this->channels[$cron_name], $channel);
        } else if ($loopType == self::loopTickType) {
            $tickId = $this->timerIds[$cron_name];
            \Swoole\Timer::clear($tickId);
            unset($this->timerIds[$cron_name]);
        }
        write_info("【Info】tickName={$cron_name} has been cancel");
        if (count($this->channels) == 0 && count($this->timerIds) == 0) {
            write_info("【Info】Process had not exit tick task");
        }
    }

    /**
     * @param string $cron_name
     * @return mixed|null
     */
    public function getLoopType(string $cron_name)
    {
        if ($cron_name) {
            if (isset($this->cronTasks[$cron_name])) {
                list($expression, $func, $loopType) = $this->cronTasks[$cron_name];
                return $loopType;
            }
        }
    }

    /**
     *
     * @return bool
     */
    public function hasRunningCronTask()
    {
        if (count($this->channels) > 0 || count($this->timerIds) > 0) {
            return true;
        }
        return false;
    }

}