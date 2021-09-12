<?php
namespace Workerfy\Tests\DirectPipe;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        sleep(1);
        $process_name = $this->getProcessName().'@'.$this->getProcessWorkerId();

        $worker_id = $this->getProcessWorkerId();

        /**
         * 静态没有重启过的子进程可以相互通信，一旦重启过，则无法通信，因为之前重启的进程无法从父进程中在复制到新进程的底层IO监听
         * 建议通过父进程代理通信，获取通过队列争抢通信，或者table共享内存通讯
         */
        if($worker_id == 0) {
            // 向进程发送消息
            var_dump($process_name."子进程开始向父进程发信息.....");
            sleep(1);
            //$this->writeToWorkerByMasterProxy($this->getProcessName(),'hello worker1', 1);
            $this->writeByProcessName($this->getProcessName(),'first send hello worker1', 1);
            sleep(5);
            //$this->writeToWorkerByMasterProxy($this->getProcessName(),'hello worker1', 1);
            $this->writeByProcessName($this->getProcessName(),'second send hello worker1', 1);


        }

        if($worker_id == 1) {
            sleep(2);
            // 重启后再次受到worker0的信息
            $this->reboot();
        }

    }

    /**
     * 进程接收父进程发送的信息，包括代理其他子进程的信息
     * @param string $msg
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param bool $is_proxy_by_master
     */
    public function onPipeMsg($msg, string $from_process_name, int $from_process_worker_id, bool $is_proxy_by_master)
    {
        var_dump('子进程 '.$this->getProcessName().'@'.$this->getProcessWorkerId().' 收到进程 '.$from_process_name.'@'.$from_process_worker_id.' 回复的msg : '.$msg);
    }
}