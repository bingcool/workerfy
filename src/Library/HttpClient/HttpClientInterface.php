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
/**
 * Interface HttpClientInterface
 */
interface HttpClientInterface
{
    /**
     * Sends a request to the server and returns the raw response.
     *
     * @param string $url     The endpoint to send the request to.
     * @param string $method  The request method.
     * @param string $body    The body of the request.
     * @param int    $timeOut The timeout in seconds for the request.
     *
     * @return RawResponse Raw response from the server.
     *
     * @throws \Workerfy\Exception\CurlException
     */
    public function send($url, $method, $body, int $timeOut);
}