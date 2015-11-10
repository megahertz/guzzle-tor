<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleTor\Middleware;

require 'vendor/autoload.php';

$myIp = get_tor_ip();
echo "Your IP address appears to be $myIp\n";

function get_tor_ip()
{
    $stack = new HandlerStack();
    $stack->setHandler(new CurlHandler());
    $stack->push(Middleware::tor());
    $client = new Client(['handler' => $stack]);

    $response = $client->get('https://check.torproject.org/');

    if (preg_match('/<strong>([\d.]+)<\/strong>/', $response->getBody(), $matches)) {
        return $matches[1];
    } else {
        return null;
    }
}