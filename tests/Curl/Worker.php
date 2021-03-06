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
       $this->url = 'http://bing:123456@127.0.0.1:9502/index/testJson';
       //$this->url = 'http://www.baidu.com';
    }

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        if($this->getProcessWorkerId() == 0)
        {
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
                    var_dump('GuzzleHttp code='.$response->getStatusCode());
                }catch (\Throwable $e) {
                    var_dump($e->getMessage());
                }
            });
        } else if($this->getProcessWorkerId() == 1)
        {
            go(function ()
            {
                // 协程里面需要捕捉异常，否则会不断重启
                try {
                    $curlClient = new \Workerfy\Library\HttpClient\CurlHttpClient();
                    $curlClient->setOptionArray([
                        CURLOPT_TIMEOUT => 10,
                    ]);

                    $response = $curlClient->get($this->url,['name'=>'bingcool']);

                    $this->parseResponse($response);

                    $code = $curlClient->getCurlErrorCode();

                    $message = $curlClient->getErrorMessage();

                    var_dump('code='.$code,'message='.$message);

                    //var_dump($response->getHeaders());

                    //var_dump($response->getInfo());
                }catch (\Throwable $t)
                {
                    $this->onHandleException($t);
                }

            });
        }else if($this->getProcessWorkerId() == 2)
        {
            \Workerfy\Coroutine\GoCoroutine::go(function ()
            {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://www.baiduw.com");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // TRUE 将curl_exec()获取的信息以字符串返回，而不是直接输出
                curl_setopt($ch, CURLOPT_HEADER, true); // 返回 response header 默认 false 只会获得响应的正文
                $response = curl_exec($ch);
                $info = curl_getinfo($ch); // 获得响应头大小
                var_dump($info);
                curl_close($ch);
            });

        }

    }

    public function parseResponse($response)
    {
        go(function () use($response)
        {
            sleep(3);
            $cid = \Co::getCid();
            //var_dump($response->getDecodeBody());
            //var_dump("cid-$cid-".$response->getInfo('url'));
        });

        //var_dump("sleep sleep");

    }

    public function onHandleException(\Throwable $throwable)
    {
        //parent::onHandleException($throwable); // TODO: Change the autogenerated stub
        var_dump($throwable->getMessage());
    }
}