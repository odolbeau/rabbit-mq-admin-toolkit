<?php

namespace Bab\RabbitMq;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class VhostManager
{
    use LoggerAwareTrait;

    protected $credentials;
    private $httpClient;
    private $config;

    public function __construct(array $context, Action $action, HttpClient $httpClient)
    {
        $this->credentials = $context;
        if ('/' === $this->credentials['vhost']) {
            $this->credentials['vhost'] = '%2f';
        }

        $this->action = $action;
        $this->action->setContext($context);
        
        $this->httpClient = $httpClient;
        $this->logger = new NullLogger();
    }

    /**
     * resetVhost
     *
     * @return void
     */
    public function resetVhost()
    {
        $this->action->resetVhost();
    }

    /**
     * createMapping
     *
     * @param array $config
     *
     * @return void
     */
    public function createMapping(Configuration $config)
    {
        $this->action->startMapping();
        
        $this->createBaseStructure($config);
        $this->createExchanges($config);
        $this->createQueues($config);
        $this->setPermissions($config);
        
        $this->action->endMapping();
    }

    private function createBaseStructure(Configuration $config)
    {
        // Unroutable queue must be created even if not asked but with_dl is
        // true to not loose unroutable messages which enters in dl exchange
        if ($config->hasDeadLetterExchange() === true || $config->hasUnroutableExchange() === true) {
            $this->createUnroutable();
        }

        if ($config->hasDeadLetterExchange() === true) {
            $this->createDl();
        }
    }

    private function createExchanges(Configuration $config)
    {
        foreach ($config['exchanges'] as $name => $parameters) {
            $currentWithUnroutable = $config->hasUnroutableExchange();

            if (isset($parameters['with_unroutable'])) {
                $currentWithUnroutable = (boolean) $parameters['with_unroutable'];
                unset($parameters['with_unroutable']);
            }

            if ($currentWithUnroutable && !isset($config['arguments']['alternate-exchange'])) {
                if (!isset($parameters['arguments'])) {
                    $parameters['arguments'] = array();
                }
                $parameters['arguments']['alternate-exchange'] = 'unroutable';
            }

            $this->createExchange($name, $parameters);
        }
    }

    private function createQueues(Configuration $config)
    {
        foreach ($config['queues'] as $name => $parameters) {
            $currentWithDl = $config->hasDeadLetterExchange();
            $retries = array();

            $bindings = array();
            
            if (isset($parameters['bindings'])) {
                $bindings = $parameters['bindings'];
            }
            unset($parameters['bindings']);

            if (isset($parameters['with_dl'])) {
                $currentWithDl = (boolean) $parameters['with_dl'];
                unset($parameters['with_dl']);
            }

            if (isset($parameters['retries'])) {
                $retries = $parameters['retries'];
                $currentWithDl = true;
                unset($parameters['retries']);
            }
            
            if ($currentWithDl === true && $config->hasDeadLetterExchange() === false) {
                $this->createDl();
            }

            if ($currentWithDl && !isset($config['arguments']['x-dead-letter-exchange'])) {
                if (!isset($parameters['arguments'])) {
                    $parameters['arguments'] = array();
                }

                $parameters['arguments']['x-dead-letter-exchange'] = 'dl';
                $parameters['arguments']['x-dead-letter-routing-key'] = $name;
            }

            $this->createQueue($name, $parameters);

            $withDelay = false;
            if (isset($parameters['delay'])) {
                $withDelay = true;
                $delay = (int) $parameters['delay'];
                $this->createExchange('delay', array(
                    'durable' => true,
                ));

                $this->createQueue($name.'_delay_'.$delay, array(
                    'durable' => true,
                    'arguments' => array(
                        'x-message-ttl' => $delay,
                        'x-dead-letter-exchange' => 'delay',
                        'x-dead-letter-routing-key' => $name,
                    ),
                ));

                $this->createBinding('delay', $name, $name);

                unset($parameters['delay']);
            }

            if ($currentWithDl) {
                $this->createQueue($name.'_dl', array(
                    'durable' => true,
                ));

                $this->createBinding('dl', $name.'_dl', $name);
            }

            for ($i = 0; $i < count($retries); $i++) {
                $retryName = $name.'_retry_'.($i+1);
                $this->createExchange('retry', array(
                    'durable' => true,
                    'type'    => 'topic',
                    'arguments' => array(
                        'alternate-exchange' => 'unroutable',
                    ),
                ));
                $this->createQueue($retryName, array(
                    'durable' => true,
                    'arguments' => array(
                        'x-message-ttl' => $retries[$i]*1000,
                        'x-dead-letter-exchange' => 'retry',
                        'x-dead-letter-routing-key' => $name,
                    ),
                ));
                $this->createBinding('retry', $retryName, $retryName);
                $this->createBinding('retry', $name, $name);
            }

            foreach ($bindings as $binding) {
                if (!is_array($binding) && false !== strpos($binding, ':')) {
                    $parts = explode(':', $binding);
                    $binding = [
                        'exchange'    => $parts[0],
                        'routing_key' => $parts[1],
                    ];
                }
                $this->createUserBinding($name, $binding, $withDelay ? $delay : false);
            }
        }
    }

