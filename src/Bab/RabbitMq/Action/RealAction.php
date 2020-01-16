<?php

namespace Bab\RabbitMq\Action;

class RealAction extends Action
{
    /**
     * {@inheritdoc}
     */
    public function createExchange($name, $parameters)
    {
        $this->log(sprintf('Create exchange <info>%s</info>', $name));

        $this->query('PUT', '/api/exchanges/'.$this->vhost.'/'.$name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function createQueue($name, $parameters)
    {
        $this->log(sprintf('Create queue <info>%s</info>', $name));

        $this->query('PUT', '/api/queues/'.$this->vhost.'/'.$name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function createBinding($name, $queue, $routingKey, array $arguments = [])
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            null !== $routingKey ? $routingKey : 'none'
        ));

        $parameters = [
            'arguments' => $arguments,
        ];

        if (!empty($routingKey)) {
            $parameters['routing_key'] = $routingKey;
        }

        $this->query('POST', '/api/bindings/'.$this->vhost.'/e/'.$name.'/q/'.$queue, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setPermissions($user, array $parameters = [])
    {
        $this->log(sprintf('Grant following permissions for user <info>%s</info> on vhost <info>%s</info>: <info>%s</info>', $user, $this->vhost, json_encode($parameters)));

        $this->query('PUT', '/api/permissions/'.$this->vhost.'/'.$user, $parameters);
    }
}
