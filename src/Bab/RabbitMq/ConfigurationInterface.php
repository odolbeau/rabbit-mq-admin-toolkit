<?php

namespace Bab\RabbitMq;

interface ConfigurationInterface extends \ArrayAccess
{
    public function getVhost();

    public function hasDeadLetterExchange();

    public function hasUnroutableExchange();

    public function offsetExists($offset);

    public function offsetGet($offset);

    public function offsetSet($offset, $value);

    public function offsetUnset($offset);
}
