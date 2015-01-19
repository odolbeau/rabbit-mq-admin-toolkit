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

    public function addUpdate($context, $name, array $parameters)
    {
        $this->append(self::TYPE_UPDATE, $context, $name, $parameters);
    }

    public function addFailed($context, $name, array $parameters)
    {
        $this->append(self::TYPE_FAILED, $context, $name, $parameters);
    }

    public function addUnchanged($context, $name, array $parameters)
    {
        $this->append(self::TYPE_UNCHANGED, $context, $name, $parameters);
    }

    private function append($type, $context, $name, array $parameters)
    {
        $log = array(
            'type' => $type,
            'context' => $context,
            'name' => $name,
            'parameters' => $parameters,
        );

        $this->log[] = $log;
    }

    public function output()
    {
        $logs = $this->log;

        $columnsMaxSize = $this->getColumnsMaxSize($logs);

        $contextMaxLength = $columnsMaxSize['contextMaxLength'];
        $messageMaxLength = $columnsMaxSize['messageMaxLength'];

        foreach ($logs as $log) {
            $message = $this->formatLine($log, $contextMaxLength, $messageMaxLength);

            switch ($log['type']) {
                case self::TYPE_UPDATE:
                    $this->logger->info($message);
                    break;
                case self::TYPE_FAILED:
                    $this->logger->error($message .' Configuration values that cause failure: ' .json_encode($log['parameters']));
                    break;
                case self::TYPE_UNCHANGED:
                    $this->logger->debug($message);
                    break;
            }
        }
    }

    private function getColumnsMaxSize(array $logs)
    {
        $messageMaxLength = 0;
        $contextMaxLength = 0;
        foreach ($logs as $log) {
            $currentMessageLength = strlen($log['name']);
            if ($currentMessageLength > $messageMaxLength) {
                $messageMaxLength = $currentMessageLength;
            }

            $currentContextLength = strlen($log['context']);
            if ($currentContextLength > $contextMaxLength) {
                $contextMaxLength = $currentContextLength;
            }
        }

        return array(
            'contextMaxLength' => $contextMaxLength,
            'messageMaxLength' => $messageMaxLength
        );
    }

    private function formatContext($context, $contextMaxLength)
    {
        return ucfirst(str_pad($context, $contextMaxLength));
    }

    private function formatLine(array $log, $contextMaxLength, $messageMaxLength)
    {
        return str_pad($this->formatContext($log['context'], $contextMaxLength) . ': ' . $log['name'], $messageMaxLength+15, '.') . ' ' . $log['type'];
    }
}
