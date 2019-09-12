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

class Config {

	use \Workerfy\Traits\SingletonTrait;

	private $config = [];

	private $color = null;

	private function __construct() {
		$this->color = new \Workerfy\EachColor();
	}

	public function loadConfig(string $config_file_path = null) {
		if(is_file($config_file_path)) {
			$config = require $config_file_path;
			if(is_array($config)) {
				$this->config = array_merge_recursive($this->config, $config);
			}else {
				$msg = $this->color->getColoredString("{$config_file_path} load is not return array", "red", "black");
				echo($msg). "\n\n";
				exit;
			}
		}
	}

	public function setConfig(array $config = []) {
		$this->config = array_merge_recursive($this->config, $config);
	}

	public function getConfig() {
		return $this->config;
	}
}