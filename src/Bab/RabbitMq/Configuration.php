<?php

namespace Bab\RabbitMq;

interface Configuration extends \ArrayAccess
{
    public function getVhost();

    public function hasDeadLetterExchange();

    public function hasUnroutableExchange();
}
