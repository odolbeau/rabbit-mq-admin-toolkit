<?php

namespace Bab\RabbitMq\Configuration;

use Bab\RabbitMq\Configuration;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Filesystem\Filesystem;

class Yaml extends FromArray
{
    public function __construct($filePath)
    {
        $fs = new Filesystem();
        if (!$fs->exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File "%s" doen\'t exist', $filePath));
        }

        $yaml = new Parser();

        $configuration = $yaml->parse(file_get_contents($filePath));

        parent::__construct($configuration);
    }
}
