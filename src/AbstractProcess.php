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

namespace Workerfy;

use Swoole\Event;
use Swoole\Process;
use Swoole\Coroutine\Channel;
use Workerfy\Crontab\CrontabManager;

/**
 * Class AbstractProcess
 * @package Workerfy
 */

abstract class AbstractProcess {

    /**
     * @var AbstractProcess
     */
    protected static $processInstance;

    /**
     * @var Process
     */
    private $swooleProcess;

    /**
     * @var string
     */
    private $process_name;

    /**
     * @var bool|null
     */
    private $async = null;

    /**
     * @var array
     */
    private $args = [];

    /**
     * @var null
     */
    private $extend_data;

    /**
     * @var bool
     */
    private $enable_coroutine = false;

    /**
     * @var int
     */
    private $pid;

    /**
     * @var int
     */
    private $master_pid;

    /**
     * @var int
     */
    private $process_worker_id = 0;

    /**
     * @var mixed
     */
    private $user;

    /**
     * @var mixed
     */
    private $group;

    /**
     * @var bool
     */
    private $is_reboot = false;

    /**
     * @var bool
     */
    private $is_exit = false;

    /**
     * @var bool
     */
    private $is_force_exit = false;

    /**
     * @var int
     */
    private $process_type = 1;// 1-静态进程，2-动态进程

    /**
     * @var int|float
     */
    private $wait_time = 30;

    /**
     * @var int
     */
    private $reboot_timer_id;

    /**
     * @var int
     */
    private $exit_timer_id;

    /**
     * @var int
     */
    private $coroutine_id;

    /**
     * @var string
     */
    private $start_time;

    /**
     * @var int
     */
    private $master_live_timer_id;

    /**
     * 动态进程正在销毁时，原则上在一定时间内不能动态创建进程，常量DYNAMIC_DESTROY_PROCESS_TIME
     * @var bool
     */
    private $is_dynamic_destroy = false;

    /**
     * 自动重启次数
     * @var int
     */
    private $reboot_count = 0;

    /**
     * 停止时，存在挂起的协程，进行轮询次数协程是否恢复，并执行完毕，默认5次,子类可以重置
     * @var int
     */
    protected $cycle_times = 5;

    /**
     * @var array cli init params
     */
    protected $cli_init_params = [];

    const PROCESS_STATIC_TYPE = 1; //静态进程
    const PROCESS_DYNAMIC_TYPE = 2; //动态进程
    const PROCESS_STATIC_TYPE_NAME = 'static';
    const PROCESS_DYNAMIC_TYPE_NAME = 'dynamic';
    const WORKERFY_PROCESS_REBOOT_FLAG = "process::worker::action::reboot";
    const WORKERFY_PROCESS_EXIT_FLAG = "process::worker::action::exit";

    /**
     * 动态进程销毁间隔多少秒后，才能再次接受动态创建，防止频繁销毁和创建，最大300s
     * @var int
     */
    const DYNAMIC_DESTROY_PROCESS_TIME = 300;

    /**
     * 定时检查master是否存活的轮询时间
     * @var int
     */
    const CHECK_MASTER_LIVE_TICK_TIME = 60;

    /**
     * AbstractProcess constructor.
     * @param string $process_name
     * @param bool   $async
     * @param array  $args
     * @param null   $extend_data
     * @param bool   $enable_coroutine
     */
    public function __construct(
        string $process_name,
        bool $async = true,
        array $args = [],
        $extend_data = null,
        bool $enable_coroutine = true
    ) {
        $this->async = $async;
        $this->args = $args;
        $this->extend_data = $extend_data;
        $this->process_name = $process_name;
        $this->enable_coroutine = $enable_coroutine;

        if(isset($args['wait_time']) && is_numeric($args['wait_time'])) {
            $this->wait_time = $args['wait_time'];
        }

        if(isset($args['user']) && is_string($args['user'])) {
            $this->user = $args['user'];
        }

        if(isset($args['group']) && is_string($args['group'])) {
            $this->group = $args['group'];
        }

        if(isset($args['max_process_num'])) {}

        if(isset($args['dynamic_destroy_process_time'])) {}

        $this->args['check_master_live_tick_time'] = self::CHECK_MASTER_LIVE_TICK_TIME;

        if(isset($args['check_master_live_tick_time'])) {
            if($args['check_master_live_tick_time'] < self::CHECK_MASTER_LIVE_TICK_TIME) {
                $this->args['check_master_live_tick_time'] = self::CHECK_MASTER_LIVE_TICK_TIME;
            }
        }
        $this->swooleProcess = new \Swoole\Process([$this,'__start'], false, 2, $enable_coroutine);
    }

