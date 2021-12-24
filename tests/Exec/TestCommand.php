<?php
$argv = $_SERVER['argv'];
foreach($argv as $item)
{
    if(substr($item,0,2) == '--') {
        $env = substr($item,2);
        if(strpos($env,'=') !== false) {
            @putenv($env);
        }
    }
}

sleep(5);

$pid = getmypid();

$time = date('Y-m-d H:i:s');
file_put_contents('./test.log',"time={$time}".',pid='.$pid.', bingcool-'.rand(1,1000));

if(getenv('type') == 'proc') {
    echo json_encode($_SERVER['argv'])."\r\n";
    echo "bingcool";
}else
{
    echo 'exec return';
}


