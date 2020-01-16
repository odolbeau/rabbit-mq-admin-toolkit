<?php

namespace Bab\RabbitMq;

interface Action
{
    public function createExchange(string $name, array $parameters): void;

    public function createQueue(string $name, array $parameters): void;

    public function createBinding(string $name, string $queue, string $routingKey, array $arguments = []): void;

    public function setPermissions(string $user, array $parameters = []): void;

    public function setVhost(string $vhost): void;
}
