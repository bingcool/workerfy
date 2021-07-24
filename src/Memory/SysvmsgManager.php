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

/**
 * 在使用该模块时，必须提前设置这些值达到一定的值
/proc/sys/kernel/msgmax　　　 单个消息的最大值　　　　  缺省值为 8192
/proc/sys/kernel/msgmnb  　　 单个消息队列的容量的最大值　缺省值为 16384
/proc/sys/kernel/msgmni  　　 消息体的数量　　　　　　　缺省值为　16
可通过下面的方式进行设置
echo 819200 > /proc/sys/kernel/msgmax
echo 1638400 > /proc/sys/kernel/msgmnb
echo 1600 > /proc/sys/kernel/msgmni

需要注意的是，一般是通过公式 msgmax * msgmni < msgmnb 来设置
 */

/**
 * $msgStat = msg_stat_queue($msgQueue);
    print_r($msgStat);
 打印出如下：
 * msg_perm.uid The uid of the owner of the queue.
 * msg_perm.gid The gid of the owner of the queue.
 * msg_perm.mode The file access mode of the queue.
 * msg_stime The time that the last message was sent to the queue.
 * msg_rtime The time that the last message was received from the queue.
 * msg_ctime The time that the queue was last changed.
 * msg_qnum The number of messages waiting to be read from the queue.
 * msg_qbytes The maximum number of bytes allowed in one message queue. On Linux, this value may be read and modified via /proc/sys/kernel/msgmnb.
 * msg_lspid The pid of the process that sent the last message to the queue.
 * msg_lrpid The pid of the process that received the last message from the queue.
 */

namespace Workerfy\Memory;

class SysvmsgManager {

    use \Workerfy\Traits\SingletonTrait;

    /**
     * @var array
     */
    private $msgQueues = [];

    /**
     * @var array
     */
    private $msgNameMapQueue = [];

    /**
     * @var array
     */
    private $msgProject = [];

    /**
     * @var array
     */
    private $msgTypes = [];

    /**
     * 读取sys_kernel
     * @var array
     */
    private $sysKernelInfo = [];

    /**
     * 队列最大容量
     * @var integer
     */
    private $sys_kernel_msgmnb;

    /**
     * 默认公共消息类型
     */
    const COMMON_MSG_TYPE = 1;

