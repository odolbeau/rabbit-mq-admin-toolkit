<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VhostResetCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:reset')
            ->setDescription('Reset a vhost')
            ->addArgument('vhost', InputArgument::REQUIRED, 'Which vhost should be cleaned ?')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $vhostManager = $this->getVhostManager($input, $output, $input->getArgument('vhost'));
        $vhostManager->resetVhost();
    }
}
