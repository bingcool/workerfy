<?php
namespace Workerfy\Tests\DbSleep;

class Worker extends \Workerfy\AbstractProcess {

    public function run() {
        // 模拟处理业务
        //sleep(1);
        //var_dump("子进程 开始 reboot start");
        if($this->getProcessWorkerId() == 0) {
            while (1)
            {
                $db = \Workerfy\Tests\Make::makeMysql();
                $res = $db->query("select sleep(10)");
                var_dump($res);
                $this->reboot();
            }
            $this->reboot(); //可以观察到子进程pid在变化
            //$ids = $this->getCliEnvParam('ids');
//            $db = \Workerfy\Tests\Db::getMasterMysql();
//            $query = $db->query("select * from user limit 2");
//            $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
//            var_dump($res);
//            sleep(1);
            //var_dump($ids);
            //$this->notifyMasterCreateDynamicProcess($this->getProcessName(),1);
            //$this->reboot();
        }

        if($this->getProcessWorkerId() == 1) {
            while (1) {
                $db = \Workerfy\Tests\Make::makeMysql();
                $res = $db->query("select sleep(3)");
                //var_dump($res);
                usleep(100000);
            }
        }
        //var_dump('worker'.$this->getProcessWorkerId());
    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        //var_dump("shutdown--");
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
    }

}