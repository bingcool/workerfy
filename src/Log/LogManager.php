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

class LogManager {

    use \Workerfy\Traits\SingletonTrait;

    const DEFAULT_TYPE = 'default';

    const RUNTIME_ERROR_TYPE = 'runtime';

    protected $logger = [];

    /**
     * __construct
     * @param mixed $log
     */
    private function __construct() {}

    /**
     * registerLogger
     * @param  string|null $channel    
     * @param  string|null $logFilePath
     * @param  string|null $output     
     * @param  string|null $dateformat 
     * @return LogHandle
     */
    public function registerLogger(
        string $type = self::DEFAULT_TYPE,
        string $logFilePath = null,
        string $channel = 'workerfy',
        string $output = null,
        string $dateformat = null
    ) {
        if($channel && $logFilePath) {
            $this->logger[$type] = new LogHandle($channel, $logFilePath, $output, $dateformat);
        }

        return $this->logger[$type];
    }

    /**
     * registerLoggerByClosure
     * @param  \Closure  $func
     * @param  string $log_name
     * @return mixed
     */
    public function registerLoggerByClosure(\Closure $func, string $type = self::DEFAULT_TYPE) {
        $this->logger[$type] = call_user_func($func, $type);
    }

    /**
     * getLogger
     * @return LogHandle
     */
    public function getLogger($type = self::DEFAULT_TYPE) {
        return $this->logger[$type] ?? null;
    }
}