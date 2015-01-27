<?php

namespace Bab\RabbitMq\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Bab\RabbitMq\VhostManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Bab\RabbitMq\Logger\CliLogger;
use Bab\RabbitMq\ActionInterface;
use Bab\RabbitMq\HttpClient\GuzzleClient;

class BaseCommand extends Command
{
    protected function configure()
    {
        $this
            ->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Which host?', '127.0.0.1')
            ->addOption('scheme', 's', InputOption::VALUE_OPTIONAL, 'Which protocol scheme ? (http(s))')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Which user?', 'guest')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Which password? If nothing provided, password is asked', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Which port?', 15672)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compare current configuration and specified one.')
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
        $context = array(
            'host'   => $input->getOption('host'),
            'scheme' => $input->getOption('scheme'),
            'user'   => $input->getOption('user'),
            'pass'   => $this->getPassword($input, $output),
            'port'   => $input->getOption('port'),
            'vhost'  => $vhost,
        );

        $logger = new CliLogger($output);
        $httpClient = new GuzzleClient($context['scheme'], $context['host'], $context['port'], $context['user'], $context['pass']);

        if ($input->getOption('dry-run')) {
            $action = new Action\DryRunAction($httpClient);
        } else {
            $action = new Action\RealAction($httpClient);
        }
        $action->setLogger($logger);

        $vhostManager = new VhostManager($context, $action, $httpClient);
        $vhostManager->setLogger($logger);

        return $vhostManager;
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
