<?php
/**
 * This file is part of Simps
 *
 * @link     https://github.com/simps/mqtt
 * @contact  Lu Fei <lufei@simps.io>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code
 */

declare(strict_types=1);

namespace Workerfy\Tests;

use Simps\MQTT\Exception\RuntimeException;
use Simps\MQTT\ProtocolV5;
use Swoole\Coroutine;
use Simps\MQTT\Client;
use Simps\MQTT\Types;
use Simps\MQTT\Protocol;

class ClientStream extends Client
{
    /** @var resource */
    private $client;

    private $connectData;

    private $config = [];

    private $swConfig = [];

    private $messageId = 0;

    private $errCode;

    private $errMsg;

    /**
     * ClientStream constructor.
     * @param array $config
     * @param array $swConfig
     * @param int $type
     */
    public function __construct(array $config, array $swConfig = [], int $type = SWOOLE_SOCK_TCP)
    {
        $this->config = array_replace_recursive($this->config, $config);
        $this->swConfig = $swConfig;

        if($this->config['protocol_level'] === 5) {
            throw new \RuntimeException('Temporarily not supported Mqtt protocol v5.0');
        }

        $result = $this->createPhpStreamClient();

        if (!$result) {
            $this->reConnect();
        }

    }

    /**
     * @return bool
     */
    private function createPhpStreamClient()
    {
        if (isset($this->swConfig['ssl_cafile'])) {
            $sslOptions['ssl_cafile'] = $this->swConfig['ssl_cafile'];
        }

        if(isset($this->swConfig['ssl_verify_peer'])) {
            $sslOptions['verify_peer'] = $this->swConfig['ssl_verify_peer'];
        }

        if(isset($this->swConfig['ssl_allow_self_signed'])) {
            $sslOptions['allow_self_signed'] = $this->swConfig['ssl_allow_self_signed'];
        }

        if(isset($this->swConfig['ssl_cert_file'])) {
            $sslOptions['local_cert'] = $this->swConfig['ssl_cert_file'];
        }

        if(isset($this->swConfig['ssl_key_file'])) {
            $sslOptions['local_pk'] = $this->swConfig['ssl_key_file'];
        }

        if(isset($sslOptions) && !empty($sslOptions)) {
            $socketContext = stream_context_create(
                [
                    'ssl' => $sslOptions
                ]
            );
            $this->client = stream_socket_client('tls://' . $this->config['host'] . ':' . $this->config['port'], $errno, $errstr, 60, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $this->client = stream_socket_client('tcp://' . $this->config['host'] . ':' . $this->config['port'], $errno, $errstr, 60, STREAM_CLIENT_CONNECT);
        }

        if (!is_resource($this->client)) {
            return false;
        }

        $this->errCode = $errno;
        $this->errMsg = $errstr;
        stream_set_timeout($this->client, (int)ceil($this->config['time_out']));
        stream_set_blocking($this->client, false);
        return true;
    }

    protected function reConnect()
    {
        $reConnectTime = 1;
        $result = false;
        while (!$result) {
            sleep(3);
            stream_socket_shutdown($this->client, STREAM_SHUT_WR);
            $result = $this->createPhpStreamClient();
            ++$reConnectTime;
        }
        $this->connect((bool)$this->connectData['clean_session'], $this->connectData['will'] ?? []);
    }

    public function send(array $data, $response = true)
    {
        if ($this->config['protocol_level'] === 5) {
            $package = ProtocolV5::pack($data);
        } else {
            $package = Protocol::pack($data);
        }
        fwrite($this->client, $package, strlen($package));
        if($response) {
            return $this->recvAck($data['type']);
        }
        return true;
    }

    protected function recvAck($type) {
        try {
            switch ($type) {
                // read CONNACK packet
                case Types::CONNECT:
                    $response = $this->read(4);
                    break;
                // read PUBACK|PUBREC|PUBREL|PUBCOMP packet
                case Types::PUBLISH:
                    $response = $this->read(4);
                    break;
                // read SUBACK packet
                case Types::SUBSCRIBE:
                    $response = $this->getCompletePacket();
                    break;
                // read UNSUBACK packet
                case Types::UNSUBSCRIBE:
                    $response = $this->read(4);
                    break;
                // read PING packet, PINGRESP
                case Types::PINGRESP:
                    $response = $this->read(2);
                    break;
                default:
                    throw new InvalidArgumentException('MQTT Type not exist');
            }
        } catch (TypeError $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode());
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode());
        }

        if ($response === '') {
            $this->reConnect();
        } elseif ($response === false) {
            if($this->client->errCode === SOCKET_ECONNRESET) {
                stream_socket_shutdown($this->client, STREAM_SHUT_WR);
            }else
                if($this->client->errCode !== SOCKET_ETIMEDOUT) {
                    throw new RuntimeException($this->client->errMsg, $this->client->errCode);
                }
        } elseif (strlen($response) > 0) {
            return Protocol::unpack($response);
        }

        return true;
    }

    public function read($int = 8192, $nb = false)
    {
        $string = '';
        $togo = $int;

        if ($nb) {
            return fread($this->client, $togo);
        }

        while (!feof($this->client) && $togo > 0) {
            $fread = fread($this->client, $togo);
            $string .= $fread;
            $togo = $int - strlen($string);
        }

        return $string;
    }

    public function getCompletePacket() {
        $byte = $this->read(1,true);
        $multiplier = 1;
        $value = 0;
        $remainingLengthHeaderByte = '';
        do {
            // loop read one byte to get RemainingLength, max read 4 byte
            $digit = ord($this->read(1));
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $remainingLengthHeaderByte .= chr($digit);
        } while (($digit & 128) !== 0);
        // has read remainingLengthHeaderByte, continue to read content is variable header+payload
        $string = $value > 0 ? $this->read($value) : '';

        $packetData = $byte.$remainingLengthHeaderByte.$string;

        return $packetData;
    }
}
