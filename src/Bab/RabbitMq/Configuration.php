<?php

namespace Bab\RabbitMq;

interface Configuration extends \ArrayAccess
{
    /**
     * @return string
     */
    public function getVhost();

    /**
     * @return bool
     */
    public function hasDeadLetterExchange();

    /**
     * @return bool
     */
    public function hasUnroutableExchange();
}
