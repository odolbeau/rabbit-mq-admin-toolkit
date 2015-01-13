<?php
namespace Bab\RabbitMq;

interface Action
{
    public function __construct(HttpClient $httpClient);
    
    public function createExchange($name, $parameters);
    
    public function createQueue($name, $parameters);
    
    public function setVhost($vhost);
}