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

class RawResponse
{
    /**
     * @var array The response headers in the form of an associative array.
     */
    protected $headers = [];

    /**
     * @var string The raw response body.
     */
    protected $body;

    /**
     * @var array $info
     */
    protected $info;


    /**
     * Creates a new RawResponse entity.
     *
     * @param string|array $headers        The headers as a raw string or array.
     * @param string       $body           The raw response body.
     */
    public function __construct($headers, $body, $info)
    {
        $this->info = $info;
        if (is_array($headers)) {
            $this->headers = $headers;
        } else {
            $this->setHeadersFromString($headers);
        }

        $this->body = $body;
    }

    /**
     * Return the response headers.
     * @param string $headeKey
     * @return array|mixed
     */
    public function getHeaders(string $headerKey = null)
    {
        if($headerKey)
        {
            return $this->headers[$headerKey] ?? '';
        }
        return $this->headers;
    }

    /**
     * Return the body of the response.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Return decode body of the response.
     *
     * @param bool $assoc
     * @return mixed
     */
    public function getDecodeBody($assoc = true)
    {
        return json_decode($this->body, $assoc) ?? $this->body;
    }

    /**
     * Return the HTTP response code.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->info['http_code'];
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->info['url'];
    }

    /**
     * @return mixed
     */
    public function getContentType()
    {
        return $this->info['content_type'] ?? '';
    }

    /**
     * @param string $opt as:
     * "url"
     * "content_type"
     * "http_code"
     * "header_size"
     * "request_size"
     * "filetime"
     * "ssl_verify_result"
     * "redirect_count"
     * "total_time"
     * "namelookup_time"
     * "connect_time"
     * "pretransfer_time"
     * "size_upload"
     * "size_download"
     * "speed_download"
     * "speed_upload"
     * "download_content_length"
     * "upload_content_length"
     * "starttransfer_time"
     * "redirect_time"
     * @return mixed
     */
    public function getInfo($opt = null)
    {
        return $this->info[$opt] ?? $this->info;
    }

    /**
     * Parse the raw headers and set as an array.
     *
     * @param string $rawHeaders The raw headers from the response.
     */
    protected function setHeadersFromString($rawHeaders)
    {
        // Normalize line breaks
        $rawHeaders = str_replace("\r\n", "\n", $rawHeaders);

        // There will be multiple headers if a 301 was followed
        // or a proxy was followed, etc
        $headerCollection = explode("\n\n", trim($rawHeaders));
        // We just want the last response (at the end)
        $rawHeader = array_pop($headerCollection);

        $headerComponents = explode("\n", $rawHeader);
        foreach ($headerComponents as $line) {
            if (strpos($line, ': ') !== false) {
                list($key, $value) = explode(': ', $line, 2);
                $this->headers[$key] = $value;
            }
        }
    }
}