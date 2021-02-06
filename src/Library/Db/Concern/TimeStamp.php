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

namespace Workerfy\Library\Db\Concern;

use DateTime;

trait TimeStamp
{
    /**
     * @var string
     */
    public $dateFormat = 'Y-m-d H:i:s';

    /**
     * 时间日期字段格式化处理
     * @param  mixed $format    日期格式
     * @param  mixed $time      时间日期表达式
     * @param  bool  $timestamp 时间表达式是否为时间戳
     * @return mixed
     */
    protected function formatDateTime($format, $time = 'now', bool $timestamp = false)
    {
        if (empty($time)) {
            return null;
        }

        if (false === $format) {
            return $time;
        } elseif (false !== strpos($format, '\\')) {
            return new $format($time);
        }

        if ($time instanceof DateTime) {
            $dateTime = $time;
        } elseif ($timestamp) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int) $time);
        } else {
            $dateTime = new DateTime($time);
        }

        return $dateTime->format($format);
    }

}
