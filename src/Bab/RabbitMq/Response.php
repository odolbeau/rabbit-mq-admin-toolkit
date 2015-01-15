<?php
namespace Bab\RabbitMq;

class Response
{
    const NOT_FOUND = 404;
    
    public $code;
    public $body;
    
    public function __construct($code, $body)
    {
        $this->code = $code;
        $this->body = $body;
    }
}
