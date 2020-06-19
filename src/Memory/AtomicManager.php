<?php
/**
+----------------------------------------------------------------------
| swoolefy framework bases on swoole extension development, we can use it easily!
+----------------------------------------------------------------------
| Licensed ( https://opensource.org/licenses/MIT )
+----------------------------------------------------------------------
| Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
+----------------------------------------------------------------------
 */

namespace Workerfy\Memory;

class AtomicManager {
    use \Workerfy\Traits\SingletonTrait;

    const ATOMIC_SHORT = 1;
    const ATOMIC_LONG = 2;

    private $swoole_atomic = [];

    private $swoole_atomic_long = [];

    /**
     * addAtomic
     * @param string $atomic_name
     * @param int $init_value
     * @return mixed
     */
    public function addAtomic(string $atomic_name, int $init_value = 0) {
        if(!isset($this->swoole_atomic[$atomic_name])) {
            $atomic = new \Swoole\Atomic($init_value);
            $this->swoole_atomic[$atomic_name] = $atomic;
        }
        return $this->swoole_atomic[$atomic_name];
    }

    /**
     * addAtomicLong
     * @param string      $atomic_name
     * @param int|integer $init_value
     * @return mixed
     */
    public function addAtomicLong(string $atomic_name, int $init_value = 0) {
        if(!isset($this->swoole_atomic_long[$atomic_name])){
            $atomic = new \Swoole\Atomic\Long($init_value);
            $this->swoole_atomic_long[$atomic_name] = $atomic;
        }
        return $this->swoole_atomic_long[$atomic_name];
    }

    /**
     * getAtomic
     * @param  string $atomic_name
     * @return mixed
     */
    public function getAtomic(string $atomic_name) {
        if(isset($this->swoole_atomic[$atomic_name])){
            return $this->swoole_atomic[$atomic_name];
        }else{
            return null;
        }
    }

    /**
     * getAtomicLong
     * @param  string $atomic_name
     * @return mixed
     */
    public function getAtomicLong(string $atomic_name) {
        if(isset($this->swoole_atomic_long[$atomic_name])){
            return $this->swoole_atomic_long[$atomic_name];
        }else{
            return null;
        }
    }

    /**
     * 获取以定义的Atomic的名称
     * @param  int $type
     * @return array
     */
    public function getAllAtomicName(int $type = self::ATOMIC_SHORT) {
        $atomic_name = [];
        if($type === self::ATOMIC_SHORT) {
            if(isset($this->swoole_atomic) && !empty($this->swoole_atomic)) {
                $atomic_name = array_keys($this->swoole_atomic);
            }
        }else if($type === self::ATOMIC_LONG) {
            if(isset($this->swoole_atomic_long) && !empty($this->swoole_atomic_long)) {
                $atomic_name = array_keys($this->swoole_atomic_long);
            }
        }
        return $atomic_name;
    }
}