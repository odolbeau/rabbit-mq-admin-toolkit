<?php

namespace Bab\RabbitMq\Specification;

use Bab\RabbitMq\Configuration;

interface Specification
{
    public function isSatisfiedBy(Configuration $config): bool;
}
