<?php
namespace GuzzleTor;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\RequestInterface;

defined('CURLPROXY_SOCKS5_HOSTNAME') or define('CURLPROXY_SOCKS5_HOSTNAME', 7);

class Middleware
{
    const TOR_OK = 250;

    /**
     * This middleware allows to use Tor client as a proxy
     *
     * @param string $proxy Tor socks5 proxy host:port
     * @param string $torControl Tor control host:port
     * @return callable
     */
    public static function tor($proxy = '127.0.0.1:9050', $torControl = '127.0.0.1:9051')
    {
        return function (callable $handler) use ($proxy, $torControl) {
            return function (
                RequestInterface $request,
                array $options
            ) use ($handler, $proxy, $torControl) {
                $options = array_replace_recursive($options, [
                    'proxy' => $proxy,
                    'curl'  => [
                        CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME
                    ]
                ]);

                if (@$options['tor_new_identity']) {
                    try {
                        self::requireNewTorIdentity($torControl, $options);
                    } catch (GuzzleException $e) {
                        if (@$options['tor_new_identity_exception']) {
                            throw $e;
                        }
                    }
                }

                return $handler($request, $options);
            };
        };
    }

    private static function requireNewTorIdentity($torControl, $options)
    {
        list($ip, $port) = explode(':', $torControl);

        $password = @$options['tor_control_password']     ?: '';
        $timeout  = @$options['tor_new_identity_timeout'] ?: null;
        $sleep    = @$options['tor_new_identity_sleep']   ?: 0;

        $socket = @fsockopen($ip, $port, $errNo, $errStr, $timeout);
        if (!$socket) {
            throw new TorNewIdentityException("Could not connect to Tor client on $torControl: $errNo $errStr");
        }

        fputs($socket, "AUTHENTICATE \"$password\"\r\n");
        $response = fread($socket, 1024);
        $code = explode(' ', $response, 2)[0];
        if (self::TOR_OK != $code) {
            throw new TorNewIdentityException("Could not authenticate on Tor client, response: $response");
        }

        fputs($socket, "signal NEWNYM\r\n");
        $response = fread($socket, 1024);
        $code = explode(' ', $response, 2)[0];
        if (self::TOR_OK != $code) {
            throw new TorNewIdentityException("Could not get new identity, response: $response");
        }

        fclose($socket);

        if ($sleep) {
            sleep($sleep);
        }
    }
}