<?php
namespace Bab\RabbitMq\Actions;

use Bab\RabbitMq\HttpClient;
use Bab\RabbitMq\Response;

class DryRunAction extends Action
{
    const
        LABEL_EXCHANGE = 'exchange',
        LABEL_QUEUE = 'queue';
    
    public function resetVhost()
    {
        $vhost = $this->getContextValue('vhost');
        $user = $this->getContextValue('user');
        
        $this->log(sprintf('Delete vhost: <info>%s</info>', $vhost));
        
        $this->log(sprintf('Create vhost: <info>%s</info>', $vhost));
        
        $this->log(sprintf(
            'Grant all permission for <info>%s</info> on vhost <info>%s</info>',
            $user,
            $vhost
        ));
    }
    
    public function createExchange($name, $parameters)
    {
        $this->compare('/api/exchanges/'.$this->getContextValue('vhost').'/'.$name, $name, $parameters, self::LABEL_EXCHANGE);
        return;
    }
    
    public function createQueue($name, $parameters)
    {
        $this->compare('/api/queues/'.$this->getContextValue('vhost').'/'.$name, $name, $parameters, self::LABEL_QUEUE);
        return;
    }
    
    public function createBinding($name, $queue, $routingKey)
    {
        $this->log(sprintf(
            'Will create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            null !== $routingKey ? $routingKey : 'none'
        ));
    }
    
    public function setPermissions($user, array $parameters = array())
    {
        $this->log(sprintf('Will attempt to grant following permissions for user <info>%s</info> on vhost <info>%s</info>: <info>%s</info>', $user, $this->getContextValue('vhost'), json_encode($parameters)));
    }
    
    public function remove($queue)
    {
        $this->log(sprintf('Will remove following queue: %s', $queue));
    }
    
    public function purge($queue)
    {
        $this->log(sprintf('Will purge following queue: %s', $queue));
    }
    
    private function compare($apiUri, $objectName, array $parameters = array(), $objectType)
    {
        $currentParameters = $this->query('GET', $apiUri);
        
        if($currentParameters instanceof Response)
        {
            if ($currentParameters->code === 404)
            {
                $this->log(sprintf('Add %s <info>%s</info> with following parameters <info>%s</info>', $objectType, $objectName, json_encode($parameters)));
                return;
            }
        
            $configurationDelta = $this->array_diff_assoc_recursive($parameters, json_decode($currentParameters->body, true));
        
            if(!empty($configurationDelta))
            {
                $this->log(
                    sprintf(
                        '<error>WARNING</error> following changes will crash the configuration update: Update %s <info>%s</info> with following parameters <error>%s</error>',
                        $objectType,
                        $objectName,
                        json_encode($configurationDelta)
                    )
                );
            }
        
        }
    }
    
    private function array_diff_assoc_recursive(array $arrayA, array $arrayB)
    {
        $difference=array();
    
        foreach ($arrayA as $key => $value) {
            if ( is_array($value) ) {
                if ( !isset($arrayB[$key]) || !is_array($arrayB[$key]) ) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $arrayB[$key]);
                    if ( !empty($new_diff) ) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif( !array_key_exists($key, $arrayB) || $arrayB[$key] !== $value ) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }
}