<?php
/**
 * author: NickBai
 * createTime: 2016/12/2 0002 上午 11:12
 */
//创建管道
$pipePath = "/tmp/test.pipe";
if(!file_exists( $pipePath ) ){
    if(!posix_mkfifo($pipePath, 0666)){
        exit('make pipe false!' . PHP_EOL);
    }
}
//创建进程,子进程写管道，父进程读管道
$pid = pcntl_fork();
if( $pid == 0 ){
    //子进程写管道
    $file = fopen( $pipePath, 'w' );
    var_dump($file);
    fwrite( $file, 'hello world' );
    sleep(1);
    exit();
}else{
    //父进程读管道
    $file = fopen( $pipePath, 'r' );
    var_dump($file);
    //stream_set_blocking( $file, False );  //设置成读取非阻塞
    echo fread( $file, 20 ) . PHP_EOL;
    pcntl_wait($status);  //回收子进程
}