    /**
     * __start 创建process的成功回调处理
     * @param  Process $swooleProcess
     * @return mixed
     */
    public function __start(Process $swooleProcess) {
        try {
            if($this->is_exit) {
                return false;
            }
            static::$processInstance = $this;
            $this->pid = $this->swooleProcess->pid;
            $this->coroutine_id = \Swoole\Coroutine::getCid();
            $this->setUserAndGroup();
            if($this->async) {
                Event::add($this->swooleProcess->pipe, function () {
                    try {
                        $msg = $this->swooleProcess->read(64 * 1024);
                        if (is_string($msg)) {
                            $message = json_decode($msg, true);
                            @list($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master) = $message;
                            if (!isset($is_proxy_by_master) || is_null($is_proxy_by_master) || $is_proxy_by_master === false) {
                                $is_proxy_by_master = false;
                            } else {
                                $is_proxy_by_master = true;
                            }
                        }
                        if ($msg && isset($from_process_name) && isset($from_process_worker_id)) {
                            $is_call_pipe = true;
                            if (is_string($msg)) {
                                switch ($msg) {
                                    case self::WORKERFY_PROCESS_REBOOT_FLAG :
                                        $is_call_pipe = false;
                                        \Swoole\Coroutine::create(function () {
                                            $this->reboot();
                                        });
                                        break;
                                    case self::WORKERFY_PROCESS_EXIT_FLAG :
                                        $is_call_pipe = false;
                                        \Swoole\Coroutine::create(function () use($from_process_name) {
                                            if($from_process_name == ProcessManager::MASTER_WORKER_NAME) {
                                                $this->exit(true);
                                            }else {
                                                $this->exit();
                                            }
                                        });
                                        break;
                                }

                            }
                            if ($is_call_pipe === true) {
                                \Swoole\Coroutine::create(function () use($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master) {
                                    try {
                                        $this->onPipeMsg($msg, $from_process_name, $from_process_worker_id, $is_proxy_by_master);
                                    }catch (\Throwable $throwable) {
                                        $this->onHandleException($throwable);
                                    }
                                });
                            }
                        }
                    }catch (\Throwable $throwable) {
                        \Swoole\Coroutine::create(function () use($throwable) {
                            $this->onHandleException($throwable);
                        });
                    }
                });
            }

            // exit
            Process::signal(SIGTERM, function ($signo) {
                try {
                    if(method_exists($this,'__destruct')) {
                        $this->__destruct();
                    }
                    $this->writeStopFormatInfo();
                    // clear
                    if($this->master_live_timer_id) {
                        @\Swoole\Timer::clear($this->master_live_timer_id);
                    }
                    $processName = $this->getProcessName();
                    $workerId = $this->getProcessWorkerId();
                    write_info("【Info】 Start to exit process={$processName}, worker_id={$workerId}");
                }catch (\Throwable $throwable)
                {
                    write_info("【Error】process=$processName exit error:".$throwable->getMessage());
                }finally {
                    Event::del($this->swooleProcess->pipe);
                    Event::exit();
                    $this->swooleProcess->exit(SIGTERM);
                }
            });

            // reboot
            Process::signal(SIGUSR1, function ($signo) {
                // clear
                try {
                    if($this->master_live_timer_id) {
                        @\Swoole\Timer::clear($this->master_live_timer_id);
                    }
                    // destroy
                    if(method_exists($this,'__destruct')) {
                        $this->__destruct();
                    }
                    $processName = $this->getProcessName();
                    $workerId = $this->getProcessWorkerId();
                    write_info("【Info】Start to reboot process={$processName}, worker_id={$workerId}");
                }catch (\Throwable $throwable)
                {
                    write_info("【Error】process=$processName reboot error:".$throwable->getMessage());
                }finally {
                    Event::del($this->swooleProcess->pipe);
                    Event::exit();
                    $this->swooleProcess->exit(SIGUSR1);
                }
            });

            // 定时检测父进程是否存活,否则自身要退出
            $this->master_live_timer_id = \Swoole\Timer::tick(($this->args['check_master_live_tick_time'] + rand(1, 10)) * 1000, function($timer_id) {
                if($this->isMasterLive() === false) {
                    \Swoole\Timer::clear($timer_id);
                    $this->master_live_timer_id = null;
                    $processName = $this->getProcessName();
                    $workerId = $this->getProcessWorkerId();
                    $masterPid = $this->getMasterPid();
                    write_info("【Warming】定时检测到父进程master_pid={$masterPid}不存在，子进程process={$processName},worker_id={$workerId} start to exit");
                    $this->exit(true, 1);
                }
                if($this->getProcessWorkerId() == 0 && $this->master_pid) {
                    $this->saveMasterId($this->master_pid);
                }
            });

            // 这里子进程不需要SIGUSR2，需要移除信号监听
            @Process::signal(SIGUSR2, null);

            if(PHP_OS != 'Darwin') {
                $process_type_name = $this->getProcessTypeName();
                $this->swooleProcess->name("php-process-worker[{$process_type_name}]:".$this->getProcessName().'@'.$this->getProcessWorkerId());
            }

            $this->writeStartFormatInfo();

            try{
                $targetAction = 'init';
                if(method_exists($this,$targetAction))
                {
                    // init() method will accept cli params from cli,as --sleep=5 --name=bing
                    list($method, $args) =  Helper::parseActionParams($this, $targetAction, Helper::getCliParams());
                    $this->cli_init_params = $args;
                    $this->{$targetAction}(...$args);
                }
                $this->run();
            }catch(\Throwable $throwable) {
                $this->onHandleException($throwable);
            }

        }catch(\Throwable $throwable) {
            $this->onHandleException($throwable);
        }
    }

