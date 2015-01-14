<?php

namespace Bab\RabbitMq;

interface Action
{
    public function __construct(HttpClient $httpClient);
    
    public function createExchange($name, $parameters);
    
    public function createQueue($name, $parameters);
    
    public function createBinding($name, $queue, $routingKey);
    
    public function setPermissions($user, array $parameters = array());
    
    public function setVhost($vhost);
}
