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

    private $msg_queue = [];

    private $msg_name_map_queue = [];

    private $msg_project = [];

    private $msg_type = [];

    private $read_sys_kernel = true;

    //队列最大容量
    private $sys_kernel_msgmnb;

    //读取sys_kernel
    private $sys_kernel_info = [];

    //默认公共消息类型
    const COMMON_MSG_TYPE = 1;

    public function __construct($read_sys_kernel = true) {
        $this->read_sys_kernel = $read_sys_kernel;
    }

    /**
     * addMsgFtok 创建msgqueue实例
     * @param string $msg_queue_name
     * @param string $path_name
     * @param string $project
     * @return bool
     * @throws \Exception
     */
    public function addMsgFtok(string $msg_queue_name, string $path_name, string $project) {
        $is_success = true;
        if(!extension_loaded('sysvmsg')) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__.' missing sysvmsg extension';
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }

        if(strlen($project) !=1 ) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__.' the params of project require string type';
            write_info("-------------- $error_msg --------------");
            $is_success = false;
        }

        $msg_queue_name_key = md5($msg_queue_name);
        $path_name_key = md5($path_name);

        if(!isset($msg_project[$path_name_key][$project])) {
            $msg_project[$path_name_key][$project] = 1;
        }else {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__.' the params of project is had setting';
            write_info("-------------- $error_msg --------------");
            $is_success = false;
        }

        $msg_key = ftok($path_name, $project);

        if($msg_key < 0) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__.' create msg_key failed';
            write_info("-------------- $error_msg --------------");
            $is_success = false;
        }

        if(!$is_success) {
            throw new \Exception("【Warning】create msg_queue_name={$msg_queue_name} of sysvmsg failed");
        }

        $msg_queue = msg_get_queue($msg_key,0666);

        $this->msg_name_map_queue[$msg_queue_name_key] = $msg_queue_name;

        if(is_resource($msg_queue) && msg_queue_exists($msg_key)) {
            $this->msg_queue[$msg_queue_name_key] = $msg_queue;
            defined('ENABLE_WORKERFY_SYSVMSG_MSG') or define('ENABLE_WORKERFY_SYSVMSG_MSG', 1);
        }

        return true;
    }

    /**
     * getSysKernelInfo 读取系统底层设置信息
     * @param bool $is_force
     * @return array
     */
    public function getSysKernelInfo(bool $is_force = false) {
        if(isset($this->sys_kernel_info) && !empty($this->sys_kernel_info)) {
            return $this->sys_kernel_info;
        }
        if($is_force) {
            $read_sys_kernel = true;
        }else {
            $read_sys_kernel = $this->read_sys_kernel;
        }
        if($read_sys_kernel) {
            // 单个消息体最大限制，单位字节
            $msg_max = @file_get_contents("/proc/sys/kernel/msgmax");
            // 队列的最大容量限制，单位字节
            $msgmnb = @file_get_contents("/proc/sys/kernel/msgmnb");
            // 队列能存消息体的最大的数量个数
            $msgmni = @file_get_contents("/proc/sys/kernel/msgmni");
            $this->sys_kernel_info = ['msgmax'=>(int)$msg_max, 'msgmnb'=>(int)$msgmnb, 'msgmni'=>(int)$msgmni];
        }
        return $this->sys_kernel_info;
    }

    /**
     * registerMsgType 注册消息类型
     * @param string $msg_queue_name
     * @param string $msg_type_name
     * @param int $msg_type_flag_num
     * @throws \Exception
     */
    public function registerMsgType(string $msg_queue_name, string $msg_type_name, int $msg_type_flag_num = 1) {
        if($msg_type_flag_num <=0) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__.' 第三个参数msg_flag_num必须大于0';
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }

        $msg_queue_name_key = md5($msg_queue_name);
        $msg_type_name_key = md5($msg_type_name);

        if(isset($this->msg_type[$msg_queue_name_key][$msg_type_name_key])) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 第二个参数msg_type_name={$msg_type_name}已经存在设置";
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }

        if(isset($this->msg_type[$msg_queue_name_key])) {
            $register_msg_flag_num = array_values($this->msg_type[$msg_queue_name_key]);
            if(in_array($msg_type_flag_num, $register_msg_flag_num)) {
                $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 第三个参数msg_type_flag_num={$msg_type_flag_num}已经存在设置,不要重复设置";
                write_info("-------------- $error_msg --------------");
                throw new \Exception($error_msg);
            }
        }

        $this->msg_type[$msg_queue_name_key][$msg_type_name_key] = $msg_type_flag_num;

    }

    /**
     * push 向队列发送信息
     * @param string $msg_queue_name
     * @param $msg
     * @param string|null $msg_type_name
     * @return bool
     * @throws \Exception
     */
    public function push(string $msg_queue_name, $msg, string $msg_type_name = null) {
        $msg_queue_name_key = md5($msg_queue_name);
        $msg_type_flag_num = self::COMMON_MSG_TYPE;
        if($msg_type_name) {
            $msg_type_name_key = md5($msg_type_name);
            if(isset($this->msg_type[$msg_queue_name_key][$msg_type_name_key])) {
                $msg_type_flag_num = $this->msg_type[$msg_queue_name_key][$msg_type_name_key];
            }else {
                $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 消息类型={$msg_type_name},不存在";
                write_info("-------------- $error_msg --------------");
                throw new \Exception($error_msg);
            }
        }

        if(!isset($this->msg_queue[$msg_queue_name_key])) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 队列名称：{$msg_queue_name},不存在";
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }

        $msg_queue = $this->msg_queue[$msg_queue_name_key];
        $res = msg_send($msg_queue, $msg_type_flag_num, $msg, $serialize = true, $blocking = false, $errorcode);

        if($res === false) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." msg_send() 发送消息失败，返回错误码：{$errorcode}";
            write_info("-------------- $error_msg --------------");
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
     * @throws \Exception
     */
    public function pop(string $msg_queue_name, string $msg_type_name = null, int $max_size = 65535) {
        $msg_queue_name_key = md5($msg_queue_name);
        if(!isset($this->msg_queue[$msg_queue_name_key])) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 队列名称：{$msg_queue_name},不存在";
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }

        if($msg_type_name) {
            $msg_type_name_key = md5($msg_type_name);
            if(isset($this->msg_type[$msg_queue_name_key][$msg_type_name_key])) {
                $msg_type_flag_num = $this->msg_type[$msg_queue_name_key][$msg_type_name_key];
            }else {
                $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 消息类型={$msg_type_name},不存在";
                write_info("-------------- $error_msg --------------");
                throw new \Exception($error_msg);
            }
        }else {
            $msg_type_flag_num = self::COMMON_MSG_TYPE;
        }

        $msg_queue = $this->msg_queue[$msg_queue_name_key];
        $res = msg_receive($msg_queue, $msg_type_flag_num, $msg_type, $max_size, $msg, true, 0, $errorcode);
        if($res === false) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." msg_receive() 接收消息失败，返回错误码：{$errorcode}";
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }

        return $msg;

    }

    /**
     * getMsgQueue 获取队列实例
     * @param string $msg_queue_name
     * @return mixed
     * @throws \Exception
     */
    public function getMsgQueue(string $msg_queue_name) {
        $msg_queue_name_key = md5($msg_queue_name);
        if(!isset($this->msg_queue[$msg_queue_name_key])) {
            $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 队列名称：{$msg_queue_name},不存在";
            write_info("-------------- $error_msg --------------");
            throw new \Exception($error_msg);
        }
        return $msg_queue = $this->msg_queue[$msg_queue_name_key];
    }

    /**
     * getMsgType 获取注册的类型，默认是1，公共类型
     * @param string $msg_queue_name
     * @param string|null $msg_type_name
     * @return int|mixed
     * @throws \Exception
     */
    public function getMsgType(string $msg_queue_name, string $msg_type_name = null) {
        $msg_queue_name_key = md5($msg_queue_name);
        $msg_type_flag_num = self::COMMON_MSG_TYPE;
        if($msg_type_name) {
            $msg_type_name_key = md5($msg_type_name);
            if(isset($this->msg_type[$msg_queue_name_key][$msg_type_name_key])) {
                $msg_type_flag_num = $this->msg_type[$msg_queue_name_key][$msg_type_name_key];
            }else {
                $error_msg = "【Warning】".__CLASS__.'::'.__FUNCTION__." 消息类型={$msg_type_name},不存在";
                write_info("-------------- $error_msg --------------");
                throw new \Exception($error_msg);
            }
        }
        return $msg_type_flag_num;
    }

    /**
     * getMsgQueueWaitToPopNum 获取队列里面待读取消息体数量
     * @param string $msg_queue_name
     * @return mixed
     * @throws \Exception
     */
    public function getMsgQueueWaitToPopNum(string $msg_queue_name) {
        $msg_queue = $this->getMsgQueue($msg_queue_name);
        $status = msg_stat_queue($msg_queue);
        if(!isset($this->sys_kernel_msgmnb)) {
            if(isset($status['msg_qbytes'])) {
                $this->sys_kernel_msgmnb = $status['msg_qbytes'];
            }
        }
        return $status['msg_qnum'];
    }

    /**
     * 队列容量大小，单位字节
     * @param string $msg_queue_name
     * @return mixed
     * @throws \Exception
     */
    public function getMsgQueueSize(string $msg_queue_name) {
        if(isset($this->sys_kernel_msgmnb)) {
            return $this->sys_kernel_msgmnb;
        }
        $msg_queue = $this->getMsgQueue($msg_queue_name);
        $status = msg_stat_queue($msg_queue);
        if(isset($status['msg_qbytes'])) {
            $this->sys_kernel_msgmnb = $status['msg_qbytes'];
        }
        return $this->sys_kernel_msgmnb;
    }

    /**
     * @param string|null $msg_queue_name
     * @return bool
     * @throws \Exception
     */
    public function destroyMSgQueue(string $msg_queue_name = null) {
        if($msg_queue_name) {
            $msg_queue = $this->getMsgQueue($msg_queue_name);
            is_resource($msg_queue) && msg_remove_queue($msg_queue);
            return true;
        }
        // remove all
        if(!empty($this->msg_queue)) {
            foreach($this->msg_queue as $msg_queue) {
                // 存在数据，不应该强制删除
                if(is_resource($msg_queue)) {
                    $status = msg_stat_queue($msg_queue);
                    if($status['msg_qnum'] == 0) {
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
    public function getAllMsgQueueWaitToPopNum() {
        $result = [];
        foreach($this->msg_queue as $key=>$msg_queue) {
            if(is_resource($msg_queue)) {
                $status = msg_stat_queue($msg_queue);
                $wait_to_read_num = $status['msg_qnum'];
                if($msg_queue_name = $this->msg_name_map_queue[$key]) {
                    $result[] = [$msg_queue_name, $wait_to_read_num];
                }
            }
        }
        return $result;
    }
}