<?php
namespace Bab\RabbitMq\Loggers;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CliLogger implements LoggerInterface
{
    private
        $output;
    
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }
    
    public function emergency($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function alert($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function critical($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function error($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function warning($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function notice($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function info($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function debug($message, array $context = array())
    {
        $this->log(null, $message);
    }

    public function log($level, $message, array $context = array())
    {
        $this->output->writeln($message);
    }
}