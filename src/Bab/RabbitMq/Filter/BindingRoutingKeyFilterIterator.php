<?php

namespace Bab\RabbitMq\Filter;

class BindingRoutingKeyFilterIterator extends \FilterIterator
{
    private $routingKey;
    
    public function __construct(\Iterator $it, $routingKey)
    {
        $this->routingKey = is_null($routingKey) ?  '' : $routingKey;
        
        parent::__construct($it);
    }
    
    public function accept()
    {
        $current = $this->getInnerIterator()->current();
        
        return (isset($current['routing_key']) && $current['routing_key'] === $this->routingKey);
    }
}