<?php

namespace Bab\RabbitMq;

interface HttpClient
{
    public function __construct($scheme, $host, $port, $user, $pass);
    
    public function query($verb, $uri, array $parameters = null);
}
