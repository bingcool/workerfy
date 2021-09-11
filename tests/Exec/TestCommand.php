<?php

$pid = getmypid();
file_put_contents('./test.log','pid='.$pid.', bingcool-'.rand(1,1000));
sleep(2);
var_dump('hello');
