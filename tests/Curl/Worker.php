<?php
namespace Workerfy\Tests\Curl;

use Workerfy\AbstractProcess;
use GuzzleHttp\Client;
use Yurun\Util\Swoole\Guzzle\SwooleHandler;
use GuzzleHttp\DefaultHandler;

class Worker extends AbstractProcess {

    public $url;

    public function init()
    {
        parent::init();
        $this->url = 'http://127.0.0.1:9502/index/testJson';

    }

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        //var_dump('start start ......');
//        go(function () {
//            //swoole 4.5开始已经支持curl的协程化，通过使用Swoole\Coroutine\Http\Client模拟实现了curl的API，并在底层替换了curl_init等函数的C Handler
//            $ch = curl_init($this->url);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//            curl_setopt($ch, CURLOPT_HEADER, 0);
//            $output = curl_exec($ch);
//            if ($output === FALSE) {
//                echo "CURL Error:" . curl_error($ch);
//            }
//            curl_close($ch);
//
//            var_dump(json_decode($output, true));
//        });

        go(function () {
            try {
                // 必须设置handler
                DefaultHandler::setDefaultHandler(SwooleHandler::class);
                //原理上GuzzleHttp 底层是使用curl实现的封装，curl已经实现自动协程化，那么GuzzleHttp也就实现协程了，可以直接使用,
                //但是由于GuzzleHttp自身问题，会自动打印出response,所以需要设置handler
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', $this->url);
                //var_dump($response->getStatusCode());
                $res = $response->getBody()->getContents();
                var_dump(json_decode($res, true));
            }catch (\Throwable $e) {
                var_dump($e->getMessage());
            }

        });

        //var_dump('end');

    }
}