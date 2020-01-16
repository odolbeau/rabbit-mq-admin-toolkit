<?php

namespace Bab\RabbitMq;

interface Configuration extends \ArrayAccess
{
    public function getVhost(): string;

    public function hasDeadLetterExchange(): bool;

    public function hasUnroutableExchange(): bool;
}
