<?php
namespace Workerfy\Tests\Pool;

use Workerfy\Tests\Make;

class CurlPoolWorker extends \Workerfy\AbstractProcess
{
    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        $pool = new \Common\Library\Pool\CurlPool(function () {
            return Make::makeCurl();
        },5);

        while (1)
        {
            go(function () use($pool) {
                try {
                    /**
                     * @var \Common\Library\HttpClient\CurlHttpClient $curlClient
                     */
                    $curlClient = $pool->get();
                    $result = $curlClient->get('https://www.baidu.com');
                    var_dump($result);

                }catch (\Throwable $e)
                {
                    $this->onHandleException($e);
                } finally {
                    $pool->put($curlClient);
                }
            });

            go(function () use($pool) {
                try {
                    /**
                     * @var \Common\Library\HttpClient\CurlHttpClient $curlClient
                     */
                    $curlClient = $pool->get();
                    $curlClient = $pool->get();
                    $result = $curlClient->get('https://www.baidu.com');
                    var_dump($result);
                }catch (\Throwable $e)
                {
                    $this->onHandleException($e);
                } finally {
                    $pool->put($curlClient);
                }
            });

            go(function () use($pool) {
                try {
                    /**
                     * @var \Common\Library\HttpClient\CurlHttpClient $curlClient
                     */
                    $curlClient = $pool->get();
                    $result = $curlClient->get('https://www.baidu.com');
                    var_dump($result);
                }catch (\Throwable $e)
                {
                    $this->onHandleException($e);
                } finally {
                    $pool->put($curlClient);
                }
            });

            sleep(1);
        }
    }
}