<?php
Swoole\Runtime::enableCoroutine();

go(function () {
    sleep(2);// 相当于休息2s,让出控制权
    var_dump("go1休息完毕，已唤醒");
});

go(function () {
    sleep(1);// 相当于休息1s,让出控制权
    var_dump("go2休息完毕，已唤醒");
});

go(function () {
    sleep(3);// 相当于休息3s，让出控制权
    var_dump("go3休息完毕，已唤醒");
});

var_dump('三个go目前正在休息中');

// 主进程将等待协程唤醒
