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

    private $swooleAtomic = [];

    private $swooleAtomicLong = [];

    /**
     * addAtomic
     * @param string $atomic_name
     * @param int $init_value
     * @return mixed
     */
    public function addAtomic(string $atomic_name, int $init_value = 0) {
        if(!isset($this->swooleAtomic[$atomic_name])) {
            $atomic = new \Swoole\Atomic($init_value);
            $this->swooleAtomic[$atomic_name] = $atomic;
        }
        return $this->swooleAtomic[$atomic_name];
    }

    /**
     * addAtomicLong
     * @param string      $atomic_name
     * @param int|integer $init_value
     * @return mixed
     */
    public function addAtomicLong(string $atomic_name, int $init_value = 0) {
        if(!isset($this->swooleAtomicLong[$atomic_name])){
            $atomic = new \Swoole\Atomic\Long($init_value);
            $this->swooleAtomicLong[$atomic_name] = $atomic;
        }
        return $this->swooleAtomicLong[$atomic_name];
    }

    /**
     * getAtomic
     * @param  string $atomic_name
     * @return mixed
     */
    public function getAtomic(string $atomic_name) {
        if(isset($this->swoole_atomic[$atomic_name])){
            return $this->swooleAtomic[$atomic_name];
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
            return $this->swooleAtomicLong[$atomic_name];
        }else{
            return null;
        }
    }

    /**
     * getAllAtomicName
     * @param  int $type
     * @return array
     */
    public function getAllAtomicName(int $type = self::ATOMIC_SHORT) {
        $atomicName = [];
        if($type === self::ATOMIC_SHORT) {
            if(isset($this->swooleAtomic) && !empty($this->swooleAtomic)) {
                $atomicName = array_keys($this->swooleAtomic);
            }
        }else if($type === self::ATOMIC_LONG) {
            if(isset($this->swooleAtomicLong) && !empty($this->swooleAtomicLong)) {
                $atomicName = array_keys($this->swooleAtomicLong);
            }
        }
        return $atomicName;
    }
}