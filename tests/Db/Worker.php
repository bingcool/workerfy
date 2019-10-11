<?php
namespace Workerfy\Tests\Db;

use Workerfy\ProcessManager;
use PDO;

class Worker extends \Workerfy\AbstractProcess {

	public function run() {
		$db = \Workerfy\Tests\Db::getMasterMysql();
		while (1) {
            $query = $db->query("select * from user limit 100");
            $res = $query->fetchAll(\PDO::FETCH_ASSOC);  //获取结果集中的所有数据
            var_dump($res);
            sleep(3);
        }
	}
}