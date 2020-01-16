<?php

namespace Bab\RabbitMq\Specification;

class DeadLetterExchangeCanBeCreated implements Specification
{
    public function isSatisfiedBy($config)
    {
        if (true === $config->hasDeadLetterExchange()) {
            return true;
        }

        if (!isset($config['queues']) || empty($config['queues'])) {
            return false;
        }

        $currentWithDl = false;
        foreach ($config['queues'] as $name => $parameters) {
            if (isset($parameters['with_dl']) && true === (bool) $parameters['with_dl']) {
                return true;
            }

            if (isset($parameters['retries'])) {
                return true;
            }

            if ($currentWithDl) {
                return true;
            }
        }

        return false;
    }
}
