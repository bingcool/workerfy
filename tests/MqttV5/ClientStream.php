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

namespace Simps\MQTT;

use Simps\MQTT\Client;
use Simps\MQTT\Protocol;
use Simps\MQTT\ProtocolV5;
use Simps\MQTT\Types;
use Simps\MQTT\Hex\ReasonCode;
use Simps\MQTT\Exception\RuntimeException;
use Simps\MQTT\Exception\InvalidArgumentException;

class ClientStream extends Client
{
    /** @var resource */
    protected $client;

    protected $swConfig = [];

    public $errCode;

    public $errMsg;

    /**
     * ClientStream constructor.
     * @param array $config
     * @param array $swConfig
     * @param int $type
     */
    public function __construct(array $config, array $swConfig = [], int $type = SWOOLE_SOCK_TCP)
    {
        $this->config = array_replace_recursive($this->config, $config);
        $this->swConfig = array_replace_recursive($this->swConfig, $swConfig);

//        if($this->config['protocol_level'] === 5) {
//            throw new \RuntimeException('Temporarily not supported Mqtt protocol v5.0');
//        }

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
        if(isset($this->swConfig['ssl_cafile']) && !empty($this->swConfig['ssl_cafile']))
        {
            $sslOptions['ssl_cafile'] = $this->swConfig['ssl_cafile'];
        }

        if(isset($this->swConfig['ssl_verify_peer']) && !empty($this->swConfig['ssl_verify_peer']))
        {
            $sslOptions['verify_peer'] = $this->swConfig['ssl_verify_peer'];
        }

        if(isset($this->swConfig['ssl_allow_self_signed']) && !empty($this->swConfig['ssl_allow_self_signed']))
        {
            $sslOptions['allow_self_signed'] = $this->swConfig['ssl_allow_self_signed'];
        }

        if(isset($this->swConfig['ssl_cert_file']) && !empty($this->swConfig['ssl_cert_file']))
        {
            $sslOptions['local_cert'] = $this->swConfig['ssl_cert_file'];
        }

        if(isset($this->swConfig['ssl_key_file']) && !empty($this->swConfig['ssl_key_file']))
        {
            $sslOptions['local_pk'] = $this->swConfig['ssl_key_file'];
        }

        if(isset($sslOptions) && !empty($sslOptions))
        {
            $socketContext = stream_context_create(
                [
                    'ssl' => $sslOptions
                ]
            );
            $this->client = stream_socket_client('tls://' . $this->config['host'] . ':' . $this->config['port'], $errCode, $errStr, 60, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $this->client = stream_socket_client('tcp://' . $this->config['host'] . ':' . $this->config['port'], $errCode, $errStr, 60, STREAM_CLIENT_CONNECT);
        }

        if(!is_resource($this->client) || $errCode === SOCKET_ECONNRESET)
        {
            return false;
        }

        $this->errCode = $errCode;
        $this->errMsg = $errStr;

        if($this->errCode !=0 && $this->errCode !== SOCKET_ETIMEDOUT)
        {
            throw new RuntimeException($this->errMsg, $this->errCode);
        }

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
            if(is_resource($this->client)) {
                fclose($this->client);
            }
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

    protected function recvAck($type)
    {
        $response = $this->getCompletePacket();
        if($response === '') {
            $this->reConnect();
        }if (strlen($response) > 0) {
            if($this->config['protocol_level'] === 5) {
                return ProtocolV5::unpack($response);
            }
            return Protocol::unpack($response);
        }

        return true;
    }

    public function recv()
    {
        $response = $this->getCompletePacket();
        if(strlen($response) > 0) {
            if ($this->config['protocol_level'] === 5) {
                return ProtocolV5::unpack($response);
            }

            return Protocol::unpack($response);
        }

        return true;
    }

    public function read($length = 8192, $nb = false)
    {
        $string = '';
        $togo = $length;

        if ($nb) {
            return fread($this->client, $togo);
        }

        while (!feof($this->client) && $togo > 0) {
            $fread = fread($this->client, $togo);
            $string .= $fread;
            $togo = $length - strlen($string);
        }

        return $string;
    }

    public function getCompletePacket() {
        $packetData = '';
        $byte = $this->read(1,true);
        if($byte == '') {
            return $packetData;
        }

        $type = (int)(ord($byte) >> 4);

        if(!in_array($type, [
            Types::CONNECT,
            Types::CONNACK,
            Types::PUBLISH,
            Types::PUBACK,
            Types::PUBREC,
            Types::PUBREL,
            Types::PUBCOMP,
            Types::SUBSCRIBE,
            Types::SUBACK,
            Types::UNSUBSCRIBE,
            Types::UNSUBACK,
            Types::PINGREQ,
            Types::PINGRESP,
            Types::DISCONNECT,
            Types::AUTH
        ])) {
            throw new InvalidArgumentException('MQTT packet type error');
        }

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

    public function close(int $code = ReasonCode::NORMAL_DISCONNECTION)
    {
        $this->send(['type' => Types::DISCONNECT, 'code' => $code], false);

        return fclose($this->client);
    }
}