    /**
     * writeByProcessName worker进程向某个进程写数据
     * @param string $process_name
     * @param $data
     * @param int $process_worker_id
     * @param bool $is_use_master_proxy
     * @return bool
     * @throws \Exception
     */
    public function writeByProcessName(string $process_name, $data, int $process_worker_id = 0, bool $is_use_master_proxy = true) {
        $processManager = \Workerfy\processManager::getInstance();
        $isMaster = $processManager->isMaster($process_name);
        $from_process_name = $this->getProcessName();
        $from_process_worker_id = $this->getProcessWorkerId();
        
        if($from_process_name == $process_name && $process_worker_id == $from_process_worker_id) {
            throw new \Exception('Process can\'t write message to myself');
        }

        if($isMaster) {
            $to_process_worker_id = 0;
            $message = json_encode([$data, $from_process_name, $from_process_worker_id, $processManager->getMasterWorkerName(), $to_process_worker_id], JSON_UNESCAPED_UNICODE);
            $this->getSwooleProcess()->write($message);
            return true;
        }

        $process_workers = [];
        $to_target_process = $processManager->getProcessByName($process_name, $process_worker_id);
        if(is_object($to_target_process) && $to_target_process instanceof AbstractProcess) {
            $process_workers = [$process_worker_id => $to_target_process];
        }else if(is_array($to_target_process)) {
            $process_workers = $to_target_process;
        }

        foreach($process_workers as $process_worker_id => $process) {
            // 进程处于rebooting|Exiting时，不再发msg
            if($process->isRebooting() || $process->isExiting()) {
                write_info("【Warming】the process(worker_id={$this->getProcessWorkerId()}) is in isRebooting or isExiting status, not send msg to other process");
                continue;
            }
            $to_process_name = $process->getProcessName();
            $to_process_worker_id = $process->getProcessWorkerId();

            $message = json_encode([$data, $from_process_name, $from_process_worker_id, $to_process_name, $to_process_worker_id], JSON_UNESCAPED_UNICODE);
            if($is_use_master_proxy) {
                $this->getSwooleProcess()->write($message);
            }else {
                $is_proxy = false;
                $message = json_encode([$data, $from_process_name, $from_process_worker_id, $is_proxy], JSON_UNESCAPED_UNICODE);
                $process->getSwooleProcess()->write($message);
            }
        }
    }

