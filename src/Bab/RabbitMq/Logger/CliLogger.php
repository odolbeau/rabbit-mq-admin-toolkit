<?php

namespace Bab\RabbitMq\Logger;

use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Output\OutputInterface;

class CliLogger extends AbstractLogger
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function log($level, $message, array $context = [])
    {
        $this->output->writeln($message);
    }
}
