<?php

namespace GuzzleTor\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleTor\Middleware;
use GuzzleTor\TorNewIdentityException;
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    public function testInitialize()
    {
        $mock = new MockHandler([
            new Response(200),
        ]);

        $stack = new HandlerStack();
        $stack->setHandler($mock);
        $stack->push(Middleware::tor());
        $client = new Client(['handler' => $stack]);

        $client->get('http://xmh57jrzrnw6insl.onion/');

        $this->assertEquals('127.0.0.1:9050', $mock->getLastOptions()['proxy']);
        $this->assertEquals(
            CURLPROXY_SOCKS5_HOSTNAME,
            $mock->getLastOptions()['curl'][CURLOPT_PROXYTYPE]
        );
    }

    public function testIsTorUsed()
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::tor());
        $client = new Client(['handler' => $stack]);

        $response = $client->get('https://check.torproject.org/');
        $torInfo = self::extractTorInfo($response->getBody());

        $this->assertEquals(true, $torInfo['tor']);
    }

    public function testNewIdentity()
    {
        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::tor());
        $client = new Client(['handler' => $stack]);

        $response = $client->get('https://check.torproject.org/');
        $firstIp = self::extractTorInfo($response->getBody())['ip'];

        $response = $client->get('https://check.torproject.org/', [
            'tor_new_identity'           => true,
            'tor_new_identity_sleep'     => 15,
            'tor_new_identity_exception' => true
        ]);
        $secondIp = self::extractTorInfo($response->getBody())['ip'];

        $this->assertNotEquals($firstIp, $secondIp);
    }

    public function testExceptionWhileNewIdentity()
    {
        $this->expectException(TorNewIdentityException::class);

        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::tor('127.0.0.1:9050', 'not-existed-host:9051'));
        $client = new Client(['handler' => $stack]);

        // Throw TorNewIdentityException because of wrong tor control host
        $client->get('https://check.torproject.org/', [
            'tor_new_identity' => true,
            'tor_new_identity_exception' => true
        ]);
    }

    /**
     * Parse content of the page https://check.torproject.org/
     * @param string $content
     * @return array [
     *   tor: bool Is Tor used
     *   ip: string
     * ]
     */
    private static function extractTorInfo($content)
    {
        $result = ['tor' => false, 'ip' => null];
        if (preg_match('/<h1 class="(on|off|not)/', $content, $matches)) {
            $result['tor'] = 'on' == in_array($matches[1], ['on', 'not']);
        }
        if (preg_match('/<strong>([\d.]+)<\/strong>/', $content, $matches)) {
            $result['ip'] = $matches[1];
        }
        return $result;
    }
}
