<?php

namespace Bab\RabbitMq\Specification;

class DelayExchangeCanBeCreated implements Specification
{
    public function isSatisfiedBy($config)
    {
        if (!isset($config['queues']) || 0 === count($config['queues'])) {
            return false;
        }

        foreach ($config['queues'] as $name => $parameters) {
            if (isset($parameters['delay'])) {
                return true;
            }
        }

        return false;
    }
}
