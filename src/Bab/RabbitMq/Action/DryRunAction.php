<?php
namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClient;
use Bab\RabbitMq\Response;
use Bab\RabbitMq\Action\Formatter\Log;

class DryRunAction extends Action
{
    const LABEL_EXCHANGE = 'exchange';
    const LABEL_QUEUE = 'queue';
    const LABEL_BINDING = 'binding';
    const LABEL_PERMISSION = 'permission';

    private $log;

    public function __construct(HttpClient $httpClient)
    {
        parent::__construct($httpClient);

        $this->log = new Log();

        $this->httpClient->enableDryRun(true);
    }

    public function endMapping()
    {
        $this->log->setLogger($this->logger);
        $this->log->output();
    }

    public function resetVhost()
    {
        $vhost = $this->getContextValue('vhost');
        $user = $this->getContextValue('user');

        $this->log(sprintf('Will Delete vhost: <info>%s</info>', $vhost));

        $this->log(sprintf('Will Create vhost: <info>%s</info>', $vhost));

        $this->log(sprintf(
            'Will Grant all permission for <info>%s</info> on vhost <info>%s</info>',
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

    public function createBinding($name, $queue, $routingKey, array $arguments = array())
    {
        $vhost = $this->getContextValue('vhost');
        $response = $this->query('GET', '/api/queues/'.$vhost.'/'.$queue.'/bindings');

        $binding = array(
            'source' => $name,
            'destination' => $queue,
            'vhost' => $vhost,
            'routing_key' => is_null($routingKey) ? '' : $routingKey,
            'arguments' => $arguments
        );

        if ($response->isSuccessful()) {
            $bindings = json_decode($response->body, true);
            foreach ($bindings as $existingBinding) {
                $configurationDelta = $this->array_diff_assoc_recursive($binding, $existingBinding);

                if (empty($configurationDelta)) {
                    $this->log->addUnchanged(self::LABEL_BINDING, $queue.':'.$name, $arguments);
                    return;
                }
            }
        }

        $this->log->addUpdate(self::LABEL_BINDING, $queue.':'.$name, $arguments);
    }

    public function setPermissions($user, array $parameters = array())
    {
        $response = $this->query('GET', '/api/users/'.$user.'/permissions');
        $permissionDelta = array();

        if ($response->isNotFound()) {
            $permissionDelta = $parameters;
        } else {
            $userPermissions = current(json_decode($response->body, true));
            $permissionDelta = array_diff_assoc($parameters, $userPermissions);
        }

        if (!empty($permissionDelta)) {
            $this->log->addUpdate(self::LABEL_PERMISSION, $user, $permissionDelta);
        } else {
            $this->log->addUnchanged(self::LABEL_PERMISSION, $user, $parameters);
        }
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

        if ($currentParameters instanceof Response) {
            if ($currentParameters->isNotFound()) {
                $this->log->addUpdate($objectType, $objectName, $parameters);

                return;
            }

            $configurationDelta = $this->array_diff_assoc_recursive($parameters, json_decode($currentParameters->body, true));

            if (!empty($configurationDelta)) {
                $this->log->addFailed($objectType, $objectName, $configurationDelta);

                return;
            }

            $this->log->addUnchanged($objectType, $objectName, $parameters);
        }
    }

    private function array_diff_assoc_recursive(array $arrayA, array $arrayB)
    {
        $difference = array();

        foreach ($arrayA as $key => $value) {
            if (is_array($value)) {
                if (!isset($arrayB[$key]) || !is_array($arrayB[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $arrayB[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!array_key_exists($key, $arrayB) || $arrayB[$key] !== $value) {
                $difference[$key] = $value;
            }
        }

        return $difference;
    }
}
