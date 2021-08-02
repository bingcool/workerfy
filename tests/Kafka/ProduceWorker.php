<?php
namespace Workerfy\Tests\Kafka;

use Workerfy\AbstractProcess;

class ProduceWorker extends AbstractProcess
{

    /**
     * @inheritDoc
     */
    public function run()
    {
        $metaBrokerList = '127.0.0.1:9092';
        $topicName = 'mykafka';

        $producer = new \Common\Library\Kafka\Producer($metaBrokerList, $topicName);

        // 可以重新设置注入conf
        //$conf = new \RdKafka\Conf();
        //$conf->set('bootstrap.servers', $metaBrokerList);
        //$producer->setConf($conf);

        // 可以重新设置注入topicConf
//        $topicConf = new \RdKafka\TopicConf();
//        $topicConf->set('request.required.acks', 'all');
//        $producer->setTopicConf($topicConf);

        while (true)
        {
            $producer->produce('hello-'.rand(1,1000),5000);
            sleep(1);
        }
    }
}