    private function createUserBinding($queueName, array $bindingDefinition, $delay = false)
    {
        $defaultParameterValues = array(
            'routing_key' => null,
            'x-match' => 'all',
            'matches' => array(),
        );

        $parameters = array_merge($defaultParameterValues, $bindingDefinition);

        if (! isset($parameters['exchange'])) {
            throw new \InvalidArgumentException(sprintf(
                'Exchange is missing in binding for queue %s',
                $queueName
            ));
        }

        $arguments = array();
        if (! empty($parameters['matches'])) {
            $arguments = $parameters['matches'];
            $arguments['x-match'] = $parameters['x-match'];
        }

        $bindingName = $delay !== false ? $queueName.'_delay_'.$delay : $queueName;

        $this->createBinding($parameters['exchange'], $bindingName, $parameters['routing_key'], $arguments);
    }

    /**
     * getQueues
     *
     * @return array
     */
    public function getQueues()
    {
        $response = $this->query('GET', '/api/queues/'.$this->credentials['vhost']);

        $queues = array();
        if ($response instanceof Response) {
            $informations = json_decode($response->body, true);
            foreach ($informations as $information) {
                $queues[] = $information['name'];
            }
        }
        
        return $queues;
    }

    /**
     * remove
     *
     * @param string $queue
     *
     * @return void
     */
    public function remove($queue)
    {
        return $this->action->remove($queue);
    }

    /**
     * purge
     *
     * @param string $queue
     *
     * @return void
     */
    public function purge($queue)
    {
        return $this->action->purge($queue);
    }

    /**
     * createExchange
     *
     * @param string $exchange
     * @param array  $parameters
     *
     * @return void
     */
    protected function createExchange($exchange, array $parameters = array())
    {
        return $this->action->createExchange($exchange, $parameters);
    }

    /**
     * createQueue
     *
     * @param string $queue
     * @param array  $parameters
     *
     * @return void
     */
    protected function createQueue($queue, array $parameters = array())
    {
        return $this->action->createQueue($queue, $parameters);
    }

    /**
     * createBinding
     *
     * @param string $exchange
     * @param string $queue
     * @param string $routingKey
     *
     * @return void
     */
    protected function createBinding($exchange, $queue, $routingKey = null, array $arguments = array())
    {
        return $this->action->createBinding($exchange, $queue, $routingKey, $arguments);
    }

    /**
     * createUnroutable
     *
     * @return void
     */
    protected function createUnroutable()
    {
        $this->createExchange('unroutable', array(
            'type'    => 'fanout',
            'durable' => true,
        ));
        $this->createQueue('unroutable', array(
            'auto_delete' => false,
            'durable'     => true,
        ));
        $this->createBinding('unroutable', 'unroutable');
    }

    /**
     * createDl
     *
     * @return void
     */
    protected function createDl()
    {
        return $this->createExchange('dl', array(
            'type'      => 'direct',
            'durable'   => true,
            'arguments' => array(
                'alternate-exchange' => 'unroutable',
            ),
        ));
    }

    /**
     * setPermissions
     *
     * @param array $permissions
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
     * extractPermissions
     *
     * @param array $userPermissions
     *
     * @return void
     */
    private function extractPermissions(array $userPermissions = array())
    {
        $permissions = array(
            'configure' => '',
            'read' => '',
            'write' => '',
        );

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
     * query
     *
     * @param mixed $method
     * @param mixed $url
     * @param array $parameters
     *
     * @return response body
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
