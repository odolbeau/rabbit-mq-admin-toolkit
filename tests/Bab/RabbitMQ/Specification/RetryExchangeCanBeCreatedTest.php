<?php

namespace Bab\RabbitMq\Tests\Specification;

use Bab\RabbitMq\Configuration;
use Bab\RabbitMq\Specification\RetryExchangeCanBeCreated;
use PHPUnit\Framework\TestCase;

class RetryExchangeCanBeCreatedTest extends TestCase
{
    private $specification;

    public function setUp(): void
    {
        $this->specification = new RetryExchangeCanBeCreated();
    }

    /**
     * @param $expected
     * @param array $arrayConfig
     *
     * @dataProvider provideConfig
     */
    public function testItCreatesAnExchange($expected, array $arrayConfig)
    {
        $config = new Configuration\FromArray($arrayConfig);
        $this->assertEquals($expected, $this->specification->isSatisfiedBy($config));
    }

    public function provideConfig()
    {
        return [
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => null,
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => '',
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [],
                    ],
                ],
            ],
            [
                true,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'retries' => [5, 10, 15],
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                true,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                            'test_queue_2' => [
                                'durable' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_2'],
                                ],
                            ],
                            'test_queue_with_retry' => [
                                'durable' => true,
                                'retries' => [10],
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_with_retry'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                false,
                [
                    'my_vhost' => [
                        'parameters' => [
                            'with_dl' => false,
                            'with_unroutable' => false,
                        ],
                        'exchanges' => [
                            'default' => ['type' => 'direct', 'durable' => true],
                        ],
                        'queues' => [
                            'test_queue' => [
                                'durable' => true,
                                'with_dl' => false,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue'],
                                ],
                            ],
                            'test_queue_2' => [
                                'durable' => true,
                                'with_dl' => false,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_2'],
                                ],
                            ],
                            'test_queue_with_retry' => [
                                'durable' => true,
                                'with_dl' => true,
                                'bindings' => [
                                    ['exchange' => 'default', 'routing_key' => 'test_queue_with_retry'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
