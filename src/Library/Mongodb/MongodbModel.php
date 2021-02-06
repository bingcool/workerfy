<?php
/**
+----------------------------------------------------------------------
| Daemon and Cli model about php process worker
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
 */

namespace Workerfy\Library\Mongodb;

use MongoDB\Client;

class MongodbModel {
    /**
     * $mongodbClient mongodb的客户端对象
     * @var null
     */
    public $mongodbClient = null;

    /**
     * $database 默认连接的数据库
     * @var null
     */
    public $database = null;

    /**
     * 配置值
     * @var string
     */
    public $uri = 'mongodb=127.0.0.1:27017';
    public $uriOptions = [];
    public $driverOptions = [];

    /**
     * $databaseObject 数据库对象
     * @var null
     */
    protected $databaseObject = null;

    /**
     * $collectionModels 每个collection的操作对象
     * @var array
     */
    protected $collectionModels = [];

    /**
     * _id 将默认设置成id
     * @var string
     */
    public $_id = null;

    /**
     * MongodbModel constructor.
     * @param string $uri
     * @param array $uriOptions
     * @param array $driverOptions
     */
    public function __construct(string $uri = 'mongodb=127.0.0.1:27017', array $uriOptions = [], array $driverOptions=[]) {
        $this->uri = $uri;
        $this->uriOptions = $uriOptions;
        $this->driverOptions = $driverOptions;
    }

    /**
     * setDatabase 
     * @param   string   $db
     * @return  mixed
     */
    public function setDatabase(string $db = null) {
        if($db) {
            return $this->database = $db;
        }
        if(isset($this->database) && is_string($this->database)) {
            return $this->database;
        }

    }

    /**
     * dbInstanc
     * @param    string   $db
     * @return   mixed
     */
    public function dbInstance(string $db = null) {
        if(isset($this->databaseObject) && is_object($this->databaseObject)) {
            return  $this->databaseObject;
        }
        $db = $this->setDatabase($db);
        return $this->databaseObject = $this->mongodbClient->$db;
    }

     /**
     * db 返回数据库对象实例
     * @return mixed
     */
    public function db() {
        if(!is_object($this->mongodbClient)) {
            $this->mongodbClient = new Client($this->uri, $this->uriOptions, $this->driverOptions);
        }
        return $this->dbInstance($db = null);
    }

    /**
     * ping 测试是否能够连接mongodb server
     * @param  boolean $pong 是否返回ping的所有信息
     * @return mixed
     */
    public function ping(bool $pong = false) {
        $cursor = $this->db()->command([
            'ping' => 1,
        ]);
        if($pong) {
            $pong_info = $cursor->toArray();
        }else {
            $pong_info = $cursor->toArray()[0]['ok'];
        }
        return $pong_info;
    }

    /**
     *  collection 创建collection对象
     * @param   string  $collection
     * @return  mixed
     */
    public function collection(string $collection) {
        if(!is_object($this->mongodbClient)) {
            $this->mongodbClient = new Client($this->uri, $this->uriOptions, $this->driverOptions);
        }

        if(isset($this->collectionModels[$collection]) && is_object($this->collectionModels[$collection])) {
            return $this->collectionModels[$collection];
        }

        $databaseObject = $this->dbInstance();
        return $this->collectionModels[$collection] = new MongodbCollection($collection, $this->_id, $databaseObject);
    }

    /**
     * setId
     * @param   string $_id
     * @return  void
     */
    public function setIdKey(string $_id) {
        $this->_id = $_id;
    }

    /**
     * getIdKey 
     * @return string
     */
    public function getIdKey() {
        return $this->_id;
    }

    /**
     * __get 获取collection
     * @param string  $name
     * @return mixed
     */
    public function __get($name) {
        if(is_string($name)) {
            return $this->collection($name);
        }
        return false;
    }

    /**
     * __destruct 销毁初始化变量
     */
    public function __destruct() {
        $this->databaseObject = null;
        $this->collectionModels = [];
    }

}