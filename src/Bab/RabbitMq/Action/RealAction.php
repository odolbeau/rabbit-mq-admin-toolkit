<?php

namespace Bab\RabbitMq\Action;

class RealAction extends Action
{
    /**
     * {@inheritdoc}
     */
    public function createExchange(string $name, array $parameters): void
    {
        $this->log(sprintf('Create exchange <info>%s</info>', $name));

        $this->query('PUT', '/api/exchanges/'.$this->vhost.'/'.$name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function createQueue(string $name, array $parameters): void
    {
        $this->log(sprintf('Create queue <info>%s</info>', $name));

        $this->query('PUT', '/api/queues/'.$this->vhost.'/'.$name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function createBinding(string $name, string $queue, string $routingKey = null, array $arguments = []): void
    {
        $this->log(sprintf(
            'Create binding between exchange <info>%s</info> and queue <info>%s</info> (with routing_key: <info>%s</info>)',
            $name,
            $queue,
            $routingKey ?? 'none'
        ));

        $parameters = [
            'arguments' => $arguments,
        ];

        if (null !== $routingKey) {
            $parameters['routing_key'] = $routingKey;
        }

        $this->query('POST', '/api/bindings/'.$this->vhost.'/e/'.$name.'/q/'.$queue, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setPermissions(string $user, array $parameters = []): void
    {
        $this->log(sprintf('Grant following permissions for user <info>%s</info> on vhost <info>%s</info>: <info>%s</info>', $user, $this->vhost, json_encode($parameters)));

        $this->query('PUT', '/api/permissions/'.$this->vhost.'/'.$user, $parameters);
    }
}
