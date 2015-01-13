<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Bab\RabbitMq\VhostManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bab\RabbitMq\Actions\RealAction;
use Bab\RabbitMq\HttpClients\CurlClient;

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
        $host = $input->getOption('host');
        $user = $input->getOption('user');
        $pass = $this->getPassword($input, $output);
        $port = $input->getOption('port');
        $httpClient = new CurlClient($host, $port, $user, $pass);
        $action = new RealAction($httpClient);
        
        return new VhostManager(array(
            'host'     => $host,
            'user'     => $user,
            'password' => $pass,
            'port'     => $port,
            'vhost'    => $vhost,
        ), $output, $action, $httpClient);
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
