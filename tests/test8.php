<?php
define('PID_FILE','lll');

include '../vendor/autoload.php';


$execFile = 'php '.__DIR__.'/Exec/TestCommand.php';



$process = new \Symfony\Component\Process\Process($execFile);

$process->start();

while ($process->isRunning()) {
    // waiting for process to finish / 等待进程完成
}

echo $process->getOutput();
