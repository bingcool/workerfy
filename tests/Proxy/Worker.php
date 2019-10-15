<?php
namespace Workerfy\Tests\Proxy;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        sleep(1);

        // 子进程worker_id = 0 向worker_id = 1 子进程发送代理信息，请求父进程代理
        if($this->getProcessWorkerId() == 0) {
            var_dump("子进程开始向父进程发送代理转发信息.....");
            $this->writeToWorkerByMasterProxy($this->getProcessName(), ['hello test-proxy@1, 我是子进程'.$this->getProcessName().'@'.$this->getProcessWorkerId()], 1);
        }
    }

    /**
     * 进程接收父进程发送的信息，包括代理其他子进程的信息
     * @param array $msg
     * @param string $from_process_name
     * @param int $from_process_worker_id
     * @param bool $is_proxy_by_master
     */
    public function onPipeMsg($msg, string $from_process_name, int $from_process_worker_id, bool $is_proxy_by_master)
    {
        var_dump($this->getProcessName().'@'.$this->getProcessWorkerId().' 收到来自了子进程 '.$from_process_name.'@'.$from_process_worker_id.' 请求父进程代理转发的msg : '.$msg);
    }
}