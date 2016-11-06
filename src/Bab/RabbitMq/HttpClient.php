<?php

namespace Bab\RabbitMq;

interface HttpClient
{
    /**
     * @param string     $verb
     * @param string     $uri
     * @param array|null $parameters
     *
     * @return string response body
     */
    public function query($verb, $uri, array $parameters = null);
}