    /**
     * writeToMasterProcess 直接向master进程写数据
     * @param string $process_name
     * @param mixed $data
     * @param int $process_worker_id
     * @return bool
     * @throws \Exception
     */
    public function writeToMasterProcess(string $process_name, $data, int $process_worker_id = 0) {
        $is_use_master_proxy = false;
        return $this->writeByProcessName($process_name, $data, $process_worker_id, $is_use_master_proxy);
    }

    /**
     * writeToWorkerByMasterProxy 向master进程写代理数据，master在代理转发worker进程
     * @param string $process_name
     * @param mixed $data
     * @param int $process_worker_id
     * @return void
     * @throws \Exception
     */
    public function writeToWorkerByMasterProxy(string $process_name, $data, int $process_worker_id = 0) {
        $is_use_master_proxy = true;
        $this->writeByProcessName($process_name, $data, $process_worker_id, $is_use_master_proxy);
    }

    /**
     * notifyMasterCreateDynamicProcess 通知master进程动态创建进程
     * @param string $dynamic_process_name
     * @param int $dynamic_process_num
     * @return void
     * @throws \Exception
     */
    public function notifyMasterCreateDynamicProcess(string $dynamic_process_name, int $dynamic_process_num = 2) {
        if($this->is_dynamic_destroy) {
            write_info("【Warming】process is destroying, forbidden dynamic create process");
            return;
        }
        $data = [
            ProcessManager::CREATE_DYNAMIC_WORKER,
            $dynamic_process_name,
            $dynamic_process_num
        ];
        $this->writeToMasterProcess(ProcessManager::MASTER_WORKER_NAME, $data);
    }

    /**
     * notifyMasterDestroyDynamicProcess 通知master销毁动态创建的进程
     * @param string $dynamic_process_name
     * @param int $dynamic_process_num
     * @throws \Exception
     */
    public function notifyMasterDestroyDynamicProcess(string $dynamic_process_name, int $dynamic_process_num = -1) {
        if(!$this->is_dynamic_destroy) {
            // 销毁默认是销毁所有动态创建的进程，没有部分销毁,$dynamic_process_num设置没有意义
            $dynamic_process_num = -1;
            $data = [
                ProcessManager::DESTROY_DYNAMIC_PROCESS,
                $dynamic_process_name,
                $dynamic_process_num
            ];
            $this->writeToMasterProcess(ProcessManager::MASTER_WORKER_NAME, $data);
            // 发出销毁指令后，需要在一定时间内避免继续调用动态创建和动态销毁通知这两个函数，因为进程销毁时存在wait_time
            $this->isDynamicDestroy(true);
            if(isset($this->getArgs()['dynamic_destroy_process_time'])) {
                $dynamic_destroy_process_time = $this->getArgs()['dynamic_destroy_process_time'];
                // 最大时间不能太长
                if(is_numeric($dynamic_destroy_process_time)) {
                    if($dynamic_destroy_process_time > 300) {
                        $dynamic_destroy_process_time = self::DYNAMIC_DESTROY_PROCESS_TIME;
                    }
                }else {
                    $dynamic_destroy_process_time = $this->wait_time + 10;
                }
            }else {
                $dynamic_destroy_process_time = $this->wait_time + 10;
            }
            // 等待
            \Swoole\Coroutine::sleep($dynamic_destroy_process_time);
            $this->isDynamicDestroy(false);
        }
    }

