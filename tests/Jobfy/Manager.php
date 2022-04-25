<?php

namespace Workerfy\Tests\Jobfy;

class Manager extends \Workerfy\ProcessManager
{

    /**
     * @param array $conf
     *
     */
    public function loadConf(array $conf)
    {
        foreach($conf['worker_conf'] ?? [] as $aliasQueueName=>$config)
        {
            $processName = $config['process_name'];
            $processClass = $config['handler'];
            $processWorkerNum = $config['worker_num'] ?? 1;
            $async = true;
            $args = [
                'alias_queue_name'       => $aliasQueueName,
                'max_handle'             => $config['$config'] ?? null,
                'dynamic_queue_create_backlog'  => $config['dynamic_queue_backlog'] ?? null,
                'dynamic_queue_destroy_backlog' => $config['dynamic_queue_destroy_backlog'] ?? null,
                'dynamic_queue_worker_num' => $config['dynamic_queue_worker_num'] ?? null
            ];
            $extendData = $config['extend_data'] ?? [];
            $enableCoroutine = true;
            $this->addProcess($processName, $processClass, $processWorkerNum, $async, $args, $extendData, $enableCoroutine);
        }

        return $this;
    }
}