<?php
namespace Bab\RabbitMq\Action;

use Bab\RabbitMq\HttpClient;
use Bab\RabbitMq\Response;
use Bab\RabbitMq\HttpClient\GuzzleClient;
use Bab\RabbitMq\Filter\BindingRoutingKeyFilterIterator;
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

        $this->httpClient->setDryRunMode(GuzzleClient::DRYRUN_ENABLED);
    }

    public function startMapping()
    {
        $this->logger->debug('Start Dry-run RabbitMq configuration');
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

    public function createBinding($name, $queue, $routingKey)
    {
        $response = $this->query('GET', '/api/bindings/'.$this->getContextValue('vhost').'/e/'.$name.'/q/'.$queue);
        $bindings = json_decode($response->body, true);

        if ($this->isExistingBinding($bindings, $routingKey) === false) {
            $this->log->addUpdate(self::LABEL_BINDING, sprintf(
                '<info>%s</info>. Queue <info>%s</info>. Parameters:  <info>routing_key: %s</info>)',
                $name,
                $queue,
                null !== $routingKey ? $routingKey : 'none'
            ));
        }
    }

    private function isExistingBinding(array $bindings = array(), $routingKey)
    {
        $matches = iterator_to_array(new BindingRoutingKeyFilterIterator(new \ArrayIterator($bindings), $routingKey));

        return !empty($matches);
    }

    public function setPermissions($user, array $parameters = array())
    {
        $response = $this->query('GET', '/api/users/'.$user.'/permissions');
        $permissionDelta = array();

        if ($response->code === Response::NOT_FOUND) {
            $permissionDelta = $parameters;
        } else {
            $userPermissions = current(json_decode($response->body, true));
            $permissionDelta = array_diff_assoc($parameters, $userPermissions);
        }

        if (!empty($permissionDelta)) {
            $this->log->addUpdate(self::LABEL_PERMISSION, sprintf('User: <info>%s</info>. Vhost: <info>%s</info>. Parameters: <info>%s</info>', $user, $this->getContextValue('vhost'), json_encode($permissionDelta)));
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
            if ($currentParameters->code === Response::NOT_FOUND) {
                $this->log->addUpdate($objectType, sprintf('<info>%s</info>. Parameters <info>%s</info>', $objectName, json_encode($parameters)));
                return;
            }

            $configurationDelta = $this->array_diff_assoc_recursive($parameters, json_decode($currentParameters->body, true));

            if (!empty($configurationDelta)) {
                $this->log->addFailed($objectType, sprintf('<info>%s</info>. Parameters <error>%s</error>', $objectName, json_encode($configurationDelta)));
                return;
            }
        }
    }

    private function array_diff_assoc_recursive(array $arrayA, array $arrayB)
    {
        $difference=array();

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