<?php

namespace Bab\RabbitMq;

use Bab\RabbitMq\Specification\DeadLetterExchangeCanBeCreated;
use Bab\RabbitMq\Specification\DelayExchangeCanBeCreated;
use Bab\RabbitMq\Specification\RetryExchangeCanBeCreated;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class VhostManager
{
    use LoggerAwareTrait;

    private $credentials;
    private $action;
    private $httpClient;

    public function __construct(array $credentials, Action $action, HttpClient $httpClient)
    {
        $this->credentials = $credentials;
        $this->credentials['vhost'] = str_replace('/', '%2f', $this->credentials['vhost']);
        $this->action = $action;
        $this->action->setVhost($this->credentials['vhost']);
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }

    public function resetVhost(): void
    {
        $vhost = $this->credentials['vhost'];
        $this->log(sprintf('Delete vhost: <info>%s</info>', $vhost));
        try {
            $this->query('DELETE', '/api/vhosts/'.$vhost);
        } catch (\Exception $e) {
        }
        $this->log(sprintf('Create vhost: <info>%s</info>', $vhost));
        $this->query('PUT', '/api/vhosts/'.$vhost);
        $this->log(sprintf(
            'Grant all permission for <info>%s</info> on vhost <info>%s</info>',
            $this->credentials['user'],
            $vhost
        ));
        $this->query('PUT', '/api/permissions/'.$vhost.'/'.$this->credentials['user'], [
            'scope' => 'client',
            'configure' => '.*',
            'write' => '.*',
            'read' => '.*',
        ]);
    }

    public function createMapping(Configuration $config): void
    {
        $this->createBaseStructure($config);
        $this->createExchanges($config);
        $this->createQueues($config);
        $this->setPermissions($config);
    }

    public function getQueues(): array
    {
        $informations = json_decode($this->query('GET', '/api/queues/'.$this->credentials['vhost']), true);
        $queues = [];
        foreach ($informations as $information) {
            $queues[] = $information['name'];
        }

        return $queues;
    }

    public function remove(string $queue): void
    {
        $this->query('DELETE', '/api/queues/'.$this->credentials['vhost'].'/'.$queue);
    }

    public function purge(string $queue): void
    {
        $this->query('DELETE', '/api/queues/'.$this->credentials['vhost'].'/'.$queue.'/contents');
    }

    protected function createExchange(string $exchange, array $parameters = []): void
    {
        $this->action->createExchange($exchange, $parameters);
    }

    protected function createQueue(string $queue, array $parameters = []): void
    {
        $this->action->createQueue($queue, $parameters);
    }

    protected function createBinding(string $exchange, string $queue, string $routingKey = null, array $arguments = []): void
    {
        $this->action->createBinding($exchange, $queue, $routingKey, $arguments);
    }

    protected function createUnroutable(): void
    {
        $this->createExchange('unroutable', [
            'type' => 'fanout',
            'durable' => true,
        ]);
        $this->createQueue('unroutable', [
            'auto_delete' => 'false',
            'durable' => true,
        ]);
        $this->createBinding('unroutable', 'unroutable');
    }

    protected function createDlExchange(): void
    {
        $this->createExchange('dl', [
            'type' => 'direct',
            'durable' => true,
            'arguments' => [
                'alternate-exchange' => 'unroutable',
            ],
        ]);
    }

    protected function createRetryExchange(): void
    {
        $this->createExchange('retry', [
            'durable' => true,
            'type' => 'topic',
            'arguments' => [
                'alternate-exchange' => 'unroutable',
            ],
        ]);
    }

    protected function createDelayExchange(): void
    {
        $this->createExchange('delay', [
            'durable' => true,
        ]);
    }

    protected function setPermissions(Configuration $config): void
    {
        if (!empty($config['permissions'])) {
            foreach ($config['permissions'] as $user => $userPermissions) {
                $parameters = $this->extractPermissions($userPermissions);
                $this->action->setPermissions($user, $parameters);
            }
        }
    }

    private function extractPermissions(array $userPermissions = []): array
    {
        $permissions = [
            'configure' => '',
            'read' => '',
            'write' => '',
        ];

        if (!empty($userPermissions)) {
            foreach (array_keys($permissions) as $permission) {
                if (!empty($userPermissions[$permission])) {
                    $permissions[$permission] = $userPermissions[$permission];
                }
            }
        }

        return $permissions;
    }

    protected function query(string $method, string $url, array $parameters = null): string
    {
        return $this->httpClient->query($method, $url, $parameters);
    }

    protected function log(string $message): void
    {
        $this->logger->info($message);
    }

    private function createBaseStructure(Configuration $config): void
    {
        $this->log(sprintf('With DL: <info>%s</info>', true === $config->hasDeadLetterExchange() ? 'true' : 'false'));

        $this->log(sprintf('With Unroutable: <info>%s</info>', true === $config->hasUnroutableExchange() ? 'true' : 'false'));

        // Unroutable queue must be created even if not asked but with_dl is
        // true to not loose unroutable messages which enters in dl exchange
        if (true === $config->hasDeadLetterExchange() || true === $config->hasUnroutableExchange()) {
            $this->createUnroutable();
        }

        if ((new DeadLetterExchangeCanBeCreated())->isSatisfiedBy($config)) {
            $this->createDlExchange();
        }

        if ((new RetryExchangeCanBeCreated())->isSatisfiedBy($config)) {
            $this->createRetryExchange();
        }

        if ((new DelayExchangeCanBeCreated())->isSatisfiedBy($config)) {
            $this->createDelayExchange();
        }
    }

    private function createExchanges(Configuration $config): void
    {
        foreach ($config['exchanges'] as $name => $parameters) {
            $currentWithUnroutable = $config->hasUnroutableExchange();

            if (isset($parameters['with_unroutable'])) {
                $currentWithUnroutable = (bool) $parameters['with_unroutable'];
                unset($parameters['with_unroutable']);
            }

            if ($currentWithUnroutable && !isset($config['arguments']['alternate-exchange'])) {
                if (!isset($parameters['arguments'])) {
                    $parameters['arguments'] = [];
                }
                $parameters['arguments']['alternate-exchange'] = 'unroutable';
            }

            $this->createExchange($name, $parameters);
        }
    }

    private function createQueues(Configuration $config): void
    {
        if (!isset($config['queues']) || 0 === \count($config['queues'])) {
            return;
        }

        foreach ($config['queues'] as $name => $parameters) {
            $currentWithDl = $config->hasDeadLetterExchange();
            $retries = [];

            $bindings = [];

            if (isset($parameters['bindings']) && \is_array($parameters['bindings'])) {
                $bindings = $parameters['bindings'];
            }
            unset($parameters['bindings']);

            if (isset($parameters['with_dl'])) {
                $currentWithDl = (bool) $parameters['with_dl'];
                unset($parameters['with_dl']);
            }

            if (isset($parameters['retries'])) {
                $retries = $parameters['retries'];
                $currentWithDl = true;
                unset($parameters['retries']);
            }

            if ($currentWithDl && !isset($config['arguments']['x-dead-letter-exchange'])) {
                if (!isset($parameters['arguments'])) {
                    $parameters['arguments'] = [];
                }

                $parameters['arguments']['x-dead-letter-exchange'] = 'dl';
                $parameters['arguments']['x-dead-letter-routing-key'] = $name;
            }

            $this->createQueue($name, $parameters);

            $delay = null;
            if (isset($parameters['delay'])) {
                $delay = (int) $parameters['delay'];

                $this->createQueue($name.'_delay_'.$delay, [
                    'durable' => true,
                    'arguments' => [
                        'x-message-ttl' => $delay,
                        'x-dead-letter-exchange' => 'delay',
                        'x-dead-letter-routing-key' => $name,
                    ],
                ]);

                $this->createBinding('delay', $name, $name);

                unset($parameters['delay']);
            }

            if ($currentWithDl) {
                $this->createQueue($name.'_dl', [
                        'durable' => true,
                ]);

                $this->createBinding('dl', $name.'_dl', $name);
            }

            $retriesQueues = [];
            for ($i = 0; $i < \count($retries); ++$i) {
                if (0 === $i) {
                    $this->createBinding('retry', $name, $name);
                }

                $retryQueueName = $name.'_retry_'.$retries[$i];

                if (!\in_array($retryQueueName, $retriesQueues)) {
                    $this->createQueue($retryQueueName, [
                        'durable' => true,
                        'arguments' => [
                            'x-message-ttl' => $retries[$i] * 1000,
                            'x-dead-letter-exchange' => 'retry',
                            'x-dead-letter-routing-key' => $name,
                        ],
                    ]);

                    $retriesQueues[] = $retryQueueName;
                }

                $retryRoutingkey = $name.'_retry_'.($i + 1);
                $this->createBinding('retry', $retryQueueName, $retryRoutingkey);
            }

            foreach ($bindings as $binding) {
                if (!\is_array($binding) && false !== strpos($binding, ':')) {
                    $parts = explode(':', $binding);
                    $binding = [
                        'exchange' => $parts[0],
                        'routing_key' => $parts[1],
                    ];
                }
                $this->createUserBinding($name, $binding, $delay);
            }
        }
    }

    private function createUserBinding(string $queueName, array $bindingDefinition, int $delay = null): void
    {
        $defaultParameterValues = [
            'routing_key' => null,
            'x-match' => 'all',
            'matches' => [],
        ];

        $parameters = array_merge($defaultParameterValues, $bindingDefinition);

        if (!isset($parameters['exchange'])) {
            throw new \InvalidArgumentException(sprintf('Exchange is missing in binding for queue %s', $queueName));
        }

        $arguments = [];
        if (!empty($parameters['matches'])) {
            $arguments = $parameters['matches'];
            $arguments['x-match'] = $parameters['x-match'];
        }

        $bindingName = null !== $delay ? $queueName.'_delay_'.$delay : $queueName;

        $this->createBinding($parameters['exchange'], $bindingName, $parameters['routing_key'], $arguments);
    }
}
