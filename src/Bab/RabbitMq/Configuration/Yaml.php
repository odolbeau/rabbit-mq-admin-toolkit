<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\Configuration;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;

class Yaml implements Configuration
{
    private $config;
    private $vhost;
    private $hasDeadLetterExchange;
    private $hasUnroutableExchange;
    
    public function __construct($filePath)
    {
        $configuration = $this->readFromFile($filePath);
        
        $this->vhost = key($configuration);
        $this->config = current($configuration);
       
        $this->initParameters();
    }
    
    private function readFromFile($filePath)
    {
        $fs = new Filesystem();
        if (!$fs->exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" doen\'t exist', $filePath));
        }
        
        $yaml = new Parser();
        return $yaml->parse(file_get_contents($filePath));
    }
    
    private function initParameters()
    {
        $parameters = $this->getValue($this->config, 'parameters');
        
        $this->hasDeadLetterExchange = (bool) $this->getValue($parameters, 'with_dl');
        $this->hasUnroutableExchange = (bool) $this->getValue($parameters, 'with_unroutable');
    }
    
    public function getVhost()
    {
        return $this->vhost;
    }
    
    public function hasDeadLetterExchange()
    {
        return $this->hasDeadLetterExchange;
    }
    
    public function hasUnroutableExchange()
    {
        return $this->hasUnroutableExchange;
    }
    
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }
    
    public function offsetGet($offset)
    {
        return isset($this->config[$offset]) ? $this->config[$offset] :  null;
    }
    
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('You shall not update configuration');
    }
    
    public function offsetUnset($offset)
    {
        throw new \LogicException('No need to unset');
    }
    
    private function getValue($config, $key)
    {
        if (isset($config[$key])) {
            return $config[$key];
        }
        
        return null;
    }
}