<?php
namespace Bab\RabbitMq\Actions;

use Bab\RabbitMq\Action;
use Bab\RabbitMq\HttpClient;

class RealAction implements Action
{
    private
        $vhost,
        $httpClient;
    
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }
    
    public function createExchange($name, $parameters)
    {
        //$this->log(sprintf('Create exchange <info>%s</info>', $name));
        
        return $this->httpClient->query('PUT', '/api/exchanges/'.$this->vhost.'/'.$name, $parameters);
    }
    
    public function createQueue($name, $parameters)
    {
        //$this->log(sprintf('Create queue <info>%s</info>', $name));
        
        return $this->httpClient->query('PUT', '/api/queues/'.$this->vhost.'/'.$name, $parameters);
    }
    
    public function setVhost($vhost)
    {
        $this->vhost = $vhost;
    }
}