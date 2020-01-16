<?php

namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClient;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class Action implements \Bab\RabbitMq\Action
{
    use LoggerAwareTrait;

    /** @var HttpClient */
    protected $httpClient;
    /** @var string */
    protected $vhost;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function setVhost(string $vhost): void
    {
        $this->vhost = $vhost;
    }

    protected function query(string $verb, string $uri, array $parameters = []): string
    {
        $this->ensureVhostDefined();

        return $this->httpClient->query($verb, $uri, $parameters);
    }

    protected function log(string $message): void
    {
        $this->logger->info($message);
    }

    private function ensureVhostDefined(): void
    {
        if (empty($this->vhost)) {
            throw new \RuntimeException('Vhost must be defined');
        }
    }
}
