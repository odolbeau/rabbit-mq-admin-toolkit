<?php

namespace Bab\RabbitMq;

interface HttpClient
{
    public function query(string $verb, string $uri, array $parameters = null): string;
}
