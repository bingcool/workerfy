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

use Workerfy\Exception\CurlException;

class CurlHttpClient implements HttpClientInterface
{
    /**
     * @var BaseCurl Procedural curl as object
     */
    protected $baseCurl;

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var array The Curl options
     */
    protected $options = [];

    /**
     * @var string|boolean The raw response from the server
     */
    protected $rawResponse;

    /**
     * @var string The client error message
     */
    protected $curlErrorMessage = '';

    /**
     * @var int The curl client error code
     */
    protected $curlErrorCode = 0;

    /**
     * @param BaseCurl|null Procedural curl as object
     */
    public function __construct(BaseCurl $Curl = null)
    {
        $this->baseCurl = $Curl ?: new BaseCurl();
    }

    /**
     * @return array
     */
    protected function getDefaultOptions()
    {
        return $options = [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true, // Return response as string
            CURLOPT_HEADER => false, // Enable header processing
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ];
    }


    /**
     * Sends a request to the server and returns the raw response.
     *
     * @param string $url The endpoint to send the request to.
     * @param string $method The request method.
     * @param string $body The body of the request.
     * @param int $timeOut The timeout in seconds for the request.
     *
     * @return RawResponse Raw response from the server.
     *
     * @throws CurlException
     */
    public function send(
        $url,
        $method,
        $body = null,
        int $timeOut = 10
    ) {
        $this->openConnection($url, $method, $body, $this->headers, $timeOut);
        $this->sendRequest();
        $curlErrorCode = $this->baseCurl->errno();
        $this->curlErrorCode = $curlErrorCode;
        $curlErrorCode && $this->curlErrorMessage = $this->baseCurl->error();
        if($curlErrorCode)
        {
            throw new CurlException($this->baseCurl->error(), $curlErrorCode);
        }
        // Separate the raw headers from the raw body
        list($rawHeaders, $rawBody) = $this->extractResponseHeadersAndBody();
        $info = $this->baseCurl->getInfo();

        $this->close();

        return new RawResponse($rawHeaders, $rawBody, $info);
    }

    /**
     * Opens a new curl connection.
     *
     * @param string $url     The endpoint to send the request to.
     * @param string $method  The request method.
     * @param string $body    The body of the request.
     * @param array  $headers The request headers.
     * @param int    $timeOut The timeout in seconds for the request.
     */
    public function openConnection($url, $method, $body, array $headers = [], $timeOut = 10)
    {
        if($url)
        {
            $this->options[CURLOPT_URL] = $url;
        }

        if($headers)
        {
            $this->options[CURLOPT_HTTPHEADER] = $this->compileRequestHeaders($headers);
        }

        if($timeOut)
        {
            $this->options[CURLOPT_CONNECTTIMEOUT] = 10;
            $this->options[CURLOPT_TIMEOUT] = $timeOut;
        }

        if($method !== "GET")
        {
            if(empty($body))
            {
                throw new CurlException("Curl Body empty");
            }
            $this->options[CURLOPT_POSTFIELDS] = $body;
        }

        $this->baseCurl->init();

        $options = $this->options + $this->getDefaultOptions();

        if(isset($this->options[CURLOPT_NOBODY]) && (bool) $this->options[CURLOPT_NOBODY] === true)
        {
            $options[CURLOPT_HEADER] = $this->options[CURLOPT_HEADER] = true;
        }

        $this->baseCurl->setOptionArray($options);
    }

    /**
     * Send the request and get the raw response from curl
     */
    public function sendRequest()
    {
        $this->rawResponse = $this->baseCurl->exec();
    }

    /**
     * Compiles the request headers into a curl-friendly format.
     *
     * @param array $headers The request headers.
     *
     * @return array
     */
    public function compileRequestHeaders(array $headers)
    {
        $return = [];
        foreach ($headers as $key => $value) {
            $return[] = $key . ': ' . $value;
        }
        return $return;
    }

    /**
     * Extracts the headers and the body into a two-part array
     *
     * @return array
     */
    public function extractResponseHeadersAndBody()
    {
        $parts = explode("\r\n\r\n", $this->rawResponse);
        $rawBody = array_pop($parts);
        $rawHeaders = implode("\r\n\r\n", $parts);

        return [trim($rawHeaders), trim($rawBody)];
    }

    /**
     * @param $url
     * @param array $params
     * @param int $timeOut
     * @return RawResponse
     * @throws Exception
     */
    public function get($url, array $params = [], int $timeOut = 10)
    {
        $uri = parse_url($url);
        if(is_array($params) && !empty($params))
        {
            $queryString = http_build_query($params);
            if(isset($uri['query']) && !empty($uri['query']))
            {
                $uri['query'] = $uri['query'].'&'.$queryString;
            }else {
                $uri['query'] = $queryString;
            }
        }

        if(isset($uri['user']))
        {
            $user = $uri['user'].':';
        }

        if(isset($uri['pass']))
        {
            $pass = $uri['pass'].'@';
        }

        if(isset($uri['port']))
        {
            $port = ':'.$uri['port'];
        }

        if(isset($uri['query']))
        {
            $query = '?'.$uri['query'];
        }

        if(isset($uri['fragment']))
        {
            $fragment = '#'.$uri['fragment'];
        }

        $url = $uri['scheme'].'://'.($user ??'').($pass ?? '').$uri['host'].($port ?? '').($uri['path'] ?? '/').($query ?? '').($fragment ?? '');

        $this->options[CURLOPT_HTTPGET] = "GET";

        return $this->send($url,'GET', '', $timeOut);
    }

    /**
     * @param $url
     * @param array $params
     * @param int $timeOut
     * @return RawResponse|bool
     * @throws \Exception
     */
    public function post($url, array $params, int $timeOut = 10)
    {
        if(empty($params))
        {
            return  false;
        }
        $this->options[CURLOPT_POST] = 1;
        return $this->send($url,'POST', $params, $timeOut);
    }

    /**
     * @param array $options
     */
    public function setOptionArray(array $options)
    {
        $this->options = $options + $this->options;
    }

    /**
     * @param array $headers
     */
    public function setHeaderArray(array $headers)
    {
        $this->headers = $headers + $this->headers;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return int
     */
    public function getCurlErrorCode()
    {
        return $this->curlErrorCode;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->curlErrorMessage;
    }

    /**
     * Closes an existing curl connection
     */
    public function close()
    {
        $this->baseCurl->close();
    }

}