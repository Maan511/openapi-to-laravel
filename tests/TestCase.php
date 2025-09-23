<?php

namespace Maan511\OpenapiToLaravel\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get a sample OpenAPI specification for testing.
     */
    protected function getSampleOpenApiSpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email'],
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 2,
                                                'maxLength' => 100,
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                            ],
                                            'age' => [
                                                'type' => 'integer',
                                                'minimum' => 0,
                                                'maximum' => 120,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get a complex OpenAPI specification for testing.
     */
    protected function getComplexOpenApiSpec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Complex API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUserWithProfile',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email', 'profile'],
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 2,
                                                'maxLength' => 100,
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                            ],
                                            'profile' => [
                                                'type' => 'object',
                                                'required' => ['bio'],
                                                'properties' => [
                                                    'bio' => [
                                                        'type' => 'string',
                                                        'maxLength' => 500,
                                                    ],
                                                    'tags' => [
                                                        'type' => 'array',
                                                        'items' => [
                                                            'type' => 'string',
                                                        ],
                                                        'minItems' => 1,
                                                        'maxItems' => 5,
                                                    ],
                                                    'preferences' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'notifications' => [
                                                                'type' => 'boolean',
                                                            ],
                                                            'theme' => [
                                                                'type' => 'string',
                                                                'enum' => ['light', 'dark'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}