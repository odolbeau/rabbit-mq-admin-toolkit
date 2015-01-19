<?php

namespace Bab\RabbitMq\Logger;

use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class CliLogger extends AbstractLogger
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function log($level, $message, array $context = array())
    {
        $verbosity = $this->output->getVerbosity();

        if ($verbosity >= OutputInterface::VERBOSITY_NORMAL && $level === LogLevel::ERROR
        || $verbosity >= OutputInterface::VERBOSITY_VERBOSE && $level === LogLevel::INFO
        || $verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $this->output->writeln($message);
        }
    }
}
