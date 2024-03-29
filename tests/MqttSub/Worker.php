<?php
namespace Workerfy\Tests\MqttSub;

use Simps\MQTT\Client;
use Simps\MQTT\Config\ClientConfig;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        $host = '127.0.0.1';
        $port = 1883;
        $config = new ClientConfig();
        $config->setUserName('bingcool')
            ->setPassword('123456#@')
            ->setClientId(Client::genClientID())
            ->setKeepAlive(10)
            ->setDelay(3000) // 3s
            ->setMaxAttempts(5)
            ->setSwooleConfig([
                'open_mqtt_protocol' => true,
                'package_max_length' => 2 * 1024 * 1024
            ]);


        $client = new Client($host, $port, $config);

        $will = [
            'topic' => 'simps-mqtt/user001/update',
            'qos' => 1,
            'retain' => 0,
            'message' => '' . time(),
        ];

        while (!$client->connect(true, $will)) {
            \Swoole\Coroutine::sleep(3);
            $client->connect(true, $will);
        }

        $topics['simps-mqtt/user001/get'] = 0;
        $topics['simps-mqtt/user001/update'] = 1;
        $timeSincePing = time();
        var_dump('start subscribe');
        $response = $client->subscribe($topics);

        var_dump($response);

        while (true) {
            try {
                $buffer = $client->recv();
                if($buffer !== true) {
                    var_dump($buffer);
                }
            }catch (\Throwable $t) {
                var_dump($t->getMessage().'trace='.$t->getTraceAsString());
                continue;
            }

            if ($buffer && $buffer !== true) {
                $timeSincePing = time();
            }

            $keep_alive = $config->getKeepAlive();

            if (isset($keep_alive) && $timeSincePing < (time() - $keep_alive)) {
                $buffer = $client->ping();
                if ($buffer) {
                    echo 'send ping success' . PHP_EOL;
                    $timeSincePing = time();
                } else {
                    $client->close();
                    break;
                }
            }
        }
    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage()."-----trace:".$throwable->getTraceAsString());
    }

}