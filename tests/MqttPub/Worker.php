<?php
namespace Workerfy\Tests\MqttPub;

use Simps\MQTT\Client;
use Swoole\Coroutine;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        $config = [
            'host' => '127.0.0.1',
            'port' => 1884,
            'time_out' => 5,
            'user_name' => 'user001',
            'password' => 'hLXQ9ubnZGzkzf',
            'client_id' => Client::genClientID(),
            'keep_alive' => 20,
        ];

        $client = new Client($config, ['open_mqtt_protocol' => true, 'package_max_length' => 2 * 1024 * 1024]);

        $response = $client->connect();
        while (!$response) {
            Coroutine::sleep(3);
            $client->connect();
        }
        $response = $client->publish('simps-mqtt/user001/update', '{"time":'. time() .'}', 1,0,1);
        var_dump($response);
        $start = time();

        while ( time() <= $start + 5) {
            try {
                $response = $client->publish('simps-mqtt/user001/update', '{"time":'. time() .'}', 1,0,1);
                //var_dump($response);
                sleep(1);
            }catch (\Exception $e) {
                //var_dump($e->getCode(), $e->getMessage());
            }
        }

    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        //var_dump("shutdown--");
    }

//    public function __destruct()
//    {
//        var_dump("destruct");
//    }
}