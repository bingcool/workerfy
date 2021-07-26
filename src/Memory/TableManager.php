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

namespace Workerfy\Memory;

class TableManager {

    use \Workerfy\Traits\SingletonTrait;

    /**
     * @var array
     */
    private $swooleTables = [];

    /**
     * @param string $table_name
     * @param array $setting
     * [
        // 每个内存表建立的行数
        'size' => 4,
        'conflict_proportion' => 0.2,
        // 字段
        'fields'=> [
            ['tick_tasks', \Swoole\Table::TYPE_STRING, 8096]
        ]
     ]
     * @throws Exception
     */
    public function addTable(string $table_name, array $setting) {
        if(isset($this->swooleTables[$table_name]) && $this->swooleTables[$table_name] instanceof \Swoole\Table)
        {
            return $this->swooleTables[$table_name];
        }

        if(isset($setting['fields']) && is_array($setting['fields']))
        {
            $fields = $setting['fields'];
        }else
        {
            throw new \Exception('Swoole table fields is not setting');
        }

        $size = $setting['size'] ?? 128;
        $conflict_proportion = $setting['conflict_proportion'] ?? 0.2;
        $table = new \Swoole\Table($size, $conflict_proportion);
        $this->setTableColumn($table, $fields);
        $table->create();

        $table->setting = $setting;
        $table->table_name = $table_name;
        $this->swooleTables[$table_name] = $table;

        // enable flag
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
        if(isset($this->swooleTables[$table_name]) && $this->swooleTables[$table_name] instanceof \Swoole\Table)
        {
            return $this->swooleTables[$table_name];
        }
        return null;
    }

    /**
     * 获取管理定义的table_name
     * @return array
     */
    public function getAllTableName() {
        $table_names = [];
        if(isset($this->swooleTables) && !empty($this->swooleTables))
        {
            $table_names = array_keys($this->swooleTables);
        }
        return $table_names;
    }

    /**
     * getAllTableKeyMapRowValue 获取所有table的key和value信息
     * @return array
     * @throws Exception
     */
    public function getAllTableKeyMapRowValue() {
        $table_infos = [];
        $table_names = $this->getAllTableName();
        foreach($table_names as $table_name)
        {
            $table_infos[$table_name] = $this->getKeyMapRowValue($table_name);
        }
        return $table_infos;
    }

    /**
     * @param string $table_name
     * @return mixed
     * @throws Exception
     */
    public function getTableSetting(string $table_name) {
        $table = $this->getTable($table_name);
        if(isset($table) && is_object($table) && isset($table->setting))
        {
            return $table->setting;
        }
    }

    /**
     * 获取table占用的内存，单位字节
     * @param string $table_name
     * @return mixed
     * @throws \Exception
     */
    public function getTableMemory(string $table_name) {
        $table = $this->getTable($table_name);
        if(isset($table) && is_object($table) && isset($table->memorySize))
        {
            return $table->memorySize;
        }
        return 0;
    }

    /**
     * 返回table基本信息
     * @param string $table_name
     * @return array 返回格式 = [$size, $memory, $setting]
     * @throws Exception
     */
    public function getTableInfo(string $table_name) {
        $info = [];
        $table = $this->getTable($table_name);
        if(isset($table->size))
        {
            array_push($info, $table->size);
        }

        if(isset($table->memorySize))
        {
            array_push($info, $table->memorySize);
        }else {
            array_push($info, 0);
        }

        if(isset($table->setting))
        {
            array_push($info, $table->setting);
        }else {
            array_push($info, []);
        }

        return $info;
    }

    /**
     * 获取已设置的key
     * @param string $table
     * @return array
     * @throws Exception
     */
    public function getTableKeys(string $table) {
        $keys = [];
        if(is_string($table))
        {
            $table_name = $table;
            $table = $this->getTable($table_name);
        }
        if(is_object($table) && $table instanceof \Swoole\Table)
        {
            foreach ($table as $key => $item)
            {
                array_push($keys, $key);
            }
        }
        return $keys;
    }

    /**
     * 获取table的key映射的每一行数据rowValue
     * @param string $table
     * @return array
     * @throws Exception
     */
    public function getKeyMapRowValue(string $table) {
        $table_rows = [];
        if(is_string($table))
        {
            $table_name = $table;
            $table = $this->getTable($table_name);
        }
        if(is_object($table) && $table instanceof \Swoole\Table)
        {
            foreach ($table as $key => $item)
            {
                $table_rows[$key] = $item;
            }
        }
        return $table_rows;
    }
}