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
        $fs = new Filesystem();
        $credentials = array();
        $file = rtrim(getenv('HOME'), '/') . '/.rabbitmq_admin_toolkit';
        if ($fs->exists($file)) {
            $credentials = json_decode(file_get_contents($file), true);
        }

        if ($input->hasParameterOption(['--host', '-H'])) {
            $host = $input->getOption('host');
        } else {
            $host = isset($credentials['host'])? $credentials['host'] : $input->getOption('host');
        }

        if ($input->hasParameterOption('--port')) {
            $port = $input->getOption('port');
        } else {
            $port = isset($credentials['port'])? $credentials['port'] : $input->getOption('port');
        }

        if ($input->hasParameterOption(['--user', '-u'])) {
            $user = $input->getOption('user');
        } else {
            $user = isset($credentials['user'])? $credentials['user'] : $input->getOption('user');
        }

        if ($input->hasParameterOption(['--password', '-p'])) {
            $password = $input->getOption('password');
        } elseif (isset($credentials['password'])) {
            $password = $credentials['password'];
        } elseif (null === $password = $input->getOption('password')) {
            $dialog = $this->getHelperSet()->get('dialog');
            $password = $dialog->askHiddenResponse($output, 'Password?', false);
        }

        $logger = new CliLogger($output);
        $httpClient = new CurlClient($host, $port, $user, $password);
        $action = new RealAction($httpClient);
        $action->setLogger($logger);

        $vhostManager = new VhostManager(array(
            'host'     => $host,
            'user'     => $user,
            'password' => $password,
            'port'     => $port,
            'vhost'    => $vhost,
        ), $action, $httpClient);

        $vhostManager->setLogger($logger);

        return $vhostManager;
    }

    /**
     * getInfluxDB
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return InfluxDB
     */
    protected function getInfluxDB(InputInterface $input, OutputInterface $output)
    {
        $fs = new Filesystem();
        $credentials = array();
        $file = rtrim(getenv('HOME'), '/') . '/.influxdb_admin_cli';
        if ($fs->exists($file)) {
            $credentials = json_decode(file_get_contents($file), true);
        }

        if ($input->hasParameterOption(['--host', '-H'])) {
            $host = $input->getOption('host');
        } else {
            $host = isset($credentials['host'])? $credentials['host'] : $input->getOption('host');
        }

        if ($input->hasParameterOption('--port')) {
            $port = $input->getOption('port');
        } else {
            $port = isset($credentials['port'])? $credentials['port'] : $input->getOption('port');
        }

        if ($input->hasParameterOption(['--user', '-u'])) {
            $user = $input->getOption('user');
        } else {
            $user = isset($credentials['user'])? $credentials['user'] : $input->getOption('user');
        }

        if ($input->hasParameterOption(['--password', '-p'])) {
            $password = $input->getOption('password');
        } elseif (isset($credentials['password'])) {
            $password = $credentials['password'];
        } elseif (null === $password = $input->getOption('password')) {
            $dialog = $this->getHelperSet()->get('dialog');
            $password = $dialog->askHiddenResponse($output, 'Password?', false);
        }

        return new InfluxDB($host, $port, $user, $password);
    }
}
