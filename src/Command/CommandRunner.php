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

namespace Workerfy\Command;

use Workerfy\Log\LogManager;
use Swoole\Coroutine\Channel;
use Workerfy\Coroutine\GoCoroutine;
use Workerfy\Exception\CommandException;

class CommandRunner {
    /**
     * @var array
     */
    protected static $instances = [];

    /**
     * @var \Swoole\Coroutine\Channel
     */
    protected $channel;

    /**
     * @var int
     */
    protected $concurrent = 5;

    /**
     * @var bool
     */
    protected $isNextFlag = false;

    /**
     * @param string $runnerName
     * @param int $concurrent
     * @return CommandRunner
     */
    public static function getInstance(string $runnerName, int $concurrent = 5)
    {
        if(!isset(static::$instances[$runnerName]))
        {
            /**@var CommandRunner $runner*/
            $runner = new static();
            if($concurrent >= 10)
            {
                $concurrent = 10;
            }
            $runner->concurrent = $concurrent;
            $runner->channel = new Channel($runner->concurrent);
            static::$instances[$runnerName] = $runner;
        }else {
            /**@var CommandRunner $runner*/
            $runner = static::$instances[$runnerName];
        }

        return $runner;
    }

    /**
     * 执行外部系统程序，包括php,shell so on
     * 禁止swoole提供的process->exec，因为swoole的process->exec调用的程序会替换当前子进程
     * @param string $execBinFile
     * @param string $commandRouter
     * @param array $args
     * @param bool $async
     * @param string $log
     * @param bool $isExec
     * @throws CommandException
     * @return array
     */
    public function exec(
        string $execBinFile,
        string $commandRouter,
        array $args = [],
        bool $async = false,
        string $log = '/dev/null',
        bool $isExec = true
    ) {
        $this->checkNextFlag();
        $params = '';
        if($args) {
            $params = implode(' ', $args);
        }

        $path = $execBinFile.' '.$commandRouter.' '.$params;
        $command = "{$path} >> {$log} 2>&1 && echo $$";
        if($async) {
            // echo $! 表示输出进程id赋值在output数组中
            $command = "nohup {$path} >> {$log} 2>&1 & echo $!";
        }

        if($isExec)
        {
            exec($command,$output,$return);
            $pid = $output[0] ?? '';
            if($pid)
            {
                $this->channel->push([
                    'pid' => $pid,
                    'command' => $command,
                    'start_time' => time()
                ],0.2);
            }

            // when exec error save log
            if($return != 0) {
                $logger = LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
                if(is_object($logger)) {
                    $logger->info("CommandRunner Exec return={$return}", ['command' => $command, 'output'=>$output ?? '', 'return' => $return ?? '']);
                }
            }

        }

        return [$command, $output ?? [], $return ?? -1];
    }

    /**
     * @param callable $callable
     * @param string $execBinFile
     * @param array $args
     * @return mixed
     * @throws CommandException
     */
    public function procOpen(
        callable $callable,
        string $execBinFile,
        array $args = []
    ) {
        $this->checkNextFlag();
        $params = '';
        if($args) {
            $params = implode(' ', $args);
        }

        $command = $execBinFile.' '.$params;
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        GoCoroutine::go(function () use($callable, $command, $descriptors) {
            // in $callable forbidden create coroutine, because $proc_process had been bind in current coroutine
            try {
                $proc_process = proc_open($command, $descriptors, $pipes);
                if(!is_resource($proc_process)) {
                    throw new CommandException("Proc Open Command 【{$command}】 failed.");
                }
                $status = proc_get_status($proc_process);
                if($status['pid'] ?? '')
                {
                    $this->channel->push([
                        'pid' => $status['pid'],
                        'command' => $command,
                        'start_time' => time()
                    ],0.2);
                }
                array_push($pipes, $status);
                return call_user_func_array($callable, $pipes);
            }catch (\Throwable $e) {
                throw $e;
            }finally {
                foreach($pipes as $pipe) {
                    @fclose($pipe);
                }
                proc_close($proc_process);
            }
        });

    }

    /**
     * @return bool
     */
    public function isNextHandle()
    {
        $this->isNextFlag = true;
        if($this->channel->isFull())
        {
            $pids = [];
            while($item = $this->channel->pop(0.05))
            {
                $pid = $item['pid'];
                $startTime = $item['start_time'];
                if(\Swoole\Process::kill($pid,0) )
                {
                    if(($startTime + 60) > time() ) {
                        $pids[] = $item;
                    }else {
                        // 超过1分钟系统调用程序没执行完的都会记录一次
                        $command = $item['command'] ?? '';
                        $logger = LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
                        if(is_object($logger)) {
                            $logger->info("CommandRunner Long Time Run Process,Pid={$pid},startTime={$startTime},Command={$command},please check it.");
                        }
                    }
                }
            }

            foreach($pids as $item)
            {
                $this->channel->push($item,0.1);
            }

            if($this->channel->length() < $this->concurrent)
            {
                $isNext = true;
            }else
            {
                \Swoole\Coroutine\System::sleep(0.1);
            }
        }else
        {
            $isNext = true;
        }

        return $isNext ?? false;
    }

    /**
     * @throws CommandException
     */
    protected function checkNextFlag()
    {
        if(!$this->isNextFlag) {
            throw new CommandException('Missing call isNextHandle().');
        }
        $this->isNextFlag = false;
    }


    /**
     * __clone
     */
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

}