    /**
     * 是否正在动态进程销毁中状态
     * @param bool $is_destroy
     */
    public function isDynamicDestroy(bool $is_destroy) {
        $this->is_dynamic_destroy = $is_destroy;
    }

    /**
     * start
     * @return mixed
     */
    public function start() {
        return $this->swooleProcess->start();
    }

    /**
     * getProcess 获取process进程对象
     * @return object
     */
    public function getProcess() {
        return $this->swooleProcess;
    }

    /**
     * @return mixed
     */
    public function getCoroutineId() {
        return $this->coroutine_id;
    }

    /**
     * setProcessWorkerId
     * @param int $id
     */
    public function setProcessWorkerId(int $id) {
        $this->process_worker_id = $id;
    }

    /**
     * @param int $process_type
     */
    public function setProcessType(int $process_type = 1) {
        $this->process_type = $process_type;
    }

    /**
     * @return int
     */
    public function getProcessType() {
        return $this->process_type;
    }

    /**
     * @param int $master_pid
     */
    public function setMasterPid(int $master_pid) {
        $this->master_pid = $master_pid;
    }

    /**
     * @return mixed
     */
    public function getMasterPid() {
        return $this->master_pid;
    }

    /**
     * @param int $wait_time
     */
    public function setWaitTime(float $wait_time = 30) {
        $this->wait_time = $wait_time;
    }

    /**
     * getWaitTime
     */
    public function getWaitTime() {
        return $this->wait_time;
    }

    /**
     * isRebooting
     * @return bool
     */
    public function isRebooting() {
        return $this->is_reboot;
    }

    /**
     * isExiting
     * @return bool
     */
    public function isExiting() {
        return $this->is_exit;
    }

    /**
     * 静态创建进程，属于进程池进程，可以自重启，退出
     * @return bool
     */
    public function isStaticProcess() {
        if($this->process_type == 1) {
            return true;
        }
        return false;
    }

    /**
     * 动态创建进程,工作完只能退出，不能重启
     * @return bool
     */
    public function isDynamicProcess() {
        return !$this->isStaticProcess();
    }

    /**
     * @return Process
     */
    public function getSwooleProcess() {
        return $this->swooleProcess;
    }

    /**
     * getProcessWorkerId
     * @return int
     */
    public function getProcessWorkerId() {
        return $this->process_worker_id;
    }

    /**
     * getPid
     * @return int
     */
    public function getPid() {
        return $this->swooleProcess->pid;
    }

    /**
     * @return bool
     */
    public function isStart() {
        if(isset($this->pid) && $this->pid > 0) {
            return true;
        }
        return false;
    }

    /**
     * getProcessName
     * @return string
     */
    public function getProcessName() {
        return $this->process_name;
    }

    /**
     * @return string
     */
    public function getProcessTypeName() {
        if($this->getProcessType() == self::PROCESS_STATIC_TYPE) {
            $process_type_name = self::PROCESS_STATIC_TYPE_NAME;
        }else {
            $process_type_name = self::PROCESS_DYNAMIC_TYPE_NAME;
        }

        return $process_type_name;
    }

    /**
     * getArgs 获取变量参数
     * @return mixed
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * @return mixed
     */
    public function getExtendData() {
        return $this->extend_data;
    }

    /**
     * isAsync
     * @return boolean
     */
    public function isAsync() {
        return $this->async;
    }

    /**
     * 是否启用协程
     * @return boolean
     */
    public function isEnableCoroutine() {
        return $this->enable_coroutine;
    }

