<?php

namespace Bab\RabbitMq\Logger;

use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\AbstractLogger;

class CliLogger extends AbstractLogger
{
    private $output;
    
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }
    
    public function log($level, $message, array $context = array())
    {
        $this->output->writeln($message);
    }
}
