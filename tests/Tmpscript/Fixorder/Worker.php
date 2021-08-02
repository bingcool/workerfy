<?php
namespace Workerfy\Tests\Tmpscript\Fixorder;

/**
 * 此demo test主要是展示出来一些临时性的脚本的实现方法，比如修复某些数据啊等
 * php Master.php start -action=test1
 * Class SubscribeWorker
 * @package Workerfy\Tests\Tmpscript
 */

class Worker extends \Workerfy\AbstractProcess {

    protected $action;

    /**
     * @param $action
     */
    public function init($action = '')
    {
        $this->action = $action;
    }

    public function run() {
        sleep(1);
        var_dump($this->action);
        // 一般选择第一个worker处理，防止多个worker处理情况出现
        if($this->getProcessWorkerId() == 0 && $this->action != '') {
            switch ($this->action) {
                case 'test1' :
                    $this->actionTest1();
                    break;
                case 'test2':
                    $this->actionTest2();
                    break;
                default :
                    var_dump("action is not match anyone");
            }
        }else {
            var_dump("action is not be setted");
        }
    }


    public function actionTest1() {
        var_dump("hello,test1");
    }

    public function actionTest2() {
        var_dump("hello,test2");
    }

}