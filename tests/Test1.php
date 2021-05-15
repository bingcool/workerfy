<?php
//Swoole\Runtime::enableCoroutine();
//
//go(function () {
//    sleep(2);// 相当于休息2s,让出控制权
//    var_dump("go1休息完毕，已唤醒");
//});
//
//go(function () {
//    sleep(1);// 相当于休息1s,让出控制权
//    var_dump("go2休息完毕，已唤醒");
//});
//
//go(function () {
//    sleep(3);// 相当于休息3s，让出控制权
//    var_dump("go3休息完毕，已唤醒");
//});
//
//var_dump('三个go目前正在休息中');
//
//// 主进程将等待协程唤醒
///
/**
 * @param string $str
 * @return bool
 */
function json_validate(string $str) {
    if (is_string($str)) {
        @json_decode($str);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    return false;
}

$str = "hnhhbjbhj";

$res = json_validate($str);

//var_dump($res);

$arr = ['name'=>'bingcool','sex'=>'nan'];

$str_json = json_encode($arr);

list($name, ) = $arr;

$a = 4;

\Swoole\Process::signal(SIGCHLD, function($signo) {
    var_dump($signo);
});

var_dump('pre_pid='.posix_getpid());

\Swoole\Process::daemon(true, false,256);

var_dump('now_pid='.posix_getpid());
