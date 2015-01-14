<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Bab\RabbitMq\Configuration;

class VhostMappingCreateCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('vhost:mapping:create')
            ->setDescription('Create a vhost from a configuration file')
            ->addArgument('filepath', InputArgument::REQUIRED, 'Path to the configuration file')
            ->addOption('vhost', null, InputOption::VALUE_REQUIRED, 'Which vhost? If not defined, used the one defined in the config file')
            ->addOption('erase-vhost', null, InputOption::VALUE_NONE, 'Delete and re-create vhost')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = new Configuration\Yaml($input->getArgument('filepath'));

        $vhost = $input->getOption('vhost');
        if (null === $vhost) {
            $vhost = $configuration->getVhost();
        }

        $vhostManager = $this->getVhostManager($input, $output, $vhost);

        if ($input->getOption('erase-vhost')) {
            $vhostManager->resetVhost();
        }

        $vhostManager->createMapping($configuration);
    }
}
