<?php
namespace Bab\RabbitMq;

class Response
{
    public
        $code,
        $body;
    
    public function __construct($code, $body)
    {
        $this->code = $code;
        $this->body = $body;
    }
}
