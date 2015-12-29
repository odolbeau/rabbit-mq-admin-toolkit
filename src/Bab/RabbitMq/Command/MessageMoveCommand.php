<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Swarrot\Broker\MessageProvider\PeclPackageMessageProvider;
use Swarrot\Broker\MessagePublisher\MessagePublisherInterface;
use Swarrot\Broker\MessagePublisher\PeclPackageMessagePublisher;
use Swarrot\Processor\ProcessorInterface;
use Swarrot\Broker\Message;
use Swarrot\Consumer;
use Symfony\Component\Console\Command\Command;

class MessageMoveCommand extends Command
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('message:move')
            ->setDescription('Move messages from a queue to another one')

            ->addArgument('from_connection', InputArgument::REQUIRED, 'From connection name')
            ->addOption('to_connection', 't', InputOption::VALUE_REQUIRED, 'To connection name')

            ->addArgument('from_vhost', InputArgument::REQUIRED, 'From which vhost?')
            ->addArgument('from_queue', InputArgument::REQUIRED, 'From which queue?')
            ->addArgument('to_vhost', InputArgument::REQUIRED, 'To which vhost?')
            ->addArgument('to_exchange', InputArgument::OPTIONAL, 'To which exchange? (use message config by default)', null)
            ->addArgument('to_routing_key', InputArgument::OPTIONAL, 'To which routing key? (use message config by default)', null)
            ->addOption('max-messages', 'm', InputOption::VALUE_REQUIRED, 'Limit messages?', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf(
            'Move messages from queue "%s" (vhost: "%s") to exchange "%s" with routingKey "%s" (vhost: "%s")',
            $input->getArgument('from_queue'),
            $input->getArgument('from_vhost'),
            $input->getArgument('to_exchange'),
            $input->getArgument('to_routing_key'),
            $input->getArgument('to_vhost')
        ));

        $fromChannel = $this->getChannel(
            $input->getArgument('from_connection'),
            $input->getArgument('from_vhost')
        );

        if (null === $toConnectionName = $input->getOption('to_connection')) {
            $toChannel = $this->getChannel(
                $input->getArgument('from_connection'),
                $input->getArgument('to_vhost')
            );
        } else {
            $toChannel = $this->getChannel(
                $input->getOption('to_connection'),
                $input->getArgument('to_vhost')
            );
        }

        $queue = new \AMQPQueue($fromChannel);
        $queue->setName($input->getArgument('from_queue'));

        $messageProvider = new PeclPackageMessageProvider($queue);

        $options = array();
        $stack = (new \Swarrot\Processor\Stack\Builder());
        if (0 !== $max = (int) $input->getOption('max-messages')) {
            $stack->push('Swarrot\Processor\MaxMessages\MaxMessagesProcessor');
            $options['max_messages'] = $max;
        }
        $stack->push('Swarrot\Processor\Insomniac\InsomniacProcessor');
        $stack->push('Swarrot\Processor\Ack\AckProcessor', $messageProvider);

        $processor = $stack->resolve(new MoveProcessor($toChannel, $input->getArgument('to_exchange'), $input->getArgument('to_routing_key')));

        $consumer = new Consumer($messageProvider, $processor);
        $consumer->consume($options);
    }

    /**
     * getChannel
     *
     * @param string $connectionName
     *
     * @return \AMQPChannel
     */
    public function getChannel($connectionName, $vhost)
    {
        $file = rtrim(getenv('HOME'), '/') . '/.rabbitmq_admin_toolkit';
        if (!file_exists($file)) {
            throw new \InvalidArgumentException('Can\'t find ~/.rabbitmq_admin_toolkit file');
        }
        $credentials = json_decode(file_get_contents($file), true);
        if (!isset($credentials[$connectionName])) {
            throw new \InvalidArgumentException("Connection $connectionName not found in ~/.rabbitmq_admin_toolkit");
        }

        $defaultCredentials = [
            'host' => '127.0.0.1',
            'port' => 15672,
            'user' => 'root',
            'password' => 'root',
        ];

        $credentials = array_merge($defaultCredentials, $credentials[$connectionName]);

        $credentials['login'] = $credentials['user'];
        unset($credentials['user'], $credentials['port']);

        $connection = new \AMQPConnection(array_merge($credentials, ['vhost' => $vhost]));
        $connection->connect();

        return new \AMQPChannel($connection);
    }
}

class MoveProcessor implements ProcessorInterface
{
    protected $channel;
    protected $exchange;
    protected $routingKey;

    protected $publishers = [];

    public function __construct(\AMQPChannel $channel, $exchange, $routingKey)
    {
        $this->channel    = $channel;
        $this->exchange   = $exchange;
        $this->routingKey = $routingKey;
    }

    /**
     * {@inheritDoc}
     */
    public function process(Message $message, array $options)
    {
        $properties = $message->getProperties();

        $exchange = $this->exchange ?: $properties['exchange_name'];
        $routingKey = $this->routingKey ?: $properties['routing_key'];

        $this->getMessagePublisher($exchange)->publish(new Message($message->getBody()), $routingKey);
    }

    /**
     * getMessagePublisher
     *
     * @param string $name
     *
     * @return PeclPackageMessagePublisher
     */
    protected function getMessagePublisher($name)
    {
        if (isset($this->publishers[$name])) {
            return $this->publishers[$name];
        }

        $exchange = new \AMQPExchange($this->channel);
        $exchange->setName($name);

        $this->publishers[$name] = new PeclPackageMessagePublisher($exchange);

        return $this->publishers[$name];
    }
}
