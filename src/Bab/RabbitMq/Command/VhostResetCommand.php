<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VhostResetCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('vhost:reset')
            ->setDescription('Reset a vhost')
            ->addArgument('vhost', InputArgument::REQUIRED, 'Which vhost should be cleaned ?')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $vhostManager = $this->getVhostManager($input, $output, $input->getArgument('vhost'));
        $vhostManager->resetVhost();

        return 0;
    }
}
