<?php

namespace Bab\RabbitMq;

class VhostManager
{
    protected $credentials;
    protected $output;

    public function __construct(array $credentials, $output = null)
    {
        $this->credentials = $credentials;
        if ('/' === $this->credentials['vhost']) {
            $this->credentials['vhost'] = '%2f';
        }
        $this->output = $output;
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
        $withDl = false;
        if (isset($config['parameters']['with_dl'])) {
            $withDl = (boolean) $config['parameters']['with_dl'];
        }
        $this->log(sprintf('With DL: <info>%s</info>', $withDl ? 'true' : 'false'));

        $withUnroutable = false;
        if (isset($config['parameters']['with_unroutable'])) {
            $withUnroutable = (boolean) $config['parameters']['with_unroutable'];
        }
        $this->log(sprintf('With Unroutable: <info>%s</info>', $withUnroutable ? 'true' : 'false'));

        // Unroutable queue must be created even if not asked but with_dl is
        // true to not loose unroutable messages which enters in dl exchange
        if ($withDl || $withUnroutable) {
            $this->createUnroutable();
        }

        if ($withDl) {
            $this->createDl();
        }

        // Create all exchanges
        foreach ($config['exchanges'] as $name => $parameters) {
            $currentWithUnroutable = $withUnroutable;

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

        if (!isset($config['queues'])) {
            return;
        }

        foreach ($config['queues'] as $name => $parameters) {
            $currentWithDl = $withDl;
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

            if ($currentWithDl && !$withDl) {
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
    protected function createExchange($exchange, array $parameters = null)
    {
        $this->log(sprintf('Create exchange <info>%s</info>', $exchange));

        return $this->query('PUT', '/api/exchanges/'.$this->credentials['vhost'].'/'.$exchange, $parameters);
    }

    /**
     * createQueue
     *
     * @param string $queue
     * @param array  $parameters
     *
     * @return void
     */
    protected function createQueue($queue, $parameters)
    {
        $this->log(sprintf('Create queue <info>%s</info>', $queue));

        return $this->query('PUT', '/api/queues/'.$this->credentials['vhost'].'/'.$queue, $parameters);
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
     * query
     *
     * @param mixed $handle
     * @param mixed $method
     * @param mixed $url
     *
     * @return void
     */
    protected function query($method, $url, array $parameters = null)
    {
        $handle = $this->getHandle();

        curl_setopt($handle, CURLOPT_URL, $this->credentials['host'].$url);

        if ('GET' === $method) {
            curl_setopt($handle, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (null !== $parameters) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
        } elseif ('GET' !== $method && 'DELETE' !== $method) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, '{}');
        }

        $response = curl_exec($handle);
        if (false === $response) {
            throw new \RuntimeException(sprintf(
                'Curl error: %s',
                curl_error($handle)
            ));
        }

        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if (!in_array($httpCode, array(200, 201, 204))) {
            throw new \RuntimeException(sprintf(
                'Receive code %d instead of 200, 201 or 204. Url: %s. Body: %s',
                $httpCode,
                $url,
                $response
            ));
        }

        curl_close($handle);

        return $response;
    }

    /**
     * getHandle
     *
     * Return a correctly configured curl handle
     *
     * @return mixed
     */
    protected function getHandle()
    {
        $handle = curl_init();

        curl_setopt_array($handle, array(
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_PORT           => $this->credentials['port'],
            CURLOPT_VERBOSE        => false,
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => sprintf(
                '%s:%s',
                $this->credentials['user'],
                $this->credentials['password']
            ),
        ));

        return $handle;
    }

    protected function log($message)
    {
        if (null == $this->output) {
            return;
        }

        $this->output->writeln($message);
    }
}
