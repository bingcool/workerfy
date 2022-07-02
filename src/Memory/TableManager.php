<?php
/**
 * +----------------------------------------------------------------------
 * | Daemon and Cli model about php process worker
 * +----------------------------------------------------------------------
 * | Licensed ( https://opensource.org/licenses/MIT )
 * +----------------------------------------------------------------------
 * | Author: bingcool <bingcoolhuang@gmail.com || 2437667702@qq.com>
 * +----------------------------------------------------------------------
 */

namespace Workerfy\Memory;

class TableManager
{

    use \Workerfy\Traits\SingletonTrait;

    /**
     * @var array
     */
    private $swooleTables = [];

    /**
     * @var array
     */
    private $tableSetting = [];

    /**
     * @param string $table_name
     * @param array $setting
     * @return \Swoole\Table
     * [
     *      // 每个内存表建立的行数
     *      'size' => 4,
     *      'conflict_proportion' => 0.2,
     *      // 字段
     *      'fields'=> [
     *          ['tick_tasks', \Swoole\Table::TYPE_STRING, 8096]
     *      ]
     * ]
     * @throws \Exception
     */
    public function addTable(string $table_name, array $setting)
    {
        if (isset($this->swooleTables[$table_name]) && $this->swooleTables[$table_name] instanceof \Swoole\Table) {
            return $this->swooleTables[$table_name];
        }

        if (isset($setting['fields']) && is_array($setting['fields'])) {
            $fields = $setting['fields'];
        } else {
            throw new \Exception('Swoole table fields is not setting');
        }

        $size = $setting['size'] ?? 128;
        $conflict_proportion = $setting['conflict_proportion'] ?? 0.2;
        $table = new \Swoole\Table($size, $conflict_proportion);
        $this->setTableColumn($table, $fields);
        $table->create();

        $this->tableSetting[$table_name] = $setting;
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
    private function setTableColumn(\Swoole\Table $table, array $fields)
    {
        foreach ($fields as $field) {
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
     * @return \Swoole\Table
     */
    public function getTable(string $table_name)
    {
        if (isset($this->swooleTables[$table_name]) && $this->swooleTables[$table_name] instanceof \Swoole\Table) {
            return $this->swooleTables[$table_name];
        }
        return null;
    }

    /**
     * get all table_name instance
     * @return array
     */
    public function getAllTableName()
    {
        $tableNames = [];
        if (isset($this->swooleTables) && !empty($this->swooleTables)) {
            $tableNames = array_keys($this->swooleTables);
        }
        return $tableNames;
    }

    /**
     * getAllTableKeyMapRowValue
     * @return array
     */
    public function getAllTableKeyMapRowValue()
    {
        $tableInfos = [];
        $tableNames = $this->getAllTableName();
        foreach ($tableNames as $tableName) {
            $tableInfos[$tableName] = $this->getKeyMapRowValue($tableName);
        }
        return $tableInfos;
    }

    /**
     * @param string $table_name
     * @return mixed
     */
    public function getTableSetting(string $table_name)
    {
        return $this->tableSetting[$table_name] ?? [];
    }

    /**
     * 获取table 占用的内存-单位字节
     *
     * @param string $table_name
     * @return mixed
     */
    public function getTableMemory(string $table_name)
    {
        $table = $this->getTable($table_name);
        if (isset($table) && is_object($table) && isset($table->memorySize)) {
            return $table->memorySize;
        }
        return 0;
    }

    /**
     *
     * @param string $table_name
     * @return array return formatter of [$size, $memory, $setting]
     */
    public function getTableInfo(string $table_name)
    {
        $info = [];
        $table = $this->getTable($table_name);
        if (isset($table->size)) {
            array_push($info, $table->size);
        }

        if (isset($table->memorySize)) {
            array_push($info, $table->memorySize);
        } else {
            array_push($info, 0);
        }

        if (isset($this->tableSetting[$table_name])) {
            array_push($info, $this->tableSetting[$table_name]);
        } else {
            array_push($info, []);
        }

        return $info;
    }

    /**
     *
     * @param string $table
     * @return array
     */
    public function getTableKeys(string $table)
    {
        $keys = [];
        if (is_string($table)) {
            $tableName = $table;
            $table = $this->getTable($tableName);
        }
        if (is_object($table) && $table instanceof \Swoole\Table) {
            foreach ($table as $key => $item) {
                array_push($keys, $key);
            }
        }
        return $keys;
    }

    /**
     * get table every row Value
     *
     * @param string $table
     * @return array
     */
    public function getKeyMapRowValue(string $table)
    {
        $tableRows = [];
        if (is_string($table)) {
            $table_name = $table;
            $table = $this->getTable($table_name);
        }
        if (is_object($table) && $table instanceof \Swoole\Table) {
            foreach ($table as $key => $item) {
                $tableRows[$key] = $item;
            }
        }
        return $tableRows;
    }
}