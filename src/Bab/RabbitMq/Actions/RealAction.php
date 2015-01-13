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
    
    public function createBinding($name, $queue, $routingKey)
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            null !== $routingKey ? $routingKey : 'none'
        ));
        
        $parameters = null;
        if (null !== $routingKey) {
            $parameters = array(
                'routing_key' => $routingKey,
            );
        }
        
        return $this->query('POST', '/api/bindings/'.$this->vhost.'/e/'.$name.'/q/'.$queue, $parameters);
    }
    
    public function setPermissions(array $config = array())
    {
        if (!empty($config['permissions'])) {
            foreach($config['permissions'] as $user => $userPermissions)
            {
                $parameters = $this->extractPermissions($userPermissions);
                $this->query('PUT', '/api/permissions/'.$this->vhost.'/'.$user, $parameters);
            }
        }
    }
    
    private function extractPermissions(array $userPermissions = array())
    {
        $permissions = array(
            'configure' => '',
            'read' => '',
            'write' => '',
        );
    
        if (!empty($userPermissions)) {
            foreach(array_keys($permissions) as $permission) {
                if (!empty($userPermissions[$permission])) {
                    $permissions[$permission] = $userPermissions[$permission];
                }
            }
        }
    
        return $permissions;
    }
}