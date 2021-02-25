<?php
namespace Workerfy\Tests\CoHttp;

class Worker extends \Workerfy\AbstractProcess {

    protected $server;

    public function run() {

        $server = new \Co\Http\Server("*", 9502, false, true);

        $server->handle('/', function ($request, $response) {
            if($request->server['request_uri'] == '/favicon.ico') {
                return $response->end();
            }

            $str ='{"name":"bingcool"}';

            if(json_validate($str, $decodeData))
            {
                var_dump($decodeData);
            }

            $type = $request->get['type'] ?? 0;
            go(function () use($request, $response, $type){
                if($type == 1)
                {
                    \Co\System::sleep(6);
                }

                $cid = \Co::getCid();
                $workerId = $this->getProcessWorkerId();
                var_dump("workerId=".$workerId);
                $response->end("Index-cid=$cid,workerId=$workerId"."-".rand(1,1000));
            });
        });

        $server->handle('/test', function ($request, $response) {
            var_dump("ggggggggggggg");
            $response->end("<h1>Test</h1>");
        });

        $server->handle('/stop', function ($request, $response) use ($server) {
            $response->end("<h1>Stop</h1>");
            $server->shutdown();

            $this->reboot(1);
        });
        
        $server->start();
    }

    public function onShutDown()
    {
        parent::onShutDown(); // TODO: Change the autogenerated stub
        //var_dump("shutdown--");
    }

    public function onHandleException(\Throwable $throwable)
    {
        parent::onHandleException($throwable); // TODO: Change the autogenerated stub
        var_dump($throwable->getMessage());
    }

//    public function __destruct()
//    {
//        var_dump("destruct");
//    }
}