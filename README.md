GuzzleTor
=========

This Guzzle middleware allows to use Tor client as a proxy

```php
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
```

## Options
### General

```php
Middleware::tor($proxy, $torControl)
```

- *$proxy* is tor socks5 port, by default '127.0.0.1:9050'
- *$torControl* is Tor control port, by default '127.0.0.1:9051'. Set if you want to 
change ip (clean circuits)

### Request options

```php
$client->get('https://check.torproject.org/', [
    'tor_new_identity'         => true,
    'tor_new_identity_sleep'   => 15,
    'tor_new_identity_timeout' => 3,
    'tor_control_password'     => 'password' 
]);
```

- *tor_new_identity* Change identity/IP (clean circuits) before request. If is set we send NEWNYM
signal to Tor client. Please be aware, that this method does not guarantee 
that identity will be changed soon
- *tor_new_identity_sleep* pause (seconds) between ip change identity and request
- *tor_new_identity_timeout* Timeout for Tor control connection
- *tor_control_password* Tor control password
