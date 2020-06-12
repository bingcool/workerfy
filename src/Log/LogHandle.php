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

namespace Workerfy\Log;

use Swoole\Coroutine;
use Workerfy\Log\Formatter\LineFormatter;

class LogHandle {
    /**
     * $channel,日志的通过主题，关于那方面的日志
     * @var null
     */
    public $channel = null;

    /**
     * $logFilePath
     * @var null
     */
    public $logFilePath = null;

    /**
     * $output,默认定义输出日志的文本格式
     * @var string
     */
    public $output = "[%datetime%] %channel%:%level_name%:%message%:%context%\n";

    /**
     * $formatter 格式化对象
     * @var LineFormatter
     */
    protected $formatter = null;

    /**
     * __construct
     */
    public function __construct(
        string $channel = null,
        string $logFilePath = null,
        string $output = null,
        string $dateformat = null)
    {
        $this->channel = $channel;
        $this->logFilePath = $logFilePath;
        $output && $this->output = $output;
        $this->formatter = new LineFormatter($this->output, $dateformat);
    }

    /**
     * setChannel
     * @param    string $channel
     * @return   mixed  $this
     */
    public function setChannel($channel) {
        $this->channel = $channel;
        return $this;
    }

    /**
     * setLogFilePath
     * @param   string $logFilePath
     * @return  mixed  $this
     */
    public function setLogFilePath($logFilePath) {
        $this->logFilePath = $logFilePath;
        return $this;
    }

    /**
     * setOutputFormat
     * @param    string $output
     * @return   mixed $this
     */
    public function setOutputFormat($output) {
        $this->output = $output;
        $this->formatter = new LineFormatter($this->output, $dateformat = null);
        return $this;
    }

    /**
     * @return null|string
     */
    public function getChannel() {
        return $this->channel;
    }

    /**
     * @return null|string
     */
    public function getLogFilePath() {
        return $this->logFilePath;
    }

    /**
     * @return null|string
     */
    public function getOutputFormat() {
        return $this->formatter;
    }

    /**
     * addInfo
     * @param $logInfo
     * @param array $context
     * @param bool $enable_continue
     */
    public function info($logInfo, array $context = [], $enable_continue = true) {
        $this->logHandle($logInfo, $context, $enable_continue, Logger::INFO);
    }

    /**
     * addNotice
     * @param $logInfo
     * @param array $context
     * @param bool $enable_continue
     * @param \Throwable
     */
    public function notice($logInfo, array $context = [], $enable_continue = true) {
        $this->logHandle($logInfo, $context, $enable_continue, Logger::NOTICE);
    }

    /**
     * addWarning
     * @param $logInfo
     * @param bool $enable_continue
     * @param array $context
     */
    public function warning($logInfo, array $context = [], $enable_continue = true) {
        $this->logHandle($logInfo, $context, $enable_continue, Logger::WARNING);
    }

    /**
     * addError
     * @param $logInfo
     * @param bool $enable_continue
     * @param array $context
     */
    public function error($logInfo, array $context = [], $enable_continue = true) {
        $this->logHandle($logInfo, $context, $enable_continue, Logger::ERROR);
    }

    /**
     * @param $logInfo
     * @param array $context
     * @param bool $enable_continue
     * @param $logType
     */
    public function logHandle($logInfo, $context = [], $enable_continue = true, $logType) {
        try{
            if($enable_continue) {
                Coroutine::create(function() use($logInfo, $context, $logType) {
                    $this->insertLog($logInfo, $context, $logType);
                });
            }else {
                $this->insertLog($logInfo, $context, $logType);
            }
        }catch (\Throwable $e) {
            $this->insertLog($logInfo, $context, Logger::ERROR);
        }
    }

    /**
     * @param $logInfo
     * @param $context
     * @param int $type
     */
    public function insertLog($logInfo, array $context = [], $type = Logger::INFO) {
        if(is_array($logInfo)) {
            $logInfo = json_encode($logInfo, JSON_UNESCAPED_UNICODE);
        }
        $log = new Logger($this->channel);
        $stream = new StreamHandler($this->logFilePath, $type);
        $stream->setFormatter($this->formatter);
        $log->pushHandler($stream);
        // add records to the log
        $log->addRecord($type, $logInfo, $context);
    }

}