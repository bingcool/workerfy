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

namespace Workerfy\Library\Db\Concern;

use Workerfy\Library\Db\PDOConnection;

trait ParseSql {

    /**
     * @param array $allowFields
     * @return array
     */
    protected function parseInsertSql(array $allowFields) {
        $fields = $columns = $bindParams = [];
        foreach($allowFields as $field) {
            if(isset($this->_data[$field])) {
                $fields[] = $field;
                $column = ':'.$field;
                $columns[] = $column;
                $bindParams[$column] = $this->_data[$field];
            }else {
                unset($this->_data[$field]);
            }
        }
        $fields = implode(',', $fields);
        $columns = implode(',', $columns);
        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$columns}) ";
        return [$sql, $bindParams];
    }

    /**
     * @return array
     */
    protected function parseFindSqlByPk() {
        $pk = $this->getPk();
        $sql = "SELECT * FROM {$this->table} WHERE {$pk}=:pk";
        $bindParams = [
            ':pk'=>$this->getPkValue() ?? 0
        ];
        return [$sql, $bindParams];
    }

    /**
     * @param array $diffData
     * @param array $allowFields
     * @return array
     */
    protected function parseUpdateSql(array $diffData, array $allowFields) {
        $setValues = $bindParams = [];
        $pk = $this->getPk();
        foreach($allowFields as $field) {
            if(isset($diffData[$field])) {
                $column = ':'.$field;
                $setValues[] = $field.'='.$column;
                $bindParams[$column] = $diffData[$field];
            }
        }
        $setValueStr = implode(',', $setValues);
        $sql = "UPDATE {$this->table} SET {$setValueStr} WHERE {$pk}=:pk";
        $bindParams[':pk'] = $this->getPkValue() ?? 0;
        return [$sql, $bindParams];
    }

    /**
     * parseDeleteSql
     */
    protected function parseDeleteSql() {
        $pk = $this->getPk();
        $pkValue = $this->getPkValue();
        if($pkValue) {
            $sql = "DELETE FROM {$this->table} WHERE {$pk}=:pk LIMIT 1";
            $bindParams[':pk'] = $pkValue;
        }
        return [$sql ?? '', $bindParams ?? []];
    }

    /**
     * @param string $where
     * @param array $bindParams
     * @return string
     */
    protected function parseWhereSql(string $where) {
        $sql = "SELECT * FROM {$this->table} WHERE {$where}";
        return $sql;
    }

    /**
     * @param string $where
     * @param array $bindParams
     * @return $this|boolean
     */
    public function findOne(string $where, array $bindParams = []) {
        $sql = $this->parseWhereSql($where);
        /**@var PDOConnection $connection*/
        $connection = $this->getSlaveConnection();
        if(!is_object($connection)) {
            $connection = $this->getConnection();
        }
        $attributes = $connection->createCommand($sql)->queryOne($bindParams);
        if($attributes) {
            $this->parseOrigin($attributes);
            $this->setIsNew(false);
        }else {
            $this->exists(false);
            $this->setIsNew(true);
        }
        return $this;
    }

    /**
     * @param array $attributes
     */
    protected function parseOrigin(array $attributes = []) {
        if($attributes) {
            foreach($attributes as $field => $value) {
                $this->_data[$field] = $value;
            }
            // 记录源数据
            $this->_origin = $this->_data;
            $this->exists(true);
        }
    }

}