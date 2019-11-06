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

use Cron\CronExpression;

class TableManager {

    use \Workerfy\Traits\SingletonTrait;

    private $swoole_tables = [];

    /**
     * @param string $table_name
     * @param array $setting
     * [
        // 每个内存表建立的行数
        'size' => 4,
        'conflict_proportion' => 0.2,
        // 字段
        'fields'=> [
            ['tick_tasks','string',8096]
        ]
     ]
     * @throws Exception
     */
    public function addTable(string $table_name, array $setting) {
        if(isset($this->swoole_tables[$table_name]) && $this->swoole_tables[$table_name] instanceof \Swoole\Table) {
            return $this->swoole_tables[$table_name];
        }

        $size = $setting['size'] ?? 1024;
        $conflict_proportion = $setting['conflict_proportion'] ?? 0.2;

        $table = new \Swoole\Table($size, $conflict_proportion);

        if(isset($setting['fields']) && is_array($setting['fields'])) {
            $fields = $setting['fields'];
        }else {
            throw new \Exception('Swoole table fields is not setting');
        }

        $this->setTableColumn($table, $fields);

        $table->create();

        $table->setting = $setting;

        $this->swoole_tables[$table_name] = $table;

        // 标志启用
        defined('ENABLE_WORKERFY_SWOOLE_TABLE') or define('ENABLE_WORKERFY_SWOOLE_TABLE', 1);

        return $table;
    }

    /**
     * @param \Swoole\Table $table
     * @param array $fields
     * @return \Swoole\Table
     */
    private function setTableColumn(\Swoole\Table $table, array $fields) {
        foreach($fields  as $field) {
            switch (strtolower($field[1])) {
                case 'int':
                case \Swoole\Table::TYPE_INT:
                    $table->column($field[0], \Swoole\Table::TYPE_INT, (int)$field[2]);
                    break;
                case 'string':
                case \Swoole\Table::TYPE_STRING:
                    $table->column($field[0], \Swoole\Table::TYPE_STRING, (int)$field[2]);
                    break;
                case 'float':
                case \Swoole\Table::TYPE_FLOAT:
                    $table->column($field[0], \Swoole\Table::TYPE_FLOAT, (int)$field[2]);
                    break;
            }
        }
        return $table;
    }

    /**
     * @param string $table_name
     * @throws Exception
     * @return mixed
     */
    public function getTable(string $table_name) {
        if(isset($this->swoole_tables[$table_name]) && $this->swoole_tables[$table_name] instanceof \Swoole\Table) {
            return $this->swoole_tables[$table_name];
        }else {
            throw new \Exception("table name = {$table_name} is not create, please create it before using");
        }
    }

    /**
     * 获取管理定义的table_name
     * @return array
     */
    public function getAllTableName() {
        $table_name = [];
        if(isset($this->swoole_tables) && !empty($this->swoole_tables)) {
            $table_name = array_keys($this->swoole_tables);
        }
        return $table_name;
    }

    /**
     * @param string $table_name
     * @return mixed
     */
    public function getTableSetting(string $table_name) {
        $table = $this->getTable($table_name);
        var_dump($table);
        if(isset($table) && is_object($table) && isset($table->setting)) {
            return $table->setting;
        }
    }

    /**
     * 获取table占用的内存，单位字节
     * @param string $table_name
     * @return mixed
     */
    public function getTableMemory(string $table_name) {
        $table = $this->getTable($table_name);
        if(isset($table) && is_object($table) && isset($table->memorySize)) {
            return $table->memorySize;
        }
        return 0;
    }

    /**
     * 返回table基本信息
     * @param string $table_name
     * @return array 返回格式 = [$size, $momory, $setting]
     */
    public function getTableInfo(string $table_name) {
        $info = [];
        $table = $this->getTable($table_name);
        if(isset($table->size)) {
            array_push($info, $table->size);
        }

        if(isset($table->memorySize)) {
            array_push($info, $table->memorySize);
        }else {
            array_push($info, 0);
        }

        if(isset($table->setting)) {
            array_push($info, $table->setting);
        }else {
            array_push($info, []);
        }

        return $info;
    }
}