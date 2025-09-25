<?php

namespace Maan511\OpenapiToLaravel\Tests;

use Illuminate\Console\Application;
use Maan511\OpenapiToLaravel\Console\GenerateFormRequestsCommand;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with shared testing utilities.
 *
 * @property OpenApiParser $parser Parser instance for integration tests
 * @property FormRequestGenerator $generator Generator instance for integration tests
 */
abstract class TestCase extends BaseTestCase
{
    // Properties for dependency injection in tests (initialized in setUp or test methods)
    protected ?ValidationRuleMapper $ruleMapper = null;

    protected ?ValidationRuleMapper $mapper = null;

    protected ?TemplateEngine $templateEngine = null;

    protected ?FormRequestGenerator $generator = null;

    protected ?ReferenceResolver $referenceResolver = null;

    protected ?SchemaExtractor $schemaExtractor = null;

    protected ?OpenApiParser $parser = null;

    // Console command testing properties
    protected ?GenerateFormRequestsCommand $command = null;

    protected ?Application $application = null;

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
