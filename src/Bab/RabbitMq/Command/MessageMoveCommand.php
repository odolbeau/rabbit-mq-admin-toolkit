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

class MessageMoveCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('message:move')
            ->setDescription('Move messages from a queue to another one')
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
        if ($input->getOption('dry-run')) {
            throw new \Exception('No dry-run scenario known for this command. Abort.');
        }

        $output->writeln(sprintf(
            'Move messages from queue "%s" (vhost: "%s") to exchange "%s" with routingKey "%s" (vhost: "%s")',
            $input->getArgument('from_queue'),
            $input->getArgument('from_vhost'),
            $input->getArgument('to_exchange'),
            $input->getArgument('to_routing_key'),
            $input->getArgument('to_vhost')
        ));

        $password = $this->getPassword($input, $output);

        $connection = new \AMQPConnection(array(
            'host'     => $input->getOption('host'),
            'login'    => $input->getOption('user'),
            'password' => $password,
            'vhost'    => $input->getArgument('from_vhost'),
        ));
        $connection->connect();
        $channel = new \AMQPChannel($connection);
        $queue = new \AMQPQueue($channel);
        $queue->setName($input->getArgument('from_queue'));

        $connection = new \AMQPConnection(array(
            'host'     => $input->getOption('host'),
            'login'    => $input->getOption('user'),
            'password' => $password,
            'vhost'    => $input->getArgument('to_vhost'),
        ));
        $connection->connect();
        $channel = new \AMQPChannel($connection);
        $exchange = new \AMQPExchange($channel);
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
        $this->messagePublisher->publish($message, $this->routingKey);
    }
}
