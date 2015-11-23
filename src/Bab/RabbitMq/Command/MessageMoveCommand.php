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
            ->addArgument('to_exchange', InputArgument::REQUIRED, 'To which exchange?')
            ->addArgument('to_routing_key', InputArgument::REQUIRED, 'To which routing key?')
            ->addOption('max-messages', 'm', InputOption::VALUE_OPTIONAL, 'Limit messages?', 0)
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

        $exchange = new \AMQPExchange($toChannel);
        $exchange->setName($input->getArgument('to_exchange'));

        $messageProvider = new PeclPackageMessageProvider($queue);
        $messagePublisher = new PeclPackageMessagePublisher($exchange);

        $options = array();
        $stack = (new \Swarrot\Processor\Stack\Builder());
        if (0 !== $max = (int) $input->getOption('max-messages')) {
            $stack->push('Swarrot\Processor\MaxMessages\MaxMessagesProcessor');
            $options['max_messages'] = $max;
        }
        $stack->push('Swarrot\Processor\Insomniac\InsomniacProcessor');
        $stack->push('Swarrot\Processor\Ack\AckProcessor', $messageProvider);

        $processor = $stack->resolve(new MoveProcessor($messagePublisher, $input->getArgument('to_routing_key')));

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
    protected $messagePublisher;
    protected $routingKey;

    public function __construct(MessagePublisherInterface $messagePublisher, $routingKey)
    {
        $this->messagePublisher = $messagePublisher;
        $this->routingKey       = $routingKey;
    }

    public function process(Message $message, array $options)
    {
        $this->messagePublisher->publish(new Message($message->getBody()), $this->routingKey);
    }
}
