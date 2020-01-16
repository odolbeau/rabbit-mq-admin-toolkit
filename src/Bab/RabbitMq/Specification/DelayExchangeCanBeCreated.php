<?php

namespace Bab\RabbitMq\Specification;

use Bab\RabbitMq\Configuration;

class DelayExchangeCanBeCreated implements Specification
{
    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(Configuration $config): bool
    {
        if (!isset($config['queues']) || empty($config['queues'])) {
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
