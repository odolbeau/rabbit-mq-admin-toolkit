<?php

namespace Bab\RabbitMq\Specification;

class RetryExchangeCanBeCreated implements Specification
{
    public function isSatisfiedBy($config)
    {
        if (!isset($config['queues']) || empty($config['queues'])) {
            return false;
        }

        foreach ($config['queues'] as $name => $parameters) {
            if (isset($parameters['retries'])) {
                return true;
            }
        }

        return false;
    }
}
