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

namespace Workerfy;

use Exception;

class ConfigLoad {

	use \Workerfy\Traits\SingletonTrait;

	private $config = [];

	private $color = null;

	private function __construct() {
		$this->color = new \Workerfy\EachColor();
	}

    /**
     * @param string|null $config_file_path
     * @return array
     * @throws Exception
     */
	public function loadConfig(string $config_file_path = null) {
	    if(empty($config_file_path)) {
	        return [];
        }

        if(!is_file($config_file_path)) {
            throw new \Exception("Load config path is not a file");
        }

        $config = require $config_file_path;
        if(!is_array($config)) {
            throw new \Exception("Config file {$config_file_path} is not return array");
        }

        $this->config = array_merge_recursive($this->config, $config);

        return $this->config;
	}

	/**
	 * setConfig
	 * @param array $config
	 */
	public function setConfig(array $config = []) {
		$this->config = array_merge_recursive($this->config, $config);
	}

	/**
	 * getConfig
	 * @return array
	 */
	public function getConfig() {
		return $this->config;
	}

}