<?php

namespace Bab\RabbitMq\Tests\Configuration;

use Prophecy\Argument;
use Bab\RabbitMq\Configuration;
use PHPUnit\Framework\TestCase;

class FromArrayTest extends TestCase
{
    public function test_with_dl_and_unroutable()
    {
        $config = new Configuration\FromArray([
            'my_vhost' => [
                'parameters' => [
                    'with_dl' => true,
                    'with_unroutable' => true,
                ]
            ],
        ]);

        $this->assertSame('my_vhost', $config->getVhost());
        $this->assertTrue($config->hasDeadLetterExchange());
        $this->assertTrue($config->hasUnroutableExchange());
    }
}
