<?php

namespace Bab\RabbitMq;

interface Action
{
    public function __construct(HttpClient $httpClient);
    
    public function startMapping();
    
    public function endMapping();
    
    public function resetVhost();
    
    public function createExchange($name, $parameters);

    public function createQueue($name, $parameters);

    public function createBinding($name, $queue, $routingKey, array $arguments = array());

    public function setPermissions($user, array $parameters = array());
    
    public function purge($queue);
    
    public function remove($queue);
    
    public function setContext(array $context);
}
