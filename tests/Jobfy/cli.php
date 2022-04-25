#!/usr/bin/php
<?php
require dirname(__DIR__).'/Common.php';

use Workerfy\ConfigLoader;
use Workerfy\Tests\Jobfy\Manager;

// load config
$conf = ConfigLoader::getInstance()->loadConfig(__DIR__ . '/queue_conf.php');
// load conf
$manager = Manager::getInstance()->loadConf($conf);
$manager->start();

