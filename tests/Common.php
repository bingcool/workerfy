<?php
date_default_timezone_set('Asia/Shanghai');
define("START_SCRIPT_FILE", $_SERVER['PWD'].'/'.$_SERVER['SCRIPT_FILENAME']);

$paths = explode('/', pathinfo(START_SCRIPT_FILE, PATHINFO_DIRNAME));
$currentDir = @array_pop($paths);

define("PID_FILE_ROOT", '/tmp/workerfy/log/'.$currentDir);
define("PID_FILE", PID_FILE_ROOT.'/'.pathinfo(START_SCRIPT_FILE,PATHINFO_FILENAME).'.pid');
define('APP_ROOT', dirname(__DIR__));

include APP_ROOT . "/vendor/autoload.php";
$configFilePath = __DIR__."/Config/config.php";
// load config
\Workerfy\ConfigLoader::getInstance()->loadConfig($configFilePath);