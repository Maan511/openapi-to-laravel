<?php

namespace Maan511\OpenapiToLaravel\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with shared testing utilities.
 *
 * @property \Maan511\OpenapiToLaravel\Parser\OpenApiParser $parser Parser instance for integration tests
 * @property \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator $generator Generator instance for integration tests
 */
abstract class TestCase extends BaseTestCase
{
    // Properties for dependency injection in tests (initialized in setUp or test methods)
    protected ?\Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper $ruleMapper = null;

    protected ?\Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper $mapper = null;

    protected ?\Maan511\OpenapiToLaravel\Generator\TemplateEngine $templateEngine = null;

    protected ?\Maan511\OpenapiToLaravel\Generator\FormRequestGenerator $generator = null;

    protected ?\Maan511\OpenapiToLaravel\Parser\ReferenceResolver $referenceResolver = null;

    protected ?\Maan511\OpenapiToLaravel\Parser\SchemaExtractor $schemaExtractor = null;

    protected ?\Maan511\OpenapiToLaravel\Parser\OpenApiParser $parser = null;

    // Console command testing properties
    protected ?\Maan511\OpenapiToLaravel\Console\GenerateFormRequestsCommand $command = null;

    protected ?\Illuminate\Console\Application $application = null;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get a sample OpenAPI specification for testing.
     *
     * @return array<string, mixed>
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
     *
     * @return array<string, mixed>
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
