<?php

namespace Bab\RabbitMq;

use Bab\RabbitMq\Actions\RealAction;

class VhostManager
{
    protected $credentials;
    protected $output;
    private $hasDeadLetterExchange;
    private $hasUnroutableExchange;
    private $httpClient;

    public function __construct(array $credentials, $output = null, Action $action, HttpClient $httpClient)
    {
        $this->credentials = $credentials;
        if ('/' === $this->credentials['vhost']) {
            $this->credentials['vhost'] = '%2f';
        }
        $this->output = $output;
        $this->hasDeadLetterExchange = false;
        $this->hasUnroutableExchange = false;
        $this->action = $action;
        $this->action->setVhost($this->credentials['vhost']);
        $this->httpClient = $httpClient;
    }

    /**
     * resetVhost
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
        $this->query('PUT', '/api/permissions/'.$vhost.'/'.$this->credentials['user'], array(
            'scope'     => 'client',
            'configure' => '.*',
            'write'     => '.*',
            'read'      => '.*'
        ));
    }

    /**
     * createMapping
     *
     * @param array $config
     *
     * @return void
     */
    public function createMapping(array $config)
    {
        $this->createBaseStructure($config);
        $this->createExchanges($config);
        $this->createQueues($config);
        $this->setPermissions($config);
    }
    
    private function createBaseStructure(array $config)
    {
        if (isset($config['parameters']['with_dl'])) {
            $this->hasDeadLetterExchange = (boolean) $config['parameters']['with_dl'];
        }
        $this->log(sprintf('With DL: <info>%s</info>', $this->hasDeadLetterExchange() === true ? 'true' : 'false'));
    
        if (isset($config['parameters']['with_unroutable'])) {
            $this->hasUnroutableExchange = (boolean) $config['parameters']['with_unroutable'];
        }
        $this->log(sprintf('With Unroutable: <info>%s</info>', $this->hasUnroutableExchange() === true ? 'true' : 'false'));
    
        // Unroutable queue must be created even if not asked but with_dl is
        // true to not loose unroutable messages which enters in dl exchange
        if ($this->hasDeadLetterExchange() === true || $this->hasUnroutableExchange() === true) {
            $this->createUnroutable();
        }
    
        if ($this->hasDeadLetterExchange() === true) {
            $this->createDl();
        }
    }
    
    private function hasDeadLetterExchange()
    {
        return $this->hasDeadLetterExchange;
    }
    
    private function hasUnroutableExchange()
    {
        return $this->hasUnroutableExchange;
    }
    
    private function createExchanges(array $config)
    {
        // Create all exchanges
        foreach ($config['exchanges'] as $name => $parameters) {
            $currentWithUnroutable = $this->hasUnroutableExchange();
    
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
    
    private function createQueues(array $config)
    {
        foreach ($config['queues'] as $name => $parameters) {
            $currentWithDl = $this->hasDeadLetterExchange();
            $retries = array();
    
            $bindings = $parameters['bindings'];
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
    
            if ($currentWithDl && $this->hasDeadLetterExchange() === false) {
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
                        'x-dead-letter-routing-key' => $name
                    )
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
                        'alternate-exchange' => 'unroutable'
                    )
                ));
                $this->createQueue($retryName, array(
                    'durable' => true,
                    'arguments' => array(
                        'x-message-ttl' => $retries[$i]*1000,
                        'x-dead-letter-exchange' => 'retry',
                        'x-dead-letter-routing-key' => $name
                    )
                ));
                $this->createBinding('retry', $retryName, $retryName);
                $this->createBinding('retry', $name, $name);
            }
    
    
            foreach ($bindings as $binding) {
                list ($exchange, $routingKey) = explode(':', $binding);
                $bindingName = $withDelay ? $name.'_delay_'.$delay : $name;
    
                $this->createBinding($exchange, $bindingName, $routingKey);
            }
        }
    }

    /**
     * getQueues
     *
     * @return array
     */
    public function getQueues()
    {
        $informations = json_decode($this->query('GET', '/api/queues/'.$this->credentials['vhost']), true);
        $queues = array();
        foreach ($informations as $information) {
            $queues[] = $information['name'];
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
        return $this->query('DELETE', '/api/queues/'.$this->credentials['vhost'].'/'.$queue);
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
        return $this->query('DELETE', '/api/queues/'.$this->credentials['vhost'].'/'.$queue.'/contents');
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
        $this->action->createExchange($exchange, $parameters);
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
        $this->action->createQueue($queue, $parameters);
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
    protected function createBinding($exchange, $queue, $routingKey = null)
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $exchange,
            $queue,
            null !== $routingKey ? $routingKey : 'none'
        ));

        $parameters = null;
        if (null !== $routingKey) {
            $parameters = array(
                'routing_key' => $routingKey,
            );
        }

        return $this->query('POST', '/api/bindings/'.$this->credentials['vhost'].'/e/'.$exchange.'/q/'.$queue, $parameters);
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
            'auto_delete' => 'false',
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
                'alternate-exchange' => 'unroutable'
            )
        ));
    }
    
    /**
     * setPermissions
     *
     * @param array $permissions
     *
     * @return void
     */
    protected function setPermissions(array $config = array())
    {
        if (!empty($config['permissions'])) {
            foreach($config['permissions'] as $user => $userPermissions)
            {
                $parameters = $this->extractPermissions($userPermissions);
                $this->query('PUT', '/api/permissions/'.$this->credentials['vhost'].'/'.$user, $parameters);
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
            foreach(array_keys($permissions) as $permission) {
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
        $this->httpClient->query($method, $url, $parameters);
    }

    protected function log($message)
    {
        if (null == $this->output) {
            return;
        }

        $this->output->writeln($message);
    }
}
