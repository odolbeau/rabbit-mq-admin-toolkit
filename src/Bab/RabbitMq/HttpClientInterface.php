<?php

namespace Bab\RabbitMq;

interface HttpClientInterface
{
    public function __construct($scheme, $host, $port, $user, $pass);

    public function query($verb, $uri, array $parameters = null);

    public function enableDryRun($enabled = false);
}
