<?php
date_default_timezone_set('Asia/Shanghai');
define("START_SCRIPT_FILE", $_SERVER['PWD'].'/'.$_SERVER['SCRIPT_FILENAME']);

$currentDir = array_pop(explode('/', pathinfo(START_SCRIPT_FILE, PATHINFO_DIRNAME)));
define("PID_FILE_ROOT", '/tmp/workerfy/log/'.$currentDir);
define("PID_FILE", PID_FILE_ROOT.'/'.pathinfo(START_SCRIPT_FILE,PATHINFO_FILENAME).'.pid');
define('APP_ROOT', dirname(__DIR__));

include APP_ROOT . "/vendor/autoload.php";
$configFilePath = __DIR__."/Config/config.php";
// load config
\Workerfy\ConfigLoad::getInstance()->loadConfig($configFilePath);