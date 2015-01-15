<?php

namespace Bab\RabbitMq\Action\Formatter;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class Log
{
    use LoggerAwareTrait;

    const TYPE_UPDATE = '<info>[UPDATE]</info>';
    const TYPE_FAILED = '<error>[FAILED]</error>';
    const TYPE_UNCHANGED = '[UNCHANGED]';

    private $log;

    public function __construct()
    {
        $this->log = array();
        $this->logger = new NullLogger();
    }

    public function addUpdate($context, $message)
    {
        $this->append(self::TYPE_UPDATE, $context, $message);
    }

    public function addFailed($context, $message)
    {
        $this->append(self::TYPE_FAILED, $context, $message);
    }

    public function addUnchanged($context, $message)
    {
        $this->append(self::TYPE_UNCHANGED, $context, $message);
    }

    private function append($type, $context, $message)
    {
        $log = array(
            'type' => $type,
            'context' => $context,
            'message' => $message
        );

        $this->log[] = $log;
    }

    public function output()
    {
        $messageLength = 0;
        $contextLength = 0;
        foreach ($this->log as $log) {
            $currentMessageLength = strlen($log['message']);
            if ($currentMessageLength > $messageLength) {
                $messageLength = $currentMessageLength;
            }

            $currentContextLength = strlen($log['context']);
            if ($currentContextLength > $contextLength) {
                $contextLength = $currentContextLength;
            }
        }

        foreach ($this->log as $log) {
            $this->logger->info(str_pad(ucfirst(str_pad($log['context'], $contextLength)) . ': ' . $log['message'], $messageLength, '.') . ' ' . $log['type']);
        }
    }
}