<?php
/**
 * Created by PhpStorm.
 * User: hong
 * Date: 11/3/16
 * Time: 12:01 PM
 */

namespace PhSocket;

use PhDescriptors\LinkPartsDescriptor;
use PhMessage\Request;
use PhUtils\DNSUtil;

class Socket
{
    const SOCKET_PROTOCOL_PREFIX_SSL = 'ssl://';

    const ERROR_PROXY_UNREACHABLE = 101;

    const ERROR_SOCKET_TIMEOUT = 102;

    const ERROR_SSL_NOT_SUPPORTED = 103;

    const ERROR_HOST_UNREACHABLE = 104;

    /**
     * @var resource
     */
    protected $_socket;

    public $timeout = 6;
    public $error_code;
    public $error_message;

    public $flag = STREAM_CLIENT_CONNECT;

    protected $protocol_prefix = '';

    /**
     * @var ProxyDescriptor
     */
    public $ProxyDescriptor;
    /**
     * @var LinkPartsDescriptor
     */
    public $LinkParsDescriptor;

    protected function isSSLConnection()
    {
        return $this->LinkParsDescriptor instanceof LinkPartsDescriptor && $this->LinkParsDescriptor->isSSL();
    }

    protected function isProxyConnection()
    {
        return $this->ProxyDescriptor instanceof ProxyDescriptor && $this->ProxyDescriptor->host !== null;
    }

    protected function canOpen()
    {

        if (!($this->LinkParsDescriptor instanceof LinkPartsDescriptor)) {

            $this->error_code = self::ERROR_HOST_UNREACHABLE;
            $this->error_message = "Require connection information!";
            return false;

        }

        if ($this->isSSLConnection() && !extension_loaded("openssl")) {
            $UrlParts = $this->LinkParsDescriptor;
            $this->error_code = self::ERROR_SSL_NOT_SUPPORTED;
            $this->error_message = "Error connecting to ".$UrlParts->protocol.$UrlParts->host.": SSL/HTTPS-requests not supported, extension openssl not installed.";
            return false;
        }

        return true;
    }

    public function open()
    {

        if (!$this->canOpen()) { return false;
        }

        if ($context = $this->getClientContext()) {
            $this->_socket = @stream_socket_client(
                $this->getClientRemoteURI(),
                $this->error_code,
                $this->error_message,
                $this->timeout,
                $this->flag,
                $context
            );
        }  else {
            $this->_socket = @stream_socket_client(
                $this->getClientRemoteURI(),
                $this->error_code,
                $this->error_message,
                $this->timeout,
                $this->flag
            );

        }

        return $this->checkOpened();
    }

    protected function checkOpened()
    {
        if ($this->_socket == false) {
            // If proxy not reachable
            if ($this->isProxyConnection()) {
                $this->error_code = self::ERROR_PROXY_UNREACHABLE;
                $this->error_message = "Error connecting to proxy ".$this->ProxyDescriptor->host.": Host unreachable (".$this->error_message.").";
                return false;
            }
            else
            {
                $UrlParts = $this->LinkParsDescriptor;
                $this->error_code = self::ERROR_HOST_UNREACHABLE;
                $this->error_message = "Error connecting to ".$UrlParts->protocol.$UrlParts->host.": Host unreachable (".$this->error_message.").";
                return false;
            }
        }
        return true;
    }

    protected function getClientRemoteURI()
    {

        $protocol_prefix = '';

        if ($this->isProxyConnection()) {
            $host = $this->ProxyDescriptor->host;
            $port = $this->ProxyDescriptor->port;
        } else {

            $host = DNSUtil::getIpByHostName($this->LinkParsDescriptor->host);
            $port = $this->LinkParsDescriptor->port;

            if ($this->isSSLConnection()) {
                $host = $this->LinkParsDescriptor->host;
                $protocol_prefix = self::SOCKET_PROTOCOL_PREFIX_SSL;
            }

        }

        return $protocol_prefix . $host . ':'.$port;
    }

    protected function getClientContext()
    {
        if ($this->isSSLConnection()) {
            return @stream_context_create(array('ssl' => array('peer_name' => $this->LinkParsDescriptor->host)));
        }
        return null;
    }

    public function close()
    {
        @fclose($this->_socket);
    }

    public function send($message = '')
    {
        return @fwrite($this->_socket, $message, strlen($message));
    }

    public function read($buffer = 1024)
    {
        return @fread($this->_socket, $buffer);
    }

    public function gets($buffer = 128)
    {
        return @fgets($this->_socket, $buffer);
    }

    public function setTimeOut($timeout = null)
    {

        if ($timeout) {
            $this->timeout = $timeout;
        }

        return @socket_set_timeout($this->_socket, $this->timeout);
    }

    public function getStatus()
    {
        return @socket_get_status($this->_socket);
    }

    public function checkTimeoutStatus()
    {
        $status = $this->getStatus();
        if ($status["timed_out"] == true) {
            $this->error_code = self::ERROR_SOCKET_TIMEOUT;
            $this->error_message = "Socket-stream timed out (timeout set to ".$this->timeout." sec).";
            return true;
        }
        return false;
    }

    public function isEOF()
    {
        return ($this->getStatus()["eof"] == true || feof($this->_socket) == true);
    }

    public function getUnreadBytes()
    {
        return $this->getStatus()['unread_bytes'];
    }

}
