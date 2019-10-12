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

    public $swoole_tables = [];

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
     */
    public function addTable(string $table_name, array $setting) {
        $table_key = md5($table_name);
        if(isset($this->swoole_tables[$table_key]) && $this->swoole_tables[$table_key] instanceof \Swoole\Table) {
            return $this->swoole_tables[$table_key];
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

        $this->swoole_tables[$table_key] = $table;
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
     * @return mixed
     */
    public function getTable(string $table_name) {
        $table_key = md5($table_name);
        if(isset($this->swoole_tables[$table_key]) && $this->swoole_tables[$table_key] instanceof \Swoole\Table) {
            return $this->swoole_tables[$table_key];
        }else {
            throw new \Exception("{$table_name} table is not create, please create before using");
        }
    }
}