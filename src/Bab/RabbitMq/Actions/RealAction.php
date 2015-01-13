<?php
namespace Bab\RabbitMq\Actions;

use Bab\RabbitMq\HttpClient;

class RealAction extends Action
{
    public function createExchange($name, $parameters)
    {
        $this->log(sprintf('Create exchange <info>%s</info>', $name));
        
        return $this->query('PUT', '/api/exchanges/'.$this->vhost.'/'.$name, $parameters);
    }
    
    public function createQueue($name, $parameters)
    {
        $this->log(sprintf('Create queue <info>%s</info>', $name));
        
        return $this->query('PUT', '/api/queues/'.$this->vhost.'/'.$name, $parameters);
    }
}