<?php
define('USER_NAME', 'bingcool');
define('PASSWORD', '123456');
define('SUPERVISOR_INCLUDE_PATH', '/Users/bingcool/wwwroot/workerfy/tests');

$process_name = 'test1';

$s = new Co\Scheduler();

$s->add(function () use($process_name) {
    $result = \Co::exec('supervisorctl -u '.USER_NAME.' -p '.PASSWORD.' reread');

    //$result = \Co::exec('supervisorctl -u '.USER_NAME.' -p '.PASSWORD.' remove '.$process_name);
    var_dump($result);
    if(is_array($result) && $result['code'] == 0) {

    }

});

$s->start();


//$files = scandir(SUPERVISOR_INCLUDE_PATH);
//
//$ini_files = [];
//
//foreach ($files as $f) {
//
//    if($f == '.' || $f == '..') {
//        continue;
//    }
//    $path = trim(SUPERVISOR_INCLUDE_PATH,'/') . '/' . $f;
//    if(is_dir($path)) {
//        continue;
//    }
//
//    $file_info = pathinfo($path);
//    $file_extension = $file_info['extension'];
//    $file_basename = $file_info['basename'];
//    $file_name = $file_info['filename'];
//    if($file_extension == 'ini') {
//        $file_content = file_get_contents($path);
//        $ini_files[$file_name] = [
//            'file_name' => $file_name,
//            'file_basename' => $file_basename,
//            'file_content' => $file_content
//        ];
//    }
//}
//
//var_dump($ini_files);