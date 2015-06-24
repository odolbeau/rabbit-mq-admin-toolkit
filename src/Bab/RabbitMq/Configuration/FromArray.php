<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\Configuration;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;

class FromArray implements Configuration
{
    private $config;
    private $vhost;
    private $hasDeadLetterExchange;
    private $hasUnroutableExchange;

    public function __construct($configuration)
    {
        $this->vhost = key($configuration);
        $this->config = current($configuration);

        $parameters = $this['parameters'];

        $this->hasDeadLetterExchange = false;
        $this->hasUnroutableExchange = false;
        if (isset($parameters['with_dl'])) {
            $this->hasDeadLetterExchange = (bool) $parameters['with_dl'];
        }
        if (isset($parameters['with_dl'])) {
            $this->hasUnroutableExchange = (bool) $parameters['with_unroutable'];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getVhost()
    {
        return $this->vhost;
    }

    /**
     * {@inheritDoc}
     */
    public function hasDeadLetterExchange()
    {
        return $this->hasDeadLetterExchange;
    }

    /**
     * {@inheritDoc}
     */
    public function hasUnroutableExchange()
    {
        return $this->hasUnroutableExchange;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] :  null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('You shall not update configuration');
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('No need to unset');
    }
}
