<?php

namespace Bab\RabbitMq;

interface Action
{
    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return void
     */
    public function createExchange($name, $parameters);

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return void
     */
    public function createQueue($name, $parameters);

    /**
     * @param string $name
     * @param string $queue
     * @param string $routingKey
     *
     * @return void
     */
    public function createBinding($name, $queue, $routingKey, array $arguments = []);

    /**
     * @param string $user
     *
     * @return void
     */
    public function setPermissions($user, array $parameters = []);

    /**
     * @param string $vhost
     *
     * @return void
     */
    public function setVhost($vhost);
}
