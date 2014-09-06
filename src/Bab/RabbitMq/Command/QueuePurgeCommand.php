<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueuePurgeCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('queue:purge')
            ->setDescription('Purge all queue of a vhost')
            ->addArgument('vhost', InputArgument::REQUIRED, 'Which vhost should be purged?')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Should we purge all queues in vhost?')
            ->addOption('pattern', 'P', InputOption::VALUE_OPTIONAL, 'Purge only queues matching pattern', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pattern = $input->getOption('pattern');
        if (false === $input->getOption('all') && null === $pattern) {
            throw new \InvalidArgumentException('You must use "pattern" or "all" option');
        }
        $vhostManager = $this->getVhostManager($input, $output, $input->getArgument('vhost'));

        // Test pattern
        if (null !== $pattern && false === preg_match($pattern, "")) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid pattern: "%s".',
                $pattern
            ));
        }

        foreach ($vhostManager->getQueues() as $queue) {
            if (null !== $pattern && 1 !== preg_match($pattern, $queue)) {
                continue;
            }
            $output->writeln(sprintf(
                'Purge queue <comment>%s</comment>.',
                $queue
            ));

            $vhostManager->purge($queue);
        };
    }
}
