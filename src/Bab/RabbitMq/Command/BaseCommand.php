<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Bab\RabbitMq\VhostManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command
{
    protected function configure()
    {
        $this
            ->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Which host?', '127.0.0.1')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Which user?', 'guest')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Which password? If nothing provided, password is asked', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Which port?', 15672)
        ;
    }

    /**
     * getVhostManager
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $vhost
     *
     * @return VhostManager
     */
    protected function getVhostManager(InputInterface $input, OutputInterface $output, $vhost)
    {
        return new VhostManager(array(
            'host'     => $input->getOption('host'),
            'user'     => $input->getOption('user'),
            'password' => $this->getPassword($input, $output),
            'port'     => $input->getOption('port'),
            'vhost'    => $vhost,
        ), $output);
    }

    /**
     * getPassword
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return string
     */
    protected function getPassword(InputInterface $input, OutputInterface $output)
    {
        $password = $input->getOption('password');
        if (null === $password) {
            $dialog = $this->getHelperSet()->get('dialog');
            $password = $dialog->askHiddenResponse($output, 'Password?', false);
        }

        return $password;
    }
}
