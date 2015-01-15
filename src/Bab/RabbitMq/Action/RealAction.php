<?php

namespace Bab\RabbitMq\Action;

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

    public function createBinding($name, $queue, $routingKey, array $arguments = array())
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            null !== $routingKey ? $routingKey : 'none'
        ));

        $parameters = array(
            'arguments' => $arguments,
        );

        if (! empty($routingKey)) {
            $parameters['routing_key'] = $routingKey;
        }
        
        return $this->query('POST', '/api/bindings/'.$this->vhost.'/e/'.$name.'/q/'.$queue, $parameters);
    }
    
    public function setPermissions($user, array $parameters = array())
    {
        $this->log(sprintf('Grant following permissions for user <info>%s</info> on vhost <info>%s</info>: <info>%s</info>', $user, $this->vhost, json_encode($parameters)));
        
        $this->query('PUT', '/api/permissions/'.$this->vhost.'/'.$user, $parameters);
    }
}
