<?php
namespace Workerfy\Tests\Pipe;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        sleep(1);
        $options = \Swoole\Coroutine::getOptions();

        var_dump($options);

        $process_name = $this->getProcessName().'@'.$this->getProcessWorkerId();
        // 向父进程发送消息
        var_dump($process_name."子进程开始向父进程发信息.....");
        while (true) {
            if($this->isExiting() || $this->isForceExit() || $this->isRebooting()) {
                sleep(1);
                continue;
            }
            // 发送数组，那么master进程在onPipe中收到会是数组，发送的是字符串，那么收到的将是字符串
            $data = [
                'msg' =>'您好，父进程，我是子进程：'.$this->getProcessName().'@'.$this->getProcessWorkerId()
            ];
            $this->writeToMasterProcess(
                \Workerfy\ProcessManager::MASTER_WORKER_NAME,
                $data
            );
            var_dump("last_cid=".$process_name);
            sleep(3);
        }
    }

    /**
     * 进程接收父进程发送的信息，包括代理其他子进程的信息
     * @param string|array $msg
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param bool $is_proxy_by_master
     */
    public function onPipeMsg($msg, string $from_process_name, int $from_process_worker_id, bool $is_proxy_by_master)
    {
        var_dump($msg);
        //var_dump('子进程 '.$this->getProcessName().'@'.$this->getProcessWorkerId().' 收到父进程 '.$from_process_name.'@'.$from_process_worker_id.' 回复的msg : '.$msg);
    }
}