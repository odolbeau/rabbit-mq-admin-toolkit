<?php

namespace Bab\RabbitMq\Command;

use Swarrot\Broker\Message;
use Swarrot\Broker\MessagePublisher\PeclPackageMessagePublisher;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MessageSenderCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('message:sender')
            ->setDescription('Send messages to a queue')
            ->addArgument('to_vhost', InputArgument::REQUIRED, 'To which vhost?')
            ->addArgument('to_exchange', InputArgument::REQUIRED, 'To which exchange?')
            ->addArgument('to_routing_key', InputArgument::REQUIRED, 'To which routing key?')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'File containing every messages to send into rabbit')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Message to send into rabbit (Only available if the file was not filled)')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf(
            'Send messages to exchange "%s" with routingKey "%s" (vhost: "%s")',
            $input->getArgument('to_exchange'),
            $input->getArgument('to_routing_key'),
            $input->getArgument('to_vhost')
        ));

        $exchange = new \AMQPExchange($this->getChannel($input, $output, $input->getArgument('to_vhost')));
        $exchange->setName($input->getArgument('to_exchange'));
        $publisher = new PeclPackageMessagePublisher($exchange);

        $messages = [];

        $file = $input->getOption('file');

        if (null !== $file) {
            $messages = file($file, FILE_SKIP_EMPTY_LINES);
        }

        if (!$messages) {
            $encodedMessage = $input->getOption('message');
            if (!empty($encodedMessage)) {
                $messages = [$encodedMessage];
            }
        }

        if (!$messages) {
            throw new \InvalidArgumentException('No message to publish.');
        }

        $notHandledMessages = [];

        foreach ($messages as $message) {
            try {
                $swarrotMessage = new Message($message);
                $publisher->publish($swarrotMessage, $input->getArgument('to_routing_key'));
            } catch (\Exception $e) {
                $notHandledMessages[] = [
                    'payload' => $message,
                    'exception' => $e->getMessage(),
                ];

                continue;
            }

            $output->writeln(sprintf('Message published: <info>%s</info>', $message));
        }

        if (\count($notHandledMessages)) {
            $output->writeln(sprintf('<error>- %d message(s) were/was not published into rabbit:</error>', \count($notHandledMessages)));

            $table = new Table($output);
            $table
                ->setHeaders(['Message', 'Exception'])
                ->setRows($notHandledMessages)
            ;
            $table->render();

            return 1;
        }

        return 0;
    }

    public function getChannel(InputInterface $input, OutputInterface $output, string $vhost): \AMQPChannel
    {
        $credentials = $this->getCredentials($input, $output);

        $credentials['login'] = $credentials['user'];
        unset($credentials['user'], $credentials['port']);

        $connection = new \AMQPConnection(array_merge($credentials, ['vhost' => $vhost]));
        $connection->connect();

        return new \AMQPChannel($connection);
    }
}
