<?php

namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClient;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class Action implements \Bab\RabbitMq\Action
{
    use LoggerAwareTrait;

    protected $httpClient;
    protected $vhost;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }

    public function setVhost($vhost)
    {
        $this->vhost = $vhost;
    }

    protected function query($verb, $uri, $parameters)
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
        if (empty($this->vhost)) {
            throw new \RuntimeException('Vhost must be defined');
        }
    }
}
