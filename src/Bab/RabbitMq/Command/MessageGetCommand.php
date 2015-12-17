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

class MessageGetCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('message:get')
            ->setDescription('Get a message from a queue.')
            ->addArgument('connection', InputArgument::REQUIRED, 'From connection name')
            ->addArgument('vhost', InputArgument::REQUIRED, 'From which vhost?')
            ->addArgument('queue', InputArgument::REQUIRED, 'From which queue?')
            ->addOption('no-requeue', 'R', InputOption::VALUE_NONE, 'Avoid requeue')
            ->addOption('nb-messages', 'c', InputOption::VALUE_REQUIRED, 'How many messages?', 1)
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf(
            'Get %d messages from queue "%s"',
            $input->getOption('nb-messages'),
            $input->getArgument('queue')
        ));

        $fromChannel = $this->getChannel(
            $input->getArgument('connection'),
            $input->getArgument('vhost')
        );

        $queue = new \AMQPQueue($fromChannel);
        $queue->setName($input->getArgument('queue'));

        $noRequeue = $input->getOption('no-requeue');
        $nbMessages = $input->getOption('nb-messages');
        for ($i = 0; $i < $nbMessages; $i++) {
            if ($noRequeue) {
                $message = $queue->get(AMQP_AUTOACK);
            } else {
                $message = $queue->get();
            }

            if (false === $message) {
                $output->writeln('No more messages in queue.');

                return;
            }

            $output->writeln(print_r($message, true));
        }
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
