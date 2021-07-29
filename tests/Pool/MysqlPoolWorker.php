<?php
namespace Workerfy\Tests\Pool;

use Workerfy\Tests\Make;

class MysqlPoolWorker extends \Workerfy\AbstractProcess
{
    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        $pool = new \Common\Library\Pool\MysqlPool(function () {
            return Make::makeMysql();
        });

        while (1)
        {
            /**
             * @var \Common\Library\Db\Mysql $db
             */
            try {
                $db = $pool->get();
                $db->query('select 1');
            }catch (\Throwable $e)
            {
                $this->onHandleException($e);
            } finally {
                $pool->put($db);
            }
        }
    }
}