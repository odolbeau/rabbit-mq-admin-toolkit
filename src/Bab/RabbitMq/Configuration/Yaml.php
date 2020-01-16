<?php

namespace Bab\RabbitMq\Configuration;

use Symfony\Component\Yaml\Parser;

class Yaml extends FromArray
{
    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" doesn\'t exist', $filePath));
        }

        $yaml = new Parser();

        $configuration = $yaml->parse(file_get_contents($filePath));

        parent::__construct($configuration);
    }
}
