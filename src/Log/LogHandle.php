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
     * @var null
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
        //$formatter对象
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
        try {
            if($enable_continue) {
                go(function() use($logInfo, $context) {
                    $this->insertLog($logInfo, $context, Logger::INFO);
                });
            }else {
                $this->insertLog($logInfo, $context, Logger::INFO);
            }
        }catch (\Throwable $e) {
            $this->insertLog($logInfo, $context, Logger::INFO);
        }
    }

    /**
     * addNotice
     * @param $logInfo
     * @param array $context
     * @param bool $enable_continue
     * @param \Throwable
     */
    public function notice($logInfo, array $context = [], $enable_continue = true) {
        try {
            if($enable_continue) {
                go(function() use($logInfo, $context) {
                    $this->insertLog($logInfo, $context, Logger::NOTICE);
                });
            }else {
                $this->insertLog($logInfo, $context, Logger::NOTICE);
            }
        }catch (\Throwable $e) {
            $this->insertLog($logInfo, $context, Logger::NOTICE);
        }
    }

    /**
     * addWarning
     * @param $logInfo
     * @param bool $enable_continue
     * @param array $context
     */
    public function warning($logInfo, array $context = [], $enable_continue = true) {
        try {
            if($enable_continue) {
                go(function() use($logInfo, $context) {
                    $this->insertLog($logInfo, $context, Logger::WARNING);
                });
            }else {
                $this->insertLog($logInfo, $context, Logger::WARNING);
            }
        }catch (\Throwable $e) {
            $this->insertLog($logInfo, $context, Logger::WARNING);
        }
    }

    /**
     * addError
     * @param $logInfo
     * @param bool $enable_continue
     * @param array $context
     */
    public function error($logInfo, array $context = [], $enable_continue = true) {
        try{
            if($enable_continue) {
                go(function() use($logInfo, $context) {
                    $this->insertLog($logInfo, $context, Logger::ERROR);
                });
            }else {
                $this->insertLog($logInfo, $context, Logger::ERROR);
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