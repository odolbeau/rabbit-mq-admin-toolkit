<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MessageSenderCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
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
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf(
            'Send messages to exchange "%s" with routingKey "%s" (vhost: "%s")',
            $input->getArgument('to_exchange'),
            $input->getArgument('to_routing_key'),
            $input->getArgument('to_vhost')
        ));

        $vhostManager = $this->getVhostManager($input, $output, $input->getArgument('to_vhost'));

        $messages = array();

        $file = $input->getOption('file');

        if (null !== $file) {
            $messages = file($file, FILE_SKIP_EMPTY_LINES);
        }

        if (!$messages) {
            $encodedMessage = $input->getOption('message');
            if (!empty($encodedMessage)) {
                $messages = array($encodedMessage);
            }
        }

        if (!$messages) {
            throw new \InvalidArgumentException('No message to publish.');
        }

        $notHandledMessages = array();

        foreach ($messages as $message) {
            try {
                $vhostManager->publishMessage(
                    $input->getArgument('to_exchange'),
                    $input->getArgument('to_routing_key'),
                    $message
                );
            } catch (\Exception $e) {
                $notHandledMessages[] = array(
                    'payload' => $message,
                    'exception' => $e->getMessage(),
                );

                continue;
            }

            $output->writeln(sprintf('Message published: <info>%s</info>', $message));
        }

        if (count($notHandledMessages)) {
            $output->writeln(sprintf('<error>- %d message(s) were/was not published into rabbit:</error>', count($notHandledMessages)));

            $table = new Table($output);
            $table
                ->setHeaders(array('Message', 'Exception'))
                ->setRows($notHandledMessages)
            ;
            $table->render();

            return 1;
        }
    }
}
