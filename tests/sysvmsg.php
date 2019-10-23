<?php

// 生成一个消息队列的key

// 生成一个消息队列的key
var_dump(__FILE__);
$msgKey = ftok(__FILE__,'w');
var_dump($msgKey);
/**
msg_get_queue() returns an id that can be used to access the System V message
queue with the given {key}. The first call creates the message queue with the
optional {perms}. A second call to msg_get_queue() for the same {key} will
return a different message queue identifier, but both identifiers access the
same underlying message queue.
 */
// 产生一个消息队列
$msgQueue = msg_get_queue($msgKey,0666);

// 检查一个队列是否存在
$status = msg_queue_exists($msgKey);
var_dump($status);

// 查看当前消息的一些详细信息
/**
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
 *
 */
$msgStat = msg_stat_queue($msgQueue);
print_r($msgStat);

// 把数据加入消息队列,默认数据会被序列化
msg_send($msgQueue,1,'hahha,1');
msg_send($msgQueue,2,'ooooo,2');
msg_send($msgQueue,1,'xxxxx,3');

// 从消息队列中读取一条消息

msg_receive($msgQueue,1, $message_type1, 1024, $message1);
msg_receive($msgQueue,1, $message_type2, 1024, $message2);
//msg_receive($msgQueue,1, $message_type, 1024, $message3,true,MSG_IPC_NOWAIT);
msg_receive($msgQueue,2, $message_type3, 1024, $message3);
$msgStat = msg_stat_queue($msgQueue);
print_r($msgStat);

msg_remove_queue($msgQueue);
echo $message1.PHP_EOL;
echo $message2.PHP_EOL;
echo $message3.PHP_EOL;

echo $message_type1.PHP_EOL;
echo $message_type2.PHP_EOL;
echo $message_type3.PHP_EOL;
