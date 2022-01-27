<?php
namespace Workerfy\Tests\Openssl;

use Workerfy\AbstractProcess;
use GuzzleHttp\Client;
use Yurun\Util\Swoole\Guzzle\SwooleHandler;
use GuzzleHttp\DefaultHandler;

class Worker extends AbstractProcess {

    public $url;

    public function init()
    {
        $this->url = 'http://127.0.0.1:9502/index/testJson';

    }

    /**
     * run 进程创建后的run方法
     * @return void
     */
    public function run()
    {
        //var_dump(openssl_get_cipher_methods());

        //$encryptMethod = 'aes-256-cbc';

        $encryptMethod = 'DES-EDE3-CBC';

        $data = json_encode([
            'name'=>'bingcool',
            'sex' => 1,
            'client_id' => 9,
            'user_id' => 22222,
            'version' => '1.0.0'
        ]);

        $key = 'secret';

        $mdKey = md5($key);

        $ivLength = openssl_cipher_iv_length($encryptMethod);

        var_dump($ivLength);

        $key = substr($mdKey, 0, $ivLength);

        $iv = substr($mdKey, 10, $ivLength);

        go(function () {
            var_dump("go exit");
            $this->exit();
        });

        // 加密
        $encrypted = openssl_encrypt($data, $encryptMethod, 'secret', 0, $iv);


        var_dump($encrypted);

        // 解密
        $decrypted = openssl_decrypt($encrypted, $encryptMethod, 'secret', 0, $iv);

        var_dump(json_decode($decrypted, true));


    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub

        sleep(3);
        var_dump("jjjjjjjjjjjj");
    }

}