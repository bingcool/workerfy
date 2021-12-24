<?php

var_dump("kkkkkkkkkk");
$execFile = 'php '.__DIR__.'/Exec/TestCommand.php';


$params = [
    '--type=proc',
    '--name=bingcool-'.rand(1,1000)
];


$descriptors = array(
    0=>array('pipe','r'),
    1=>array('pipe','w'),
    2=>array('pipe','w'),
);


$source = proc_open($execFile, $descriptors, $pipes);

$read = [$pipes[1]];
$write = null;
$e = [];

if(is_resource($source)) {
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, 0);
    }
}

$res = stream_select($read, $write, $e, 0, 0);
var_dump($res);

foreach($pipes as $pipe)
{
    fclose($pipe);
}

$time = date('Y-m-d H:i:s');
var_dump('time='.$time);

//proc_close($source);



