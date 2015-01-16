<?php
namespace Bab\RabbitMq;

class Response
{
    public $code;
    public $body;

    public function __construct($code, $body)
    {
        $this->code = $code;
        $this->body = $body;
    }

    public function isSuccessful()
    {
        return (in_array($this->code, array(200, 201, 204)));
    }

    public function isNotFound()
    {
        return $this->code === 404;
    }
}
