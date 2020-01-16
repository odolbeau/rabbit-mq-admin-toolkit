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

    protected $credentials;
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

    /**
     * Resets vhost.
     *
     * @return void
     */
    public function resetVhost()
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

    /**
     * Creates mapping.
     *
     * @return void
     */
    public function createMapping(Configuration $config)
    {
        $this->createBaseStructure($config);
        $this->createExchanges($config);
        $this->createQueues($config);
        $this->setPermissions($config);
    }

    private function createBaseStructure(Configuration $config)
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

    private function createExchanges(Configuration $config)
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

    private function createQueues(Configuration $config)
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

            $withDelay = false;
            if (isset($parameters['delay'])) {
                $withDelay = true;
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
                $this->createUserBinding($name, $binding, $withDelay ? $delay : false);
            }
        }
    }

    private function createUserBinding($queueName, array $bindingDefinition, $delay = false)
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

        $bindingName = false !== $delay ? $queueName.'_delay_'.$delay : $queueName;

        $this->createBinding($parameters['exchange'], $bindingName, $parameters['routing_key'], $arguments);
    }

    /**
     * getQueues.
     *
     * @return array
     */
    public function getQueues()
    {
        $informations = json_decode($this->query('GET', '/api/queues/'.$this->credentials['vhost']), true);
        $queues = [];
        foreach ($informations as $information) {
            $queues[] = $information['name'];
        }

        return $queues;
    }

    /**
     * Publish a message into a specific queue
     * related to the current vhost.
     *
     * @param string $exchangeName
     * @param string $routingKey
     * @param string $message
     *
     * @throws \RuntimeException if an error occured during the publication
     *
     * @deprecated
     */
    public function publishMessage($exchangeName, $routingKey, $message)
    {
        @trigger_error('Sending messages using the VhostManager is deprecated. Use Swarrot instead, which has better performance when sending multiple messages.', E_USER_DEPRECATED);

        $informations = $this->query('POST', sprintf(
            '/api/exchanges/%s/%s/publish',
            $this->credentials['vhost'],
            $exchangeName
        ), [
            'properties' => [],
            'routing_key' => $routingKey,
            'payload' => $message,
            'payload_encoding' => 'string',
        ]);

        $decodedInformations = json_decode($informations, true);

        if (isset($decodedInformations['routed']) && true === $decodedInformations['routed']) {
            return;
        }

        throw new \RuntimeException('Unable to send that message into rabbit.');
    }

    /**
     * remove.
     *
     * @param string $queue
     *
     * @return void
     */
    public function remove($queue)
    {
        $this->query('DELETE', '/api/queues/'.$this->credentials['vhost'].'/'.$queue);
    }

    /**
     * purge.
     *
     * @param string $queue
     *
     * @return void
     */
    public function purge($queue)
    {
        $this->query('DELETE', '/api/queues/'.$this->credentials['vhost'].'/'.$queue.'/contents');
    }

    /**
     * createExchange.
     *
     * @param string $exchange
     *
     * @return void
     */
    protected function createExchange($exchange, array $parameters = [])
    {
        $this->action->createExchange($exchange, $parameters);
    }

    /**
     * createQueue.
     *
     * @param string $queue
     *
     * @return void
     */
    protected function createQueue($queue, array $parameters = [])
    {
        $this->action->createQueue($queue, $parameters);
    }

    /**
     * createBinding.
     *
     * @param string $exchange
     * @param string $queue
     * @param string $routingKey
     *
     * @return void
     */
    protected function createBinding($exchange, $queue, $routingKey = null, array $arguments = [])
    {
        $this->action->createBinding($exchange, $queue, $routingKey, $arguments);
    }

    /**
     * createUnroutable.
     *
     * @return void
     */
    protected function createUnroutable()
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

    /**
     * @return void
     */
    protected function createDlExchange()
    {
        $this->createExchange('dl', [
            'type' => 'direct',
            'durable' => true,
            'arguments' => [
                'alternate-exchange' => 'unroutable',
            ],
        ]);
    }

    /**
     * @return void
     */
    protected function createRetryExchange()
    {
        $this->createExchange('retry', [
            'durable' => true,
            'type' => 'topic',
            'arguments' => [
                'alternate-exchange' => 'unroutable',
            ],
        ]);
    }

    /**
     * @return void
     */
    protected function createDelayExchange()
    {
        $this->createExchange('delay', [
            'durable' => true,
        ]);
    }

    /**
     * setPermissions.
     *
     * @return void
     */
    protected function setPermissions(Configuration $config)
    {
        if (!empty($config['permissions'])) {
            foreach ($config['permissions'] as $user => $userPermissions) {
                $parameters = $this->extractPermissions($userPermissions);
                $this->action->setPermissions($user, $parameters);
            }
        }
    }

    /**
     * extractPermissions.
     *
     * @return array
     */
    private function extractPermissions(array $userPermissions = [])
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

    /**
     * query.
     *
     * @param string $method
     * @param string $url
     *
     * @return string response body
     */
    protected function query($method, $url, array $parameters = null)
    {
        return $this->httpClient->query($method, $url, $parameters);
    }

    protected function log($message)
    {
        $this->logger->info($message);
    }
}