    /**
     * @return mixed
     */
    public function getRebootTimerId() {
        return $this->reboot_timer_id;
    }

    /**
     * @param int $count
     */
    public function setRebootCount(int $count) {
        $this->reboot_count = $count;
    }

    /**
     * @return int
     */
    public function getRebootCount() {
        return $this->reboot_count;
    }

    /**
     * @return mixed
     */
    public function getExitTimerId() {
        return $this->exit_timer_id;
    }

    /**
     * setStartTime
     */
    public function setStartTime() {
        $this->start_time = strtotime('now');
    }

    /**
     * @return mixed
     */
    public function getStartTime() {
        return $this->start_time;
    }

    /**
     * 获取cli命令行传入的参数选项
     * @param string $name
     * @return array|false|string
     */
    public function getCliEnvParam(string $name) {
        $value = @getenv($name);
        if($value !== false) {
            return base64_decode($value) ?? $value;
        }
        return null;
    }

    /**
     * reboot 自动重启
     * @param float $wait_time
     * @return bool
     */
    public function reboot(float $wait_time = null) {
        if(!$this->isStaticProcess()) {
            $this->writeReloadFormatInfo();
            return false;
        }

        // 设置强制退出后，不能再设置reboot
        if($this->is_force_exit) {
            return false;
        }

        // reboot or exit status
        if($this->is_reboot || $this->is_exit) {
            return false;
        }

        // 自定义等待重启时间
        if($wait_time) {
            $this->wait_time = $wait_time;
        }

        $pid = $this->getPid();
        if(Process::kill($pid, 0)) {
            $this->is_reboot = true;
            $channel = new Channel(1);
            $timer_id = \Swoole\Timer::after($this->wait_time * 1000, function() use($pid) {
                try {
                    $this->runtimeCoroutineWait($this->cycle_times);
                    $this->onShutDown();
                }catch (\Throwable $throwable) {
                    $this->onHandleException($throwable);
                }finally {
                    $this->kill($pid, SIGUSR1);
                }
            });
            $this->reboot_timer_id = $timer_id;
            // 阻塞等待reboot，防止父协程继续往下执行逻辑
            $channel->pop(-1);
        }
        return true;
    }

    /**
     * 直接退出进程
     * @param bool $is_force 是否强制退出
     * @param float  $wait_time
     * @return bool
     */
    public function exit(bool $is_force = false, float $wait_time = null) {
        // 设置强制退出后，不能再设置exit
        if($this->is_force_exit) {
            return false;
        }

        // reboot or exit status
        if($this->is_reboot || $this->is_exit) {
            return false;
        }
        $pid = $this->getPid();
        if(Process::kill($pid, 0)) {
            $this->is_exit = true;
            if($is_force) {
                $this->is_force_exit = true;
            }
            // 设置了reboot的定时器，需要清除
            $this->clearRebootTimer();

            // 自定义退出等待时间
            $wait_time && $this->wait_time = $wait_time;

            $channel = new Channel(1);
            $timer_id = \Swoole\Timer::after($this->wait_time * 1000, function() use($pid) {
                try {
                    $this->runtimeCoroutineWait($this->cycle_times);
                    $this->onShutDown();
                }catch (\Throwable $throwable) {
                    $this->onHandleException($throwable);
                }finally {
                    $this->kill($pid, SIGTERM);
                }
            });
            $this->exit_timer_id = $timer_id;
            // 阻塞等待退出，防止父协程继续往下执行逻辑
            $channel->pop(-1);
            return true;
        }

    }

