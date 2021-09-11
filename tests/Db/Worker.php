<?php
namespace Workerfy\Tests\Db;

use Workerfy\ProcessManager;
use PDO;

class Worker extends \Workerfy\AbstractProcess {

	public function run() {
	    if($this->getProcessWorkerId() == 0) {
            $db = \Workerfy\Tests\Make::makeCommonDb();
            $db->createCommand("insert into tbl_order (`order_id`,`user_id`,`order_amount`,`order_product_ids`,`order_status`) values(:order_id,:user_id,:order_amount,:order_product_ids,:order_status)" )
                ->insert([
                    ':order_id' => time() + 5,
                    ':user_id' => 10000,
                    ':order_amount' => 105,
                    ':order_product_ids' => json_encode([1,2,3,rand(1,1000)]),
                    ':order_status' => 1
                ]);

	        while (true)
            {
                // 限制消费速度，特别是在请求第三方数据的时候
                if($this->getCurrentRunCoroutineNum() > 10)
                {
                    \Co::sleep(1);
                }

                go(function () {
                    $db = \Workerfy\Tests\Make::makeCommonDb();
                    $res = $db->query("select * from `tbl_order` limit 1");
                    //var_dump($this->getCurrentRunCoroutineNum());
                });

                \Co::sleep(0.1);
            }

	        //var_dump("yes");
            //$this->reboot();
        }

	}

	public function onHandleException(\Throwable $throwable, array $context = [])
    {
        parent::onHandleException($throwable); // TODO: Change the autogenerated stub
        var_dump($throwable->getMessage());
    }
}