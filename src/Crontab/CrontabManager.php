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

class CrontabManager {

    use \Workerfy\Traits\SingletonTrait;

	private $cron_tasks = [];

	private $cron_next_datetime = [];

	private $expression = [];

	private $offset_second = 1;

	private $timer_ids = [];

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
     * @param int    $msec
     * @throws \Exception
     */
	public function addRule(string $cron_name, string $expression, $func, int $msec = 1 * 1000) {
	    if(!class_exists('Cron\\CronExpression')) {
	        throw new \Exception("If you want to use crontab, you need to install 'composer require dragonmantank/cron-expression' ", 1);
        }

	    if(!CronExpression::isValidExpression($expression)) {
            throw new \Exception("Crontab expression format is wrong, please check it", 1);
        }

	    if(!is_callable($func)) {
            throw new \Exception("Params func must be callable", 1);
        }

        $cron_name_key = md5($cron_name);

        if(is_array($func)) {
            if(!isset($this->cron_tasks[$cron_name_key])) {
                $this->cron_tasks[$cron_name_key] = [$expression, $func];
                $timer_id = \Swoole\Timer::tick($msec, $func, $expression);
            }
        }else {
            if(!isset($this->cron_tasks[$cron_name_key])) {
                $this->cron_tasks[$cron_name_key] = [$expression, $func];
                $timer_id = \Swoole\Timer::tick($msec, function ($timer_id, $expression) use ($func) {
                    $expression_key = md5($expression);
                    $cron = CronExpression::factory($expression);
                    $now_time = time();
                    $cron_next_datetime = strtotime($cron->getNextRunDate()->format('Y-m-d H:i:s'));
                    if($cron->isDue()) {
                        if (!isset($this->cron_next_datetime[$expression_key])) {
                            $this->expression[$expression_key] = $expression;
                            $this->cron_next_datetime[$expression_key] = $cron_next_datetime;
                        }

                        if (($now_time >= $this->cron_next_datetime[$expression_key] && $now_time < ($cron_next_datetime - $this->offset_second))) {
                            $this->cron_next_datetime[$expression_key] = $cron_next_datetime;
                            if ($func instanceof \Closure) {
                                call_user_func($func, $cron);
                            }
                        }

                        // 防止万一出现的异常出现，比如没有命中任务， 19:05:00要命中的，由于其他网络或者服务器其他原因，阻塞了,造成延迟，现在时间已经到了19::05:05
                        if ($now_time > $this->cron_next_datetime[$expression_key] || $now_time >= $cron_next_datetime) {
                            $this->cron_next_datetime[$expression_key] = $cron_next_datetime;
                        }
                    }
                }, $expression);
            }
        }
        isset($timer_id) && $this->timer_ids[$cron_name_key] = $timer_id;
        unset($cron_name_key);
        return $timer_id;
    }

    /**
     * @param string|null $cron_name
     * @return array|mixed|null
     */
	public function getCronTaskByName(string $cron_name = null) {
		if($cron_name) {
			$cron_name_key = md5($cron_name);
			if(isset($this->cron_tasks[$cron_name_key])) {
				return $this->cron_tasks[$cron_name_key];
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
            $cron_name_key = md5($cron_name);
            if(isset($this->timer_ids[$cron_name_key])) {
                return $this->timer_ids[$cron_name_key];
            }
            return null;
        }
	}

}