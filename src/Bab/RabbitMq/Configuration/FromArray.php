<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\Configuration;

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
        if (isset($parameters['with_unroutable'])) {
            $this->hasUnroutableExchange = (bool) $parameters['with_unroutable'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVhost()
    {
        return $this->vhost;
    }

    /**
     * {@inheritdoc}
     */
    public function hasDeadLetterExchange()
    {
        return $this->hasDeadLetterExchange;
    }

    /**
     * {@inheritdoc}
     */
    public function hasUnroutableExchange()
    {
        return $this->hasUnroutableExchange;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('You shall not update configuration');
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('No need to unset');
    }
}
