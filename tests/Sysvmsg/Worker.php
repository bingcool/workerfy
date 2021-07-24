<?php
namespace Workerfy\Tests\Sysvmsg;

use Workerfy\Memory\SysvmsgManager;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        sleep(1);
        $sysvmsgManager = SysvmsgManager::getInstance();
        if($this->getProcessWorkerId() == 0)
        {
            $msg = str_repeat("add_order-test", 10);
            for($i=0;$i<20;$i++) {
                $sysvmsgManager->push(MSG_QUEUE_NAME_ORDER, $msg,'add_order');
            }
            $msgType = $sysvmsgManager->getMsgType(MSG_QUEUE_NAME_ORDER, 'add_order');
            //$this->exit();
            //$sysvmsgManager->msgSend(MSG_QUEUE_NAME_ORDER, 'add_order_event','add_order');
        }else if($this->getProcessWorkerId() == 1)
        {
            // push 默认消息类型
            $msg = str_repeat("default-type-test", 10);
            for($i=0;$i<20;$i++) {
                $sysvmsgManager->push(MSG_QUEUE_NAME_ORDER, $msg);
            }
        }if($this->getProcessWorkerId() == 2)
        {
            // 其他的worker处理逻辑消费队列
            //sleep(5);
            $msg_queue = $sysvmsgManager->getMsgQueue(MSG_QUEUE_NAME_ORDER);
            // 获取系统信息
            //var_dump($sysvmsgManager->getSysKernelInfo(), $sysvmsgManager->getMsgQueueSize(MSG_QUEUE_NAME_ORDER));
            while (1)
            {
                // 获取剩余的未读消息体数量
                $num = $sysvmsgManager->getMsgQueueWaitToPopNum(MSG_QUEUE_NAME_ORDER);
                var_dump($num);

                // 阻塞等待消费
                $msg = $sysvmsgManager->pop(MSG_QUEUE_NAME_ORDER,'add_order');
                var_dump($this->getProcessName().'@'.$this->getProcessWorkerId().":".$msg);
                usleep(50000);

            }
        }
        else if($this->getProcessWorkerId() == 3)
        {
            // 其他的worker处理逻辑消费队列
            //sleep(5);
            // 获取系统信息
            //var_dump($sysvmsgManager->getSysKernelInfo(), $sysvmsgManager->getMsgQueueSize(MSG_QUEUE_NAME_ORDER));
            while (1)
            {
                // 获取剩余的未读消息体数量
                $num = $sysvmsgManager->getMsgQueueWaitToPopNum(MSG_QUEUE_NAME_ORDER);
                var_dump($num);

                // 阻塞等待消费
                $msg = $sysvmsgManager->pop(MSG_QUEUE_NAME_ORDER);
                var_dump($this->getProcessName().'@'.$this->getProcessWorkerId().":".$msg);
                usleep(50000);
            }
        }
    }

}