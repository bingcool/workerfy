<?php

return [
    'worker_conf' =>
        [
            // 队列名称
            'worker-queue1' => [
                // 进程名
                'process_name' => 'worker-queue1',
                'handler' => \Workerfy\Tests\Jobfy\RedisQueue::class,
                'worker_num' => 2, // 默认动态进程数量
                'max_handle' => 10000, //消费达到10000后reboot进程,
                'dynamic_queue_create_backlog' => 500, //队列达到500积压，则动态创建进程
                'dynamic_queue_destroy_backlog' => 300, //队列少于300积压，则销毁，这个值不设置，则表示是500
                'dynamic_queue_worker_num' => 2, //动态创建的进程数,
                'retry_num' => 2, // 重试次数
                'retry_delay_time' => 5, // 延迟5s后放回主队列重试
                'ttl' => 300, // 超过多少秒没有被消费，就抛弃，0代表永不抛弃
                'extend_data' => [], // 额外数据
                'driver' => 'redis', // 对应config的配置项
            ],

            // 队列名称
            'worker-queue2' => [
                'process_name' => 'worker-queue2',
                'handler' => \Workerfy\Tests\Jobfy\worker1::class,
                'worker_num' => 2,
                'extend_data' => []
            ]
        ]
];