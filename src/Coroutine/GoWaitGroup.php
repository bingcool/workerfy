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

namespace Workerfy\Coroutine;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class GoWaitGroup {
    /**
     * @var int
     */
    private $count = 0;

    /**
     * @var Channel
     */
    private $chan;

    /**
     * @var array
     */
    private $result = [];

    /**
     * WaitGroup constructor
     */
    public function __construct() {
        $this->chan = new Channel;
    }

    /**
     * go
     */
    public function go(\Closure $callBack) {
        Coroutine::create(function () use($callBack) {
            try{
                $this->count++;
                $callBack->call($this);
            }catch (\Throwable $throwable) {
                $this->count--;
                $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE);
                $logger->error(sprintf("%s on File %s on Line %d", $throwable->getMessage(), $throwable->getFile(), $throwable->getLine()));
            }
        });
    }

    /**
     * start
     */
    public function start() {
        $this->count++;
        return $this->count;
    }

    /**
     * done
     */
    public function done(string $key = null, $data = null, float $timeout = -1) {
        if(!empty($key) && !empty($data)) {
            $this->result[$key] = $data;
        }
        $this->chan->push(1, $timeout);
    }

    /**
     * wait
     */
    public function wait(float $timeout = 0) {
        while($this->count--) {
            $this->chan->pop($timeout);
        }
        $result = $this->result;
        $this->reset();
        return $result;
    }

    /**
     * reset
     */
    public function reset() {
        $this->result = [];
        $this->count = 0;
    }

}