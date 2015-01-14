<?php
namespace Bab\RabbitMq\HttpClient;

use Bab\RabbitMq\HttpClient;
use Bab\RabbitMq\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;

class GuzzleClient implements HttpClient
{
    private $scheme;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $client;
    private $dryRunModeEnabled;
    
    public function __construct($scheme, $host, $port, $user, $pass)
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        
        $this->client = new Client([
            'base_url' => $this->formatBaseUrl(),
            'defaults' => [
                'auth' => [$this->user, $this->pass],
                'headers' => ['Content-Type' => 'application/json']
            ]
        ]);
    }
    
    private function formatBaseUrl()
    {
        $scheme = $this->scheme;
        $host = trim($this->host);
        
        if (preg_match('~^(?<scheme>https?://).~', $host) === 0) {
            if (empty($scheme)) {
                $scheme = 'http';
            }
            $scheme = trim($scheme). '://';
        }
        
        return sprintf(
            '%s%s:%d',
            $scheme,
            $host,
            $this->port
        );
    }
    
    public function query($verb, $uri, array $parameters = null)
    {
        if ($this->dryRunModeEnabled === self::DRYRUN_ENABLED && $verb !== 'GET') {
            throw new \RuntimeException('Dry run mode must only accept GET requests');
        }
        
        if ($verb === 'GET' || $verb === 'DELETE') {
            $request = $this->client->createRequest($verb, $uri, array('body' => '{}'));
        } else {
            if (!empty($parameters)) {
                $parameters = json_encode($parameters);
            }
            $request = $this->client->createRequest($verb, $uri, array('body' => $parameters));
        }
        
        try {
            $response = $this->client->send($request);
        } catch(\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
        }
        
        $httpCode = $response->getStatusCode();
        
        if ($this->dryRunModeEnabled === self::DRYRUN_NOT_ENABLED && !in_array($httpCode, array(200, 201, 204))) {
            throw new \RuntimeException(sprintf(
                'Receive code %d instead of 200, 201 or 204. Url: %s. Body: %s',
                $httpCode,
                $uri,
                $response
            ));
        }
        
        return new Response($httpCode, $response->getBody());
    }
    
    public function setDryRunMode($enabled = self::DRYRUN_NOT_ENABLED)
    {
        $this->dryRunModeEnabled = $enabled;
    }
}