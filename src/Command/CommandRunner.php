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

use Workerfy\Exception\CommandException;
use Workerfy\Log\LogManager;

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
            $runner->channel = new \Swoole\Coroutine\Channel($runner->concurrent);
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
        if(!$this->isNextFlag) {
            throw new CommandException('Missing call isNextHandle()');
        }
        $this->isNextFlag = false;
        $params = '';
        if($args)
        {
            $params = implode(' ', $args);
        }

        $path = $execBinFile.' '.$commandRouter.' '.$params;
        $command = "{$path} >> {$log} 2>&1 && echo $$";
        if($async)
        {
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
                    'start_time' => time()
                ],0.2);
            }

            $logger = LogManager::getInstance()->getLogger(LogManager::RUNTIME_ERROR_TYPE);
            if(is_object($logger))
            {
                $logger->info("CommandRunner Exec return={$return}", ['command' => $command, 'output'=>$output ?? '', 'return' => $return ?? '']);
            }
        }

        return [$command, $output ?? [], $return ?? -1];
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
                if(\Swoole\Process::kill($pid,0) || ($startTime + 60) < time() )
                {
                    $pids[] = $item;
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
                \Swoole\Coroutine\System::sleep(0.05);
            }
        }else
        {
            $isNext = true;
        }

        return $isNext ?? false;
    }

    /**
     * @param callable $callable
     * @param $execFile
     * @param array $args
     * @return mixed
     * @throws \Throwable
     */
    public static function procOpen(callable $callable, $execFile, array $args = []) {
        $params = '';
        if($args)
        {
            $params = implode(' ', $args);
        }

        $command = $execFile.' '.$params;
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $proc_process = proc_open($command, $descriptors, $pipes);
        // in $callable forbidden create coroutine, because $proc_process had been bind in current coroutine
        try {
            array_push($pipes, $command);
            return call_user_func_array($callable, $pipes);
        }catch (\Throwable $e) {
            throw $e;
        }finally {
            proc_close($proc_process);
        }
    }

}