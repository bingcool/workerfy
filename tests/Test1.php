<?php
$start_time = time();

$res = \Swoole\Process::daemon();

while(1) {
   sleep(2);

   if(time() - $start_time > 10) {
       var_dump("rand_num:".rand(1,1000));
       return;
   }
}

var_dump($res);
