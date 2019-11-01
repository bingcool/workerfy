<?php
    $user = "bingcool";
    $pass = "bingcool@123456";
    $pdo = new PDO('mysql:host=123.207.19.149;dbname=bingcool', $user, $pass,[
            PDO::ATTR_PERSISTENT => true
        ]);
    $i = 1;
    //while($i){
        $pid = pcntl_fork();
        if($pid<0){
            echo "fork error" . PHP_EOL;
        }elseif($pid == 0){
            var_dump($pdo);
            pcntl_wait($status);
            --$i;
            sleep(3);
            var_dump("parent start query");
            $query = $pdo->query("select * from user limit 1");
            $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
            var_dump($res);
        }else{
            // 子进程
            $user = "bingcool";
            $pass = "bingcool@123456";
            $pdo1 = new PDO('mysql:host=123.207.19.149;dbname=bingcool', $user, $pass,[
                PDO::ATTR_PERSISTENT => false
            ]);
            sleep(1);
            var_dump("process start dump");
            var_dump($pdo1);
            // do something
            //exit;
        }
    //}