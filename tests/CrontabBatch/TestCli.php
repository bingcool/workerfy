<?php
\Swoole\Coroutine\run(function () {
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

// todo 模拟业务处理
    sleep(2);

    if(getenv('type') == 'proc') {
        //echo json_encode($_SERVER['argv'])."\r\n";
        echo 'cid='.\Co::getCid()."\r\n";
    }else
    {
        echo 'exec return';
    }
});