    /**
     * addMsgFtok 创建msgqueue实例
     * @param string $msg_queue_name
     * @param string $path_name
     * @param string $project
     * @return bool
     * @throws Exception
     */
    public function addMsgFtok(string $msg_queue_name, string $path_name, string $project)
    {
        $isSuccess = true;
        if(!extension_loaded('sysvmsg'))
        {
            $errorMsg = sprintf("【Warning】%s::%s missing sysvmsg extension",
                __CLASS__,
                __FUNCTION__
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }

        if(strlen($project) !=1)
        {
            $errorMsg = sprintf("【Warning】%s::%s. the params of project require only one string charset",
                __CLASS__,
                __FUNCTION__
            );
            write_info($errorMsg);
            $isSuccess = false;
        }

        $msg_queue_name_key = md5($msg_queue_name);
        $path_name_key = md5($path_name);
        if(!isset($this->msgProject[$path_name_key][$project]))
        {
            $this->msgProject[$path_name_key][$project] = $project;
        }else
        {
            $errorMsg = sprintf("【Warning】%s::%s. the params of project is had setting",
                __CLASS__,
                __FUNCTION__
            );
            write_info($errorMsg);
            $isSuccess = false;
        }

        $msg_key = ftok($path_name, $project);
        if($msg_key < 0)
        {
            $errorMsg = sprintf("【Warning】%s::%s create msg_key failed",
                __CLASS__,
                __FUNCTION__
            );
            write_info($errorMsg);
            $isSuccess = false;
        }

        if(!$isSuccess)
        {
            throw new \Exception("【Warning】create msg_queue_name={$msg_queue_name} of sysvmsg failed");
        }

        $msg_queue = msg_get_queue($msg_key,0666);
        $this->msgNameMapQueue[$msg_queue_name_key] = $msg_queue_name;

        if(is_resource($msg_queue) && msg_queue_exists($msg_key))
        {
            $this->msgQueues[$msg_queue_name_key] = $msg_queue;
            defined('ENABLE_WORKERFY_SYSVMSG_MSG') or define('ENABLE_WORKERFY_SYSVMSG_MSG', 1);
        }
        return true;
    }

    /**
     * getSysKernelInfo 读取系统底层设置信息
     * @param bool $force
     * @return array
     */
    public function getSysKernelInfo(bool $force = false)
    {
        if(isset($this->sysKernelInfo) && !empty($this->sysKernelInfo) && !$force)
        {
            return $this->sysKernelInfo;
        }
        // 单个消息体最大限制，单位字节
        $msg_max = @file_get_contents("/proc/sys/kernel/msgmax");
        // 队列的最大容量限制，单位字节
        $msgmnb = @file_get_contents("/proc/sys/kernel/msgmnb");
        // 队列能存消息体的最大的数量个数
        $msgmni = @file_get_contents("/proc/sys/kernel/msgmni");
        $this->sysKernelInfo = ['msgmax'=>(int)$msg_max,'msgmnb'=>(int)$msgmnb,'msgmni'=>(int)$msgmni];
        return $this->sysKernelInfo;
    }

    /**
     * registerMsgType 注册消息类型
     * @param string $msg_queue_name
     * @param string $msg_type_name
     * @param int $msg_type
     * @return bool
     * @throws Exception
     */
    public function registerMsgType(
        string $msg_queue_name,
        string $msg_type_name,
        int $msg_type = 1
    ) {
        if($msg_type <=0)
        {
            $errorMsg = sprintf("【Warning】%s::%s third param of msg_flag_num need to > 0",
                __CLASS__,
                __FUNCTION__
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }

        $msg_queue_name_key = md5($msg_queue_name);
        $msg_type_name_key = md5($msg_type_name);
        if(isset($this->msgTypes[$msg_queue_name_key][$msg_type_name_key]))
        {
            $errorMsg = sprintf("【Warning】%s::%s second params of msg_type_name=%s had setting",
                __CLASS__,
                __FUNCTION__,
                $msg_type_name
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }

        if(isset($this->msgTypes[$msg_queue_name_key]))
        {
            $register_msg_types = array_values($this->msgTypes[$msg_queue_name_key]);
            if(!in_array($msg_type, $register_msg_types))
            {
                $this->msgTypes[$msg_queue_name_key][$msg_type_name_key] = $msg_type;
                return true;
            }
        }else
        {
            $this->msgTypes[$msg_queue_name_key][$msg_type_name_key] = $msg_type;
            return true;
        }
    }

    /**
     * push 向队列发送信息
     * @param string $msg_queue_name
     * @param $msg
     * @param string|null $msg_type_name
     * @return bool
     * @throws Exception
     */
    public function push(string $msg_queue_name, $msg, ?string $msg_type_name = null)
    {
        $msg_queue_name_key = md5($msg_queue_name);
        if(!isset($this->msgQueues[$msg_queue_name_key]))
        {
            $errorMsg = sprintf("【Warning】%s::%s queue=%s is not exist",
                __CLASS__,
                __FUNCTION__,
                $msg_queue_name
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }

        $msg_type = self::COMMON_MSG_TYPE;
        if($msg_type_name)
        {
            $msg_type_name_key = md5($msg_type_name);
            if(isset($this->msgTypes[$msg_queue_name_key][$msg_type_name_key]))
            {
                $msg_type = $this->msgTypes[$msg_queue_name_key][$msg_type_name_key];
            }else
            {
                $errorMsg = sprintf("【Warning】%s::%s msg type=%s is not exist",
                    __CLASS__,
                    __FUNCTION__,
                    $msg_type_name
                );
                write_info($errorMsg);
                throw new \Exception($errorMsg);
            }
        }

        $msg_queue = $this->msgQueues[$msg_queue_name_key];
        $res = msg_send($msg_queue, $msg_type, $msg, $serialize = true, $blocking = false, $errorCode);
        if($res === false)
        {
            $errorMsg = sprintf("【Warning】%s::%s msg_send error, error code=%d",
                __CLASS__,
                __FUNCTION__,
                $errorCode);
            write_info($errorMsg);
            return false;
        }
        return true;
    }

    /**
     * msgRecive 消息接收
     * @param string $msg_queue_name
     * @param string|null $msg_type_name
     * @param int $max_size
     * @return mixed
     * @throws Exception
     */
    public function pop(
        string $msg_queue_name,
        ?string $msg_type_name = null,
        int $max_size = 65535
    ) {
        $msg_queue_name_key = md5($msg_queue_name);
        if(!isset($this->msgQueues[$msg_queue_name_key]))
        {
            $errorMsg = sprintf("【Warning】%s::%s queue=%s is not exist",
                __CLASS__,
                __FUNCTION__,
                $msg_queue_name
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }

        if($msg_type_name)
        {
            $msg_type_name_key = md5($msg_type_name);
            if(isset($this->msgTypes[$msg_queue_name_key][$msg_type_name_key]))
            {
                $msg_type_flag_num = $this->msgTypes[$msg_queue_name_key][$msg_type_name_key];
            }else
            {
                $errorMsg = sprintf("【Warning】%s::%s msg type=%s is not exist",
                    __CLASS__,
                    __FUNCTION__,
                    $msg_type_name
                );
                write_info($errorMsg);
                throw new \Exception($errorMsg);
            }
        }else
        {
            $msg_type_flag_num = self::COMMON_MSG_TYPE;
        }

        $msg_queue = $this->msgQueues[$msg_queue_name_key];
        $res = msg_receive($msg_queue, $msg_type_flag_num, $msg_type, $max_size, $msg, true, 0, $errorCode);
        if($res === false)
        {
            $errorMsg = sprintf("【Warning】%s::%s. msg_receive() accept msg error, code=%d",
                __CLASS__,
                __FUNCTION__,
                $errorCode
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }
        return $msg;
    }

    /**
     * getMsgQueue 获取队列实例
     * @param string $msg_queue_name
     * @return mixed
     * @throws Exception
     */
    public function getMsgQueue(string $msg_queue_name)
    {
        $msg_queue_name_key = md5($msg_queue_name);
        if(!isset($this->msgQueues[$msg_queue_name_key]))
        {
            $errorMsg = sprintf("【Warning】%s::%s. queue msg=%s is not exist",
                __CLASS__,
                __FUNCTION__,
                $msg_queue_name
            );
            write_info($errorMsg);
            throw new \Exception($errorMsg);
        }
        return $msg_queue = $this->msgQueues[$msg_queue_name_key];
    }

    /**
     * getMsgType 获取注册的类型，默认是1，公共类型
     * @param string $msg_queue_name
     * @param string|null $msg_type_name
     * @return int|mixed
     * @throws Exception
     */
    public function getMsgType(string $msg_queue_name, ?string $msg_type_name = null)
    {
        $msg_queue_name_key = md5($msg_queue_name);
        $msg_type = self::COMMON_MSG_TYPE;
        if($msg_type_name)
        {
            $msg_type_name_key = md5($msg_type_name);
            if(isset($this->msgTypes[$msg_queue_name_key][$msg_type_name_key]))
            {
                $msg_type = $this->msgTypes[$msg_queue_name_key][$msg_type_name_key];
            }else
            {
                $errorMsg = sprintf("【Warning】s%::s% msg type=s% is not exist",
                    __CLASS__,
                    __FUNCTION__,
                    $msg_queue_name
                );
                write_info($errorMsg);
                throw new \Exception($errorMsg);
            }
        }
        return $msg_type;
    }

    /**
     * getMsgQueueWaitToPopNum 获取队列里面待读取消息体数量
     * @param string $msg_queue_name
     * @return mixed
     * @throws Exception
     */
    public function getMsgQueueWaitToPopNum(string $msg_queue_name)
    {
        $msg_queue = $this->getMsgQueue($msg_queue_name);
        $status = msg_stat_queue($msg_queue);
        if(!isset($this->sys_kernel_msgmnb))
        {
            if(isset($status['msg_qbytes']))
            {
                $this->sys_kernel_msgmnb = $status['msg_qbytes'];
            }
        }
        return $status['msg_qnum'];
    }

    /**
     * 队列容量大小，单位字节
     * @param string $msg_queue_name
     * @return mixed
     * @throws Exception
     */
    public function getMsgQueueSize(string $msg_queue_name)
    {
        if(isset($this->sys_kernel_msgmnb))
        {
            return $this->sys_kernel_msgmnb;
        }
        $msg_queue = $this->getMsgQueue($msg_queue_name);
        $status = msg_stat_queue($msg_queue);
        if(isset($status['msg_qbytes']))
        {
            $this->sys_kernel_msgmnb = $status['msg_qbytes'];
        }
        return $this->sys_kernel_msgmnb;
    }

    /**
     * @param string|null $msg_queue_name
     * @return bool
     * @throws \Exception
     */
    public function destroyMsgQueue(string $msg_queue_name = null)
    {
        if($msg_queue_name)
        {
            $msg_queue = $this->getMsgQueue($msg_queue_name);
            is_resource($msg_queue) && msg_remove_queue($msg_queue);
            return true;
        }
        // remove all
        if(!empty($this->msgQueues))
        {
            foreach($this->msgQueues as $msg_queue)
            {
                // 存在数据，不应该强制删除
                if(is_resource($msg_queue))
                {
                    $status = msg_stat_queue($msg_queue);
                    if($status['msg_qnum'] == 0)
                    {
                        msg_remove_queue($msg_queue);
                    }
                }
            }
        }
    }

    /**
     * getAllMsgQueueWaitToPopNum
     * @return array
     */
    public function getAllMsgQueueWaitToPopNum()
    {
        $result = [];
        foreach($this->msgQueues as $key=>$msg_queue)
        {
            if(is_resource($msg_queue))
            {
                $status = msg_stat_queue($msg_queue);
                $wait_to_read_num = $status['msg_qnum'];
                if($msg_queue_name = $this->msgNameMapQueue[$key])
                {
                    $result[] = [$msg_queue_name, $wait_to_read_num];
                }
            }
        }
        return $result;
    }
}