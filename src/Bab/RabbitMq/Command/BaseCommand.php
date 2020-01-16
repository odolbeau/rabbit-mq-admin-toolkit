<?php

namespace Bab\RabbitMq\Command;

use Bab\RabbitMq\Action\RealAction;
use Bab\RabbitMq\HttpClient\CurlClient;
use Bab\RabbitMq\Logger\CliLogger;
use Bab\RabbitMq\VhostManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class BaseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Connection name (if you use a ~/.rabbitmq_admin_toolkit file)')
            ->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Which host?', '127.0.0.1')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Which user?', 'guest')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Which password? If nothing provided, password is asked', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Which port?', 15672)
        ;
    }

    protected function getVhostManager(InputInterface $input, OutputInterface $output, string $vhost): VhostManager
    {
        $credentials = $this->getCredentials($input, $output);

        $logger = new CliLogger($output);
        $httpClient = new CurlClient($credentials['host'], $credentials['port'], $credentials['user'], $credentials['password']);
        $action = new RealAction($httpClient);
        $action->setLogger($logger);

        $credentials['vhost'] = $vhost;
        $vhostManager = new VhostManager($credentials, $action, $httpClient);

        $vhostManager->setLogger($logger);

        return $vhostManager;
    }

    protected function getCredentials(InputInterface $input, OutputInterface $output): array
    {
        if (null !== $connection = $input->getOption('connection')) {
            $file = rtrim(getenv('HOME'), '/').'/.rabbitmq_admin_toolkit';
            if (!file_exists($file)) {
                throw new \InvalidArgumentException('Can\'t use connection option without a ~/.rabbitmq_admin_toolkit file');
            }
            $credentials = json_decode(file_get_contents($file), true);
            if (!isset($credentials[$connection])) {
                throw new \InvalidArgumentException("Connection $connection not found in ~/.rabbitmq_admin_toolkit");
            }

            $defaultCredentials = [
                'host' => '127.0.0.1',
                'port' => 15672,
                'user' => 'root',
                'password' => 'root',
            ];

            return array_merge($defaultCredentials, $credentials[$connection]);
        }

        $credentials = [
            'host' => $input->getOption('host'),
            'port' => $input->getOption('port'),
            'user' => $input->getOption('user'),
        ];

        if ($input->hasParameterOption(['--password', '-p'])) {
            $credentials['password'] = $input->getOption('password');
        } elseif (null === $input->getOption('password')) {
            $question = new Question('<question>Password?</question>');
            $question->setHidden(true);

            $credentials['password'] = $this->getHelperSet()->get('question')->ask($input, $output, $question);
        }

        return $credentials;
    }
}
