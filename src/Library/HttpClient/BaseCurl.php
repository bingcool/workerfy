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

namespace Workerfy\Library\HttpClient;

class BaseCurl
{
    /**
     * @var resource Curl resource instance
     */
    protected $curl;

    /**
     * Make a new curl reference instance
     */
    public function init()
    {
        $this->curl = curl_init();
    }

    /**
     * Set a curl option
     *
     * @param $key
     * @param $value
     */
    public function setOption($key, $value)
    {
        curl_setopt($this->curl, $key, $value);
    }

    /**
     * Set an array of options to a curl resource
     *
     * @param array $options
     */
    public function setOptionArray(array $options)
    {
        curl_setopt_array($this->curl, $options);
    }

    /**
     * Send a curl request
     *
     * @return mixed
     */
    public function exec()
    {
        return curl_exec($this->curl);
    }

    /**
     * Return the curl error number
     *
     * @return int
     */
    public function errno()
    {
        return curl_errno($this->curl);
    }

    /**
     * Return the curl error message
     *
     * @return string
     */
    public function error()
    {
        return curl_error($this->curl);
    }

    /**
     * Get info from a curl reference
     *
     * @param $opt
     *
     * @return mixed
     */
    public function getInfo($opt = 0)
    {
        if(empty($opt))
        {
            if(class_exists('Swoole\Curl\Handler') && is_object($this->curl) && $this->curl instanceof \Swoole\Curl\Handler) {
                $info = curl_getinfo($this->curl, 0);
            }else if(is_resource($this->curl))
            {
                $info = curl_getinfo($this->curl);
            }
        }else {
            $info = curl_getinfo($this->curl, $opt);
        }

        return $info ?? '';
    }

    /**
     * Get the currently installed curl version
     *
     * @return array
     */
    public function version()
    {
        return curl_version();
    }

    /**
     * @return resource
     */
    public function getCurlHandler()
    {
        return $this->curl;
    }

    /**
     * Close the resource connection to curl
     */
    public function close()
    {
        curl_close($this->curl);
    }

}
