<?php

namespace Bab\RabbitMq;

interface HttpClient
{
    const DRYRUN_ENABLED = true;
    const DRYRUN_NOT_ENABLED = false;
    
    public function __construct($scheme, $host, $port, $user, $pass);
    
    public function query($verb, $uri, array $parameters = null);
    
    public function setDryRunMode($enabled = self::DRYRUN_NOT_ENABLED);
}
