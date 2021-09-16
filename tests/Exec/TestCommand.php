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

$pid = getmypid();
file_put_contents('./test.log','pid='.$pid.', bingcool-'.rand(1,1000));
sleep(2);

if(getenv('type') == 'proc') {
    echo json_encode($_SERVER['argv']);
    echo "bingcool";
}else
{
    echo 'exec return';
}


