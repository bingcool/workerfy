<?php

$dir_config = dirname(__DIR__);

$vendor_path = dirname($dir_config);

include $vendor_path."/vendor/autoload.php";

$config_file_path = $dir_config."/Config/config.php";

\Workerfy\Config::getInstance()->loadConfig($config_file_path);

$processManager = \Workerfy\processManager::getInstance();

$process_name = 'worker1';
$process_class = \Workerfy\Tests\Daemon\Worker1::class;
$process_worker_num = 1;
$async = true;
$args = [];
$extend_data = null;

$processManager->addProcess($process_name, $process_class, $process_worker_num, $async, $args, $extend_data);

$processManager->start();

