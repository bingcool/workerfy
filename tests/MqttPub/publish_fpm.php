<?php
namespace Workerfy\Tests\MqttPub;

use Simps\MQTT\Client;
use Simps\MQTT\Config\ClientConfig;
use Swoole\Coroutine;

$host = '127.0.0.1';
$port = 1883;
$config = new ClientConfig();
$config->setUserName('')
    ->setPassword('')
    ->setClientId(Client::genClientID())
    ->setKeepAlive(10)
    ->setDelay(3000) // 3s
    ->setMaxAttempts(5)
    ->setSwooleConfig([
        'open_mqtt_protocol' => true,
        'package_max_length' => 2 * 1024 * 1024
    ]);

$client = new Client($host, $port, $config);

$response = $client->connect();
while (!$response) {
    Coroutine::sleep(3);
    $client->connect();
}
$response = $client->publish('simps-mqtt/user001/update', '{"time":'. time() .'}', 1,0,1);
var_dump($response);