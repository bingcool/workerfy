<?php
namespace Workerfy\Tests\Es;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Workerfy\Command\CommandRunner;

class Worker extends \Workerfy\AbstractProcess {

    /**
     * @var Client
     */
    public $client;

    /**
     * @var array
     * @see https://www.yht7.com/news/107724
     */
    public $hosts = ['127.0.0.1:9200'];

    public function init()
    {
        $this->client = ClientBuilder::create()->setHosts($this->hosts)
            ->build();

    }


    public function run()
    {
        $params = [
            "index" => "users",
            "id"    => 1,
            "routing" => 1,
            "refresh" => true,
            "body"  => [
                "name"     => "张三",
                "age"      => 10,
                "email"    => "zs@gmail.com",
                "birthday" => "1991-12-12",
                "address"  => "北京"
            ]
        ];

        try{
            $result = $this->client->index($params);
            // 删除时不需要传body字段，否则报错
            //$result = $this->client->delete($params);
        }catch (\Throwable $e)
        {
            var_dump($e->getMessage());
        }

        var_dump($result);
    }


    protected function buildIndexAndMapping()
    {
        $params = [
            "index" => "users",
            "body" => [
                // setting
                "settings" => [
                    "number_of_shards" => 2,
                    "number_of_replicas" => 1
                ],

                // mapping
                "mappings" => [
                    "_source" => [
                        "enabled" => true
                    ],
                    '_routing' => [
                        'required' => true,
                    ],
                    "properties" => [
                        "name" => [
                            "type" => "keyword"
                        ],
                        "age" => [
                            "type" => "integer"
                        ],
                        "mobile" => [
                            "type" => "text"
                        ],
                        "email" => [
                            "type" => "text"
                        ],
                        "birthday" => [
                            "type" => "date"
                        ],
                        "address" => [
                            "type" => "text"
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->client->indices()->create($params);
//        array(3) {
//        ["acknowledged"]=>
//  bool(true)
//  ["shards_acknowledged"]=>
//  bool(true)
//  ["index"]=>
//  string(5) "users"
//}
        var_dump($response);
    }
}