    /**
     * registerTickReboot
     * 注册定时重启, 一般在init()函数中注册
     */
    protected function registerTickReboot($cron_expression)
    {
        $tickSecond = 2;
        $waitTime = 5;
        if(is_numeric($cron_expression))
        {
            // eg: reboot/600s 启动后，每600s后重启
            if($cron_expression < 120)
            {
                $sleep = 120;
            }else
            {
                $sleep = $cron_expression;
            }
            \Swoole\Timer::tick(120 * 1000, function() use($sleep, $waitTime) {
                if(time() - $this->getStartTime() >= $sleep)
                {
                    $this->reboot($waitTime);
                }
            });
        }else {
            // eg:crontab expression 定时重启
            CrontabManager::getInstance()->addRule(
                'register-tick-reboot',
                $cron_expression,
                function() use($waitTime) {
                    $this->reboot($waitTime);
                },
                CrontabManager::loopChannelType,
                $tickSecond * 1000);
        }

    }

    /**
     * 强制退出时，需要清理reboot的定时器
     * clearRebootTimer
     * @return void
     */
    public function clearRebootTimer() {
        if($this->is_reboot) $this->is_reboot = false;
        if(isset($this->reboot_timer_id) && !empty($this->reboot_timer_id)) {
            \Swoole\Timer::clear($this->reboot_timer_id);
        }
    }

    /**
     * isForceExit
     * @return boolean
     */
    public function isForceExit() {
        return $this->is_force_exit;
    }

    /**
     * @param $pid
     * @param $signal
     * @return void
     */
    public function kill($pid, $signal) {
        if(Process::kill($pid, 0)){
            Process::kill($pid, $signal);
        }
    }

    /**
     * isMasterLive
     * @return bool
     */
    public function isMasterLive() {
        if($this->master_pid) {
            if(Process::kill($this->master_pid, 0)) {
                return true;
            }else {
                return false;
            }
        }
    }

    /**
     * worker0会定时设置master_pid在文件，防止误删该文件后找不到master_pid
     * @param int $master_pid
     * @return void
     */
    protected function saveMasterId(int $master_pid) {
        if($master_pid == $this->master_pid) {
            \Workerfy\Coroutine\GoCoroutine::go(function () use($master_pid) {
                @file_put_contents(PID_FILE, $master_pid);
            });
        }
    }

    /**
     * getCurrentRunCoroutineNum 获取当前进程中正在运行的协程数量，可以通过这个值判断比较，防止协程过多创建，可以设置sleep等待
     * @return int
     */
    public function getCurrentRunCoroutineNum() {
        $coroutine_info = \Swoole\Coroutine::stats();
        return $coroutine_info['coroutine_num'] ?? null;
    }

    /**
     * getCurrentCoroutineLastCid 获取当前进程的协程cid已分配到哪个值，可以根据这个值设置进程reboot,防止cid超出最大数
     * @return int
     */
    public function getCurrentCoroutineLastCid() {
        $coroutine_info = \Swoole\Coroutine::stats();
        return $coroutine_info['coroutine_last_cid'] ?? null;
    }

    /**
     * 对于运行态的协程，还没有执行完的，设置一个再等待时间$re_wait_time
     * @param int $cycle_times 轮询次数
     * @param int $re_wait_time 每次2s轮询
     * @return void
     */
    private function runtimeCoroutineWait(int $cycle_times = 5, int $re_wait_time = 2) {
        if($cycle_times <= 0) {
            $cycle_times = 2;
        }
        while($cycle_times > 0) {
            // 当前运行的coroutine
            $runCoroutineNum = $this->getCurrentRunCoroutineNum();
            // 除了主协程和runtimeCoroutineWait跑在协程中，所以等于2个协程，还有其他协程没唤醒，则再等待
            if($runCoroutineNum > 2) {
                --$cycle_times;
                if(\Swoole\Coroutine::getCid() > 0)
                {
                    \Swoole\Coroutine::sleep($re_wait_time);
                }else
                {
                    sleep($re_wait_time);
                }

            }else {
                break;
            }
        }
    }

    /**
     * @return AbstractProcess
     */
    public static function getProcessInstance() {
        return self::$processInstance;
    }

