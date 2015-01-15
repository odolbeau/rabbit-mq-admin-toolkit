<?php

namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClient;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class Action implements \Bab\RabbitMq\Action
{
    use LoggerAwareTrait;

    protected $httpClient;
    protected $context;
    protected $logger;
    
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }
    
    public function startMapping()
    {
        
    }
    
    public function endMapping()
    {
    
    }
    
    public function setContext(array $context = array())
    {
        $this->context = $context;
        
        return $this;
    }
    
    protected function query($verb, $uri, array $parameters = null)
    {
        $this->ensureVhostDefined();

        return $this->httpClient->query($verb, $uri, $parameters);
    }

    protected function log($message)
    {
        $this->logger->info($message);
    }

    private function ensureVhostDefined()
    {
        $vhost = $this->getContextValue('vhost');
        if (empty($vhost)) {
            throw new \RuntimeException('Vhost must be defined');
        }
    }
    
    protected function getContextValue($key)
    {
        if (isset($this->context[$key])) {
            return $this->context[$key];
        }
        
        return null;
    }
}
