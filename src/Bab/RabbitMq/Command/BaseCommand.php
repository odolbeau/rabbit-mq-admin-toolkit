<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Bab\RabbitMq\VhostManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bab\RabbitMq\Action\RealAction;
use Bab\RabbitMq\HttpClient\CurlClient;
use Bab\RabbitMq\Logger\CliLogger;
use Symfony\Component\Filesystem\Filesystem;

class BaseCommand extends Command
{
    protected function configure()
    {
        $this
            ->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Which host?', '127.0.0.1')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Which user?', 'guest')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Which password? If nothing provided, password is asked', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Which port?', 5672)
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
        $credentials = $this->getCredentials();

        $logger = new CliLogger($output);
        $httpClient = new CurlClient($credentials['host'], $credentials['port'], $credentials['login'], $credentials['password']);
        $action = new RealAction($httpClient);
        $action->setLogger($logger);

        $credentials['vhost'] = $vhost;
        $vhostManager = new VhostManager($credentials);

        $vhostManager->setLogger($logger);

        return $vhostManager;
    }

    /**
     * getCredentials
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return array
     */
    protected function getCredentials(InputInterface $input, OutputInterface $output)
    {
        $fs = new Filesystem();

        $credentials = array();
        $file = rtrim(getenv('HOME'), '/') . '/.rabbitmq_admin_toolkit';
        if ($fs->exists($file)) {
            $credentials = json_decode(file_get_contents($file), true);
        }

        if ($input->hasParameterOption(['--host', '-H'])) {
            $credentials['host'] = $input->getOption('host');
        } else {
            $credentials['host'] = isset($credentials['host'])? $credentials['host'] : $input->getOption('host');
        }

        if ($input->hasParameterOption('--port')) {
            $credentials['port'] = $input->getOption('port');
        } else {
            $credentials['port'] = isset($credentials['port'])? $credentials['port'] : $input->getOption('port');
        }

        if ($input->hasParameterOption(['--user', '-u'])) {
            $credentials['login'] = $input->getOption('user');
        } else {
            $credentials['login'] = isset($credentials['login'])? $credentials['login'] : $input->getOption('user');
        }

        if ($input->hasParameterOption(['--password', '-p'])) {
            $credentials['password'] = $input->getOption('password');
        } elseif (isset($credentials['password'])) {
            $credentials['password'] = $credentials['password'];
        } elseif (null === $password = $input->getOption('password')) {
            $dialog = $this->getHelperSet()->get('dialog');
            $credentials['password'] = $dialog->askHiddenResponse($output, 'Password?', false);
        }

        return $credentials;
    }
}
