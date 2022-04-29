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

namespace Workerfy;

use Exception;

class ConfigLoader
{

    use \Workerfy\Traits\SingletonTrait;

    /**
     * @var  string
     */
    protected $configFilePath;

    /**
     * @var array
     */
    private $config = [];

    /**
     * @param string|null $configFilePath
     * @return array
     * @throws Exception
     */
    public function loadConfig(string $configFilePath)
    {
        if (empty($configFilePath)) {
            return [];
        }

        if (!is_file($configFilePath)) {
            throw new \Exception("Load config path is not a file");
        }

        $config = require $configFilePath;
        if (!is_array($config)) {
            throw new \Exception("Config file {$configFilePath} is not return array");
        }

        $this->configFilePath = $configFilePath;
        $this->config = array_merge_recursive($this->config, $config);

        return $this->config;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function reloadConfig()
    {
        return $this->loadConfig($this->configFilePath);
    }

    /**
     * setConfig
     * @param array $config
     * @return void
     */
    public function setConfig(array $config = [])
    {
        $this->config = array_merge_recursive($this->config, $config);
    }

    /**
     * getConfig
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

}