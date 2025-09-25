<?php

use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;

describe('Large OpenAPI Specification Performance', function () {
    it('should generate FormRequests for 100 endpoints in less than 5 seconds', function () {
        $startTime = microtime(true);

        // Create a large OpenAPI specification with 100 endpoints
        $paths = [];
        for ($i = 1; $i <= 100; $i++) {
            $resourceName = 'resource' . $i;
            $paths["/{$resourceName}"] = [
                'post' => [
                    'operationId' => "create{$resourceName}",
                    'summary' => "Create {$resourceName}",
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => [
                                            'type' => 'string',
                                            'minLength' => TestConstants::DEFAULT_MIN_STRING_LENGTH,
                                            'maxLength' => TestConstants::DEFAULT_MAX_STRING_LENGTH,
                                        ],
                                        'description' => [
                                            'type' => 'string',
                                            'maxLength' => TestConstants::TEST_MAX_DESCRIPTION_LENGTH,
                                        ],
                                        'status' => [
                                            'type' => 'string',
                                            'enum' => ['active', 'inactive', 'pending'],
                                        ],
                                        'priority' => [
                                            'type' => 'integer',
                                            'minimum' => 1,
                                            'maximum' => 10,
                                        ],
                                        'tags' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                            'minItems' => 0,
                                            'maxItems' => 10,
                                        ],
                                        'metadata' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'created_by' => ['type' => 'string'],
                                                'updated_by' => ['type' => 'string'],
                                                'version' => ['type' => 'integer'],
                                            ],
                                        ],
                                    ],
                                    'required' => ['name', 'status'],
                                ],
                            ],
                        ],
                    ],
                ],
                'put' => [
                    'operationId' => "update{$resourceName}",
                    'summary' => "Update {$resourceName}",
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => [
                                            'type' => 'string',
                                            'minLength' => TestConstants::DEFAULT_MIN_STRING_LENGTH,
                                            'maxLength' => TestConstants::DEFAULT_MAX_STRING_LENGTH,
                                        ],
                                        'description' => [
                                            'type' => 'string',
                                            'maxLength' => TestConstants::TEST_MAX_DESCRIPTION_LENGTH,
                                        ],
                                        'status' => [
                                            'type' => 'string',
                                            'enum' => ['active', 'inactive', 'pending'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $specData = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Large Test API',
                'version' => '1.0.0',
                'description' => 'A large OpenAPI specification for performance testing',
            ],
            'paths' => $paths,
        ];

        // Initialize services
        $referenceResolver = new ReferenceResolver;
        $schemaExtractor = new SchemaExtractor($referenceResolver);
        $parser = new OpenApiParser($schemaExtractor);
        $ruleMapper = new ValidationRuleMapper;
        $templateEngine = new TemplateEngine;
        $generator = new FormRequestGenerator($ruleMapper);

        // Parse specification
        $specification = OpenApiSpecification::fromArray($specData, 'large-test-spec.json');

        // Extract endpoints with request bodies
        $endpoints = $parser->getEndpointsWithRequestBodies($specification);

        // Generate FormRequest classes
        $formRequests = $generator->generateFromEndpoints(
            $endpoints,
            'App\\Http\\Requests',
            sys_get_temp_dir() . '/performance_test'
        );

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assertions
        expect(count($endpoints))->toBe(200); // 100 resources × 2 methods each
        expect(count($formRequests))->toBe(200);
        $timeLimit = $_ENV['PERFORMANCE_TIME_LIMIT'] ?? TestConstants::PERFORMANCE_TIME_LIMIT_SECONDS * 2; // More generous for large specs
        expect($executionTime)->toBeLessThan($timeLimit);

        // Log performance metrics for debugging
        echo "\nPerformance Metrics:\n";
        echo '- Total endpoints with request bodies: ' . count($endpoints) . "\n";
        echo '- Total FormRequest classes generated: ' . count($formRequests) . "\n";
        echo '- Execution time: ' . number_format($executionTime, 3) . " seconds\n";
        echo '- Average time per endpoint: ' . number_format($executionTime / count($endpoints) * 1000, 2) . " ms\n";

        // Validate a sample of generated FormRequest classes
        $sampleFormRequest = $formRequests[0];
        expect($sampleFormRequest)->toBeInstanceOf(FormRequestClass::class);
        expect($sampleFormRequest->className)->toMatch('/^Create\w+Request$/');
        expect($sampleFormRequest->validationRules)->toHaveKey('name');
        expect($sampleFormRequest->validationRules)->toHaveKey('status');
        expect($sampleFormRequest->validationRules['name'])->toContain('required');
        expect($sampleFormRequest->validationRules['name'])->toContain('string');
        expect($sampleFormRequest->validationRules['name'])->toContain('min:1');
        expect($sampleFormRequest->validationRules['name'])->toContain('max:255');
    });

    it('should handle deeply nested schemas efficiently', function () {
        $startTime = microtime(true);

        // Create a deeply nested schema structure
        $nestedSchema = [
            'type' => 'object',
            'properties' => [
                'level1' => [
                    'type' => 'object',
                    'properties' => [
                        'level2' => [
                            'type' => 'object',
                            'properties' => [
                                'level3' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'level4' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'level5' => [
                                                    'type' => 'array',
                                                    'items' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'data' => ['type' => 'string'],
                                                            'value' => ['type' => 'integer'],
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

        $specData = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Deep Nesting Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/deep' => [
                    'post' => [
                        'operationId' => 'createDeepStructure',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => $nestedSchema,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Initialize services
        $referenceResolver = new ReferenceResolver;
        $schemaExtractor = new SchemaExtractor($referenceResolver);
        $parser = new OpenApiParser($schemaExtractor);
        $ruleMapper = new ValidationRuleMapper;
        $templateEngine = new TemplateEngine;
        $generator = new FormRequestGenerator($ruleMapper);

        // Parse and generate
        $specification = OpenApiSpecification::fromArray($specData, 'large-test-spec.json');
        $endpoints = $parser->getEndpointsWithRequestBodies($specification);
        $formRequests = $generator->generateFromEndpoints(
            $endpoints,
            'App\\Http\\Requests',
            sys_get_temp_dir() . '/deep_test'
        );

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should handle deep nesting without performance issues
        $deepNestingLimit = $_ENV['DEEP_NESTING_TIME_LIMIT'] ?? TestConstants::PERFORMANCE_TIME_LIMIT_SECONDS / 5; // Faster for single endpoint
        expect($executionTime)->toBeLessThan($deepNestingLimit);
        expect(count($formRequests))->toBe(1);

        // Validate nested field validation rules were generated
        $formRequest = $formRequests[0];
        expect($formRequest->validationRules)->toHaveKey('level1');
        expect($formRequest->validationRules)->toHaveKey('level1.level2');
        expect($formRequest->validationRules)->toHaveKey('level1.level2.level3');
        expect($formRequest->validationRules)->toHaveKey('level1.level2.level3.level4');
        expect($formRequest->validationRules)->toHaveKey('level1.level2.level3.level4.level5');
        expect($formRequest->validationRules)->toHaveKey('level1.level2.level3.level4.level5.*');
        expect($formRequest->validationRules)->toHaveKey('level1.level2.level3.level4.level5.*.data');
        expect($formRequest->validationRules)->toHaveKey('level1.level2.level3.level4.level5.*.value');

        echo "\nDeep Nesting Performance:\n";
        echo '- Execution time: ' . number_format($executionTime, 3) . " seconds\n";
        echo '- Total validation rules generated: ' . count($formRequest->validationRules) . "\n";
    });

    it('should efficiently generate FormRequests with complex validation constraints', function () {
        $startTime = microtime(true);

        // Create schemas with complex validation constraints
        $paths = [];
        for ($i = 1; $i <= 50; $i++) {
            $paths["/complex{$i}"] = [
                'post' => [
                    'operationId' => "createComplex{$i}",
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => [
                                            'type' => 'string',
                                            'format' => 'email',
                                            'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                                            'minLength' => 5,
                                            'maxLength' => 254,
                                        ],
                                        'password' => [
                                            'type' => 'string',
                                            'pattern' => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$',
                                            'minLength' => 8,
                                            'maxLength' => 128,
                                        ],
                                        'age' => [
                                            'type' => 'integer',
                                            'minimum' => 0,
                                            'maximum' => 150,
                                            'multipleOf' => 1,
                                        ],
                                        'score' => [
                                            'type' => 'number',
                                            'minimum' => 0.0,
                                            'maximum' => 100.0,
                                            'multipleOf' => 0.01,
                                        ],
                                        'categories' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'string',
                                                'enum' => ['tech', 'science', 'arts', 'sports', 'music'],
                                            ],
                                            'minItems' => 1,
                                            'maxItems' => 5,
                                            'uniqueItems' => true,
                                        ],
                                        'metadata' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'source' => [
                                                    'type' => 'string',
                                                    'enum' => ['web', 'mobile', 'api', 'import'],
                                                ],
                                                'timestamp' => [
                                                    'type' => 'string',
                                                    'format' => 'date-time',
                                                ],
                                                'version' => [
                                                    'type' => 'string',
                                                    'pattern' => '^v\d+\.\d+\.\d+$',
                                                ],
                                            ],
                                            'required' => ['source', 'timestamp'],
                                        ],
                                    ],
                                    'required' => ['email', 'password', 'age'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $specData = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Complex Validation Test API',
                'version' => '1.0.0',
            ],
            'paths' => $paths,
        ];

        // Initialize services
        $referenceResolver = new ReferenceResolver;
        $schemaExtractor = new SchemaExtractor($referenceResolver);
        $parser = new OpenApiParser($schemaExtractor);
        $ruleMapper = new ValidationRuleMapper;
        $templateEngine = new TemplateEngine;
        $generator = new FormRequestGenerator($ruleMapper);

        // Parse and generate
        $specification = OpenApiSpecification::fromArray($specData, 'large-test-spec.json');
        $endpoints = $parser->getEndpointsWithRequestBodies($specification);
        $formRequests = $generator->generateFromEndpoints(
            $endpoints,
            'App\\Http\\Requests',
            sys_get_temp_dir() . '/complex_test'
        );

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should handle complex validation constraints efficiently
        $complexValidationLimit = $_ENV['COMPLEX_VALIDATION_TIME_LIMIT'] ?? TestConstants::PERFORMANCE_TIME_LIMIT_SECONDS / 2.5; // Medium complexity
        expect($executionTime)->toBeLessThan($complexValidationLimit);
        expect(count($formRequests))->toBe(50);

        // Validate complex validation rules were generated correctly
        $sampleFormRequest = $formRequests[0];
        expect($sampleFormRequest->validationRules)->toHaveKey('email');
        expect($sampleFormRequest->validationRules)->toHaveKey('password');
        expect($sampleFormRequest->validationRules)->toHaveKey('age');
        expect($sampleFormRequest->validationRules)->toHaveKey('categories');
        expect($sampleFormRequest->validationRules)->toHaveKey('metadata');

        // Check that complex constraints are properly mapped
        expect($sampleFormRequest->validationRules['email'])->toContain('required');
        expect($sampleFormRequest->validationRules['email'])->toContain('email');
        expect($sampleFormRequest->validationRules['email'])->toContain('min:5');
        expect($sampleFormRequest->validationRules['email'])->toContain('max:254');

        expect($sampleFormRequest->validationRules['categories'])->toContain('array');
        expect($sampleFormRequest->validationRules['categories'])->toContain('min:1');
        expect($sampleFormRequest->validationRules['categories'])->toContain('max:5');

        echo "\nComplex Validation Performance:\n";
        echo '- Execution time: ' . number_format($executionTime, 3) . " seconds\n";
        echo '- FormRequests generated: ' . count($formRequests) . "\n";
        echo '- Average rules per FormRequest: ' . number_format(array_sum(array_map(fn ($fr) => count($fr->validationRules), $formRequests)) / count($formRequests), 1) . "\n";
    });
});