    /**
     * setUserAndGroup Set unix user and group for current process.
     * @return boolean
     */
    protected function setUserAndGroup() {
        if(!isset($this->user)) {
            return false;
        }
        // Get uid.
        $user_info = posix_getpwnam($this->user);
        if(!$user_info) {
            write_info("【Warning】User {$this->user} not exist");
            $this->exit();
            return false;
        }
        $uid = $user_info['uid'];
        // Get gid.
        if($this->group) {
            $group_info = posix_getgrnam($this->group);
            if(!$group_info) {
                write_info("【Warning】Group {$this->group} not exist");
                $this->exit();
                return false;
            }
            $gid = $group_info['gid'];
        }else {
            $gid = $user_info['gid'];
            $this->group = $gid;
        }
        // Set uid and gid.
        if($uid !== posix_getuid() || $gid !== posix_getgid()) {
            if(!posix_setgid($gid) || !posix_initgroups($user_info['name'], $gid) || !posix_setuid($uid)) {
                write_info("【Warning】change gid or uid failed");
            }
        }
    }

    /**
     * @return array
     */
    public function getUserAndGroup() {
        return [$this->user, $this->group];
    }

    /**
     * writeStartFormatInfo
     */
    private function writeStartFormatInfo() {
        $process_name = $this->getProcessName();
        $worker_id = $this->getProcessWorkerId();
        if($this->getProcessType() == self::PROCESS_STATIC_TYPE) {
            if($this->getRebootCount() > 0) {
                $process_type = 'static-reboot';
            }else {
                $process_type = self::PROCESS_STATIC_TYPE_NAME;
            }
        }else  {
            $process_type = self::PROCESS_DYNAMIC_TYPE_NAME;
        }
        $pid = $this->getPid();
        $logInfo = "start children_process【{$process_type}】: $process_name@$worker_id started, Pid=$pid";
        write_info($logInfo,'green');

    }

    /**
     * writeStopFormatInfo
     */
    private function writeStopFormatInfo() {
        $process_name = $this->getProcessName();
        $worker_id = $this->getProcessWorkerId();
        if($this->getProcessType() == self::PROCESS_STATIC_TYPE) {
            $process_type = self::PROCESS_STATIC_TYPE_NAME;
        }else {
            $process_type = self::PROCESS_DYNAMIC_TYPE_NAME;
        }
        $pid = $this->getPid();
        $logInfo = "stop children_process【{$process_type}】: $process_name@$worker_id stopped, Pid=$pid";
        write_info($logInfo,'red');
    }

    /**
     * writeReloadFormatInfo
     */
    private function writeReloadFormatInfo() {
        if($this->getProcessType() == self::PROCESS_DYNAMIC_TYPE) {
            $process_name = $this->getProcessName();
            $worker_id = $this->getProcessWorkerId();
            $process_type = self::PROCESS_DYNAMIC_TYPE_NAME;
            $pid = $this->getPid();
            $logInfo = "start children_process【{$process_type}】: $process_name@$worker_id start(默认动态创建的进程不支持reload，可以使用 kill -10 pid 强制重启), Pid=$pid";
            write_info($logInfo,'red');
        }
    }

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public abstract function run();

    /**
     * @param mixed $msg
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param bool $is_proxy_by_master
     */
    public function onPipeMsg($msg, string $from_process_name, int $from_process_worker_id, bool $is_proxy_by_master) {}

    /**
     * onShutDown
     * @return void
     */
    public function onShutDown() {}

    /**
     * onHandleException
     * @param  $throwable
     * @return void
     */
    public function onHandleException(\Throwable $throwable) {
        $logger = \Workerfy\Log\LogManager::getInstance()->getLogger(\Workerfy\Log\LogManager::RUNTIME_ERROR_TYPE);
        $logger->error(sprintf("%s on File %s on Line %d on trace %s", $throwable->getMessage(), $throwable->getFile(), $throwable->getLine(), $throwable->getTraceAsString()));
    }

}