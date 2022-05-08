<?php

namespace Workerfy\Tests\Jobfy;

use Workerfy\ProcessManager;

class Manager extends ProcessManager
{

    /**
     * @param array $conf
     *
     */
    public function loadConf(array $conf)
    {
        foreach ($conf as $workerType => $workerConfItems)
        {
            switch ($workerType)
            {
                case 'worker_queue_conf':
                    $this->initWorkerQueueConf($workerConfItems);
                    break;
                case 'worker_kafka_conf':
                    $this->initWorkerKafkaConf($workerConfItems);
                    break;
                case 'worker_other_conf':
                    $this->initWorkerOtherConf($workerConfItems);
                    break;
                default:
                    break;
            }

        }


        return $this;
    }

    /**
     * @param array $workerConfItems
     */
    protected function initWorkerQueueConf(array $workerConfItems)
    {
        foreach($workerConfItems ?? [] as $aliasQueueName => $config)
        {
            $processName = $config['process_name'];
            $processClass = $config['handler'];
            $processWorkerNum = $config['worker_num'] ?? 1;
            $async = true;
            $args = $config['args'] ?? [];
            $args['max_handle'] = $config['max_handle'] ?? 10000;
            $args['life_time'] = $config['life_time'] ?? 3600;
            $args['alias_queue_name'] = $aliasQueueName;
            $extendData = $config['extend_data'] ?? [];
            $enableCoroutine = true;
            $this->addProcess($processName, $processClass, $processWorkerNum, $async, $args, $extendData, $enableCoroutine);
        }
    }

    /**
     * @param array $workerConfItems
     */
    protected function initWorkerKafkaConf(array $workerConfItems)
    {

    }

    /**
     * @param array $workerConfItems
     */
    protected function initWorkerOtherConf(array $workerConfItems)
    {
        foreach($workerConfItems ?? [] as $aliasName => $config)
        {
            $processName = $config['process_name'];
            $processClass = $config['handler'];
            $processWorkerNum = $config['worker_num'] ?? 1;
            $async = true;
            $args = $config['args'] ?? [];
            $args['max_handle'] = $config['max_handle'] ?? 10000;
            $args['life_time'] = $config['life_time'] ?? 3600;
            $extendData = $config['extend_data'] ?? [];
            $enableCoroutine = true;
            $this->addProcess($processName, $processClass, $processWorkerNum, $async, $args, $extendData, $enableCoroutine);
        }
    }
}