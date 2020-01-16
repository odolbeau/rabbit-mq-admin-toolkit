<?php

namespace Bab\RabbitMq\Specification;

use Bab\RabbitMq\Configuration;

class DeadLetterExchangeCanBeCreated implements Specification
{
    /**
     * {@inheritdoc}
     */
    public function isSatisfiedBy(Configuration $config): bool
    {
        if (true === $config->hasDeadLetterExchange()) {
            return true;
        }

        if (!isset($config['queues']) || empty($config['queues'])) {
            return false;
        }

        foreach ($config['queues'] as $name => $parameters) {
            if (isset($parameters['with_dl']) && true === (bool) $parameters['with_dl']) {
                return true;
            }

            if (isset($parameters['retries'])) {
                return true;
            }
        }

        return false;
    }
}
