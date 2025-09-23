<?php

namespace Maan511\OpenapiToLaravel\Tests\Integration;

use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use Maan511\OpenapiToLaravel\Tests\TestCase;

/**
 * Integration test for validation rule mapping
 *
 * This test validates the mapping from OpenAPI constraints to Laravel
 * validation rules across all supported constraint types.
 */
class ValidationMappingTest extends TestCase
{
    protected function createParser(): OpenApiParser
    {
        $referenceResolver = new ReferenceResolver;
        $schemaExtractor = new SchemaExtractor($referenceResolver);

        return new OpenApiParser($schemaExtractor, $referenceResolver);
    }

    protected function createGenerator(): FormRequestGenerator
    {
        $ruleMapper = new ValidationRuleMapper;
        $templateEngine = new TemplateEngine;

        return new FormRequestGenerator($ruleMapper, $templateEngine);
    }

    public function test_string_constraint_mapping()
    {
        // Test basic string constraint mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testStringConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email'],
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 5,
                                                'maxLength' => 100,
                                                'pattern' => '^[A-Z]+$',
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                            ],
                                            'website' => [
                                                'type' => 'string',
                                                'format' => 'url',
                                            ],
                                            'category' => [
                                                'type' => 'string',
                                                'enum' => ['a', 'b', 'c'],
                                            ],
                                            'uuid' => [
                                                'type' => 'string',
                                                'format' => 'uuid',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'string_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check basic string constraints are mapped
            $this->assertArrayHasKey('name', $rules);
            $this->assertArrayHasKey('email', $rules);
            $this->assertArrayHasKey('website', $rules);
            $this->assertArrayHasKey('category', $rules);
            $this->assertArrayHasKey('uuid', $rules);

            // Verify string type validation
            $this->assertStringContainsString('string', $rules['name']);
            $this->assertStringContainsString('string', $rules['email']);

            // Verify format-specific rules
            $this->assertStringContainsString('email', $rules['email']);
            $this->assertStringContainsString('url', $rules['website']);
            $this->assertStringContainsString('uuid', $rules['uuid']);

            // Verify enum mapping
            $this->assertStringContainsString('in:a,b,c', $rules['category']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_numeric_constraint_mapping()
    {
        // Test numeric constraint mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testNumericConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['age', 'price'],
                                        'properties' => [
                                            'age' => [
                                                'type' => 'integer',
                                                'minimum' => 0,
                                                'maximum' => 120,
                                            ],
                                            'price' => [
                                                'type' => 'number',
                                                'minimum' => 0.01,
                                                'maximum' => 999.99,
                                                'multipleOf' => 0.01,
                                            ],
                                            'rating' => [
                                                'type' => 'integer',
                                                'enum' => [1, 2, 3, 4, 5],
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

        $tempFile = tempnam(sys_get_temp_dir(), 'numeric_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check numeric types are mapped
            $this->assertArrayHasKey('age', $rules);
            $this->assertArrayHasKey('price', $rules);
            $this->assertArrayHasKey('rating', $rules);

            // Verify type validation
            $this->assertStringContainsString('integer', $rules['age']);
            $this->assertStringContainsString('numeric', $rules['price']);

            // Verify enum mapping for numbers
            $this->assertStringContainsString('in:1,2,3,4,5', $rules['rating']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_array_constraint_mapping()
    {
        // Test array constraint mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testArrayConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['tags'],
                                        'properties' => [
                                            'tags' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string'],
                                                'minItems' => 1,
                                                'maxItems' => 10,
                                                'uniqueItems' => true,
                                            ],
                                            'categories' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string', 'maxLength' => 50],
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

        $tempFile = tempnam(sys_get_temp_dir(), 'array_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check array types are mapped
            $this->assertArrayHasKey('tags', $rules);
            $this->assertArrayHasKey('categories', $rules);

            // Verify array type validation
            $this->assertStringContainsString('array', $rules['tags']);
            $this->assertStringContainsString('array', $rules['categories']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_object_constraint_mapping()
    {
        // Test object constraint mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testObjectConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['user'],
                                        'properties' => [
                                            'user' => [
                                                'type' => 'object',
                                                'required' => ['name', 'email'],
                                                'properties' => [
                                                    'name' => ['type' => 'string', 'minLength' => 1],
                                                    'email' => ['type' => 'string', 'format' => 'email'],
                                                    'age' => ['type' => 'integer', 'minimum' => 0],
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

        $tempFile = tempnam(sys_get_temp_dir(), 'object_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check nested object validation
            $this->assertArrayHasKey('user', $rules);
            $this->assertArrayHasKey('user.name', $rules);
            $this->assertArrayHasKey('user.email', $rules);
            $this->assertArrayHasKey('user.age', $rules);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_boolean_constraint_mapping()
    {
        // Test boolean constraint mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testBooleanConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['isActive'],
                                        'properties' => [
                                            'isActive' => ['type' => 'boolean'],
                                            'isPublic' => ['type' => 'boolean'],
                                            'hasPermission' => ['type' => 'boolean'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'boolean_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check boolean types are mapped
            $this->assertArrayHasKey('isActive', $rules);
            $this->assertArrayHasKey('isPublic', $rules);
            $this->assertArrayHasKey('hasPermission', $rules);

            // Verify boolean type validation
            $this->assertStringContainsString('boolean', $rules['isActive']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_required_field_mapping()
    {
        // Test required field mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testRequiredFields',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'email'],
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'phone' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'required_fields_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Required fields should have 'required' rule
            $this->assertStringContainsString('required', $rules['name']);
            $this->assertStringContainsString('required', $rules['email']);

            // Optional fields should not have 'required' rule
            $this->assertArrayHasKey('phone', $rules);
            $this->assertStringNotContainsString('required', $rules['phone']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_nested_object_mapping()
    {
        // Test nested object validation mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testNestedObjects',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['profile'],
                                        'properties' => [
                                            'profile' => [
                                                'type' => 'object',
                                                'required' => ['bio'],
                                                'properties' => [
                                                    'bio' => [
                                                        'type' => 'string',
                                                        'maxLength' => 500,
                                                    ],
                                                    'social' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'twitter' => [
                                                                'type' => 'string',
                                                                'nullable' => true,
                                                            ],
                                                            'github' => [
                                                                'type' => 'string',
                                                                'format' => 'url',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'nested_objects_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check nested object validation using dot notation
            $this->assertArrayHasKey('profile', $rules);
            $this->assertArrayHasKey('profile.bio', $rules);
            $this->assertArrayHasKey('profile.social', $rules);
            $this->assertArrayHasKey('profile.social.twitter', $rules);
            $this->assertArrayHasKey('profile.social.github', $rules);

            // Verify proper required/nullable rules
            $this->assertStringContainsString('required', $rules['profile']);
            $this->assertStringContainsString('required', $rules['profile.bio']);
            $this->assertStringContainsString('nullable', $rules['profile.social.twitter']);
            $this->assertStringContainsString('nullable', $rules['profile.social.github']); // Optional field

            // Verify type validation
            $this->assertStringContainsString('array', $rules['profile']); // Objects are arrays in Laravel
            $this->assertStringContainsString('string', $rules['profile.bio']);

            // Verify constraint mapping
            $this->assertStringContainsString('max:500', $rules['profile.bio']);
            $this->assertStringContainsString('url', $rules['profile.social.github']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_array_of_objects_mapping()
    {
        // Test array of objects validation mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testArrayOfObjects',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['addresses'],
                                        'properties' => [
                                            'addresses' => [
                                                'type' => 'array',
                                                'minItems' => 1,
                                                'items' => [
                                                    'type' => 'object',
                                                    'required' => ['street', 'city'],
                                                    'properties' => [
                                                        'street' => [
                                                            'type' => 'string',
                                                            'minLength' => 1,
                                                        ],
                                                        'city' => [
                                                            'type' => 'string',
                                                            'minLength' => 1,
                                                        ],
                                                        'zipcode' => [
                                                            'type' => 'string',
                                                            'pattern' => '^\d{5}$',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'array_objects_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check array of objects validation using * notation
            $this->assertArrayHasKey('addresses', $rules);
            $this->assertArrayHasKey('addresses.*', $rules);
            $this->assertArrayHasKey('addresses.*.street', $rules);
            $this->assertArrayHasKey('addresses.*.city', $rules);
            $this->assertArrayHasKey('addresses.*.zipcode', $rules);

            // Verify array validation
            $this->assertStringContainsString('array', $rules['addresses']);
            $this->assertStringContainsString('min:1', $rules['addresses']);

            // Verify array item validation (each object in the array)
            $this->assertStringContainsString('array', $rules['addresses.*']);

            // Verify required/nullable rules for object properties
            $this->assertStringContainsString('required', $rules['addresses.*.street']);
            $this->assertStringContainsString('required', $rules['addresses.*.city']);
            $this->assertStringContainsString('nullable', $rules['addresses.*.zipcode']); // Optional field

            // Verify constraint mapping
            $this->assertStringContainsString('min:1', $rules['addresses.*.street']);
            $this->assertStringContainsString('min:1', $rules['addresses.*.city']);
            $this->assertStringContainsString('regex:', $rules['addresses.*.zipcode']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_complex_format_mapping()
    {
        // Test comprehensive format mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testComplexFormats',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'website'],
                                        'properties' => [
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                            ],
                                            'website' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
                                            'birthdate' => [
                                                'type' => 'string',
                                                'format' => 'date',
                                            ],
                                            'created_at' => [
                                                'type' => 'string',
                                                'format' => 'date-time',
                                            ],
                                            'meeting_time' => [
                                                'type' => 'string',
                                                'format' => 'time',
                                            ],
                                            'user_id' => [
                                                'type' => 'string',
                                                'format' => 'uuid',
                                            ],
                                            'server_ip' => [
                                                'type' => 'string',
                                                'format' => 'ipv4',
                                            ],
                                            'server_ipv6' => [
                                                'type' => 'string',
                                                'format' => 'ipv6',
                                            ],
                                            'hostname' => [
                                                'type' => 'string',
                                                'format' => 'hostname',
                                            ],
                                            'encoded_data' => [
                                                'type' => 'string',
                                                'format' => 'byte',
                                            ],
                                            'file_upload' => [
                                                'type' => 'string',
                                                'format' => 'binary',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'complex_formats_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Test format-specific validation rules
            $this->assertStringContainsString('email', $rules['email']);
            $this->assertStringContainsString('url', $rules['website']);
            $this->assertStringContainsString('date_format:Y-m-d', $rules['birthdate']);
            $this->assertStringContainsString('date', $rules['created_at']);
            $this->assertStringContainsString('date_format:H:i:s', $rules['meeting_time']);
            $this->assertStringContainsString('uuid', $rules['user_id']);
            $this->assertStringContainsString('ipv4', $rules['server_ip']);
            $this->assertStringContainsString('ipv6', $rules['server_ipv6']);
            $this->assertStringContainsString('regex:', $rules['hostname']); // Hostname as regex
            $this->assertStringContainsString('regex:', $rules['encoded_data']); // Base64 regex
            $this->assertStringContainsString('file', $rules['file_upload']);

            // Verify all fields exist in rules
            $expectedFields = [
                'email', 'website', 'birthdate', 'created_at', 'meeting_time',
                'user_id', 'server_ip', 'server_ipv6', 'hostname', 'encoded_data', 'file_upload',
            ];

            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $rules, "Field '{$field}' should exist in validation rules");
            }

        } finally {
            unlink($tempFile);
        }
    }

    public function test_pattern_regex_mapping()
    {
        // Test regex pattern mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testPatternRegex',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['username', 'phone'],
                                        'properties' => [
                                            'username' => [
                                                'type' => 'string',
                                                'pattern' => '^[A-Z]+$',
                                            ],
                                            'phone' => [
                                                'type' => 'string',
                                                'pattern' => '^\+\d{1,3}\d{10}$',
                                            ],
                                            'product_code' => [
                                                'type' => 'string',
                                                'pattern' => '^[A-Z]{2}-\d{4}$',
                                            ],
                                            'complex_pattern' => [
                                                'type' => 'string',
                                                'pattern' => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'pattern_regex_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check pattern regex validation rules
            $this->assertArrayHasKey('username', $rules);
            $this->assertArrayHasKey('phone', $rules);
            $this->assertArrayHasKey('product_code', $rules);
            $this->assertArrayHasKey('complex_pattern', $rules);

            // Verify regex rules are properly formatted
            $this->assertStringContainsString('regex:', $rules['username']);
            $this->assertStringContainsString('regex:', $rules['phone']);
            $this->assertStringContainsString('regex:', $rules['product_code']);
            $this->assertStringContainsString('regex:', $rules['complex_pattern']);

            // Verify patterns are properly escaped and delimited
            $this->assertStringContainsString('/^[A-Z]+$/', $rules['username']);
            $this->assertStringContainsString('/^\+\d{1,3}\d{10}$/', $rules['phone']);
            $this->assertStringContainsString('/^[A-Z]{2}-\d{4}$/', $rules['product_code']);

            // Check that all fields have proper string and required rules
            $this->assertStringContainsString('string', $rules['username']);
            $this->assertStringContainsString('required', $rules['username']);
            $this->assertStringContainsString('nullable', $rules['product_code']); // Optional field

        } finally {
            unlink($tempFile);
        }
    }

    public function test_enum_value_mapping()
    {
        // Test enum value mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testEnumValues',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['status'],
                                        'properties' => [
                                            'status' => [
                                                'type' => 'string',
                                                'enum' => ['active', 'inactive', 'pending'],
                                            ],
                                            'priority' => [
                                                'type' => 'integer',
                                                'enum' => [1, 2, 3, 4, 5],
                                            ],
                                            'type' => [
                                                'type' => 'string',
                                                'enum' => ['public', 'private'],
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

        $tempFile = tempnam(sys_get_temp_dir(), 'enum_values_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check enum fields exist
            $this->assertArrayHasKey('status', $rules);
            $this->assertArrayHasKey('priority', $rules);
            $this->assertArrayHasKey('type', $rules);

            // Verify enum validation rules - Laravel uses 'in:' rule
            $this->assertStringContainsString('in:', $rules['status']);
            $this->assertStringContainsString('in:', $rules['priority']);
            $this->assertStringContainsString('in:', $rules['type']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_combined_constraint_mapping()
    {
        // Test multiple constraints on single field
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testCombinedConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'price'],
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 5,
                                                'maxLength' => 100,
                                                'pattern' => '^[A-Z]',
                                            ],
                                            'price' => [
                                                'type' => 'number',
                                                'minimum' => 0.01,
                                                'maximum' => 999.99,
                                                'multipleOf' => 0.01,
                                            ],
                                            'tags' => [
                                                'type' => 'array',
                                                'minItems' => 1,
                                                'maxItems' => 10,
                                                'uniqueItems' => true,
                                                'items' => [
                                                    'type' => 'string',
                                                    'maxLength' => 50,
                                                ],
                                            ],
                                            'category' => [
                                                'type' => 'string',
                                                'enum' => ['electronics', 'books', 'clothing'],
                                                'minLength' => 3,
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

        $tempFile = tempnam(sys_get_temp_dir(), 'combined_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Test combined string constraints
            $this->assertStringContainsString('required', $rules['name']);
            $this->assertStringContainsString('string', $rules['name']);
            $this->assertStringContainsString('min:5', $rules['name']);
            $this->assertStringContainsString('max:100', $rules['name']);
            $this->assertStringContainsString('regex:', $rules['name']);

            // Test combined numeric constraints
            $this->assertStringContainsString('required', $rules['price']);
            $this->assertStringContainsString('numeric', $rules['price']);
            $this->assertStringContainsString('min:0.01', $rules['price']);
            $this->assertStringContainsString('max:999.99', $rules['price']);
            $this->assertStringContainsString('multiple_of:0.01', $rules['price']);

            // Test combined array constraints
            $this->assertStringContainsString('array', $rules['tags']);
            $this->assertStringContainsString('min:1', $rules['tags']);
            $this->assertStringContainsString('max:10', $rules['tags']);
            $this->assertStringContainsString('distinct', $rules['tags']);

            // Test array items constraints
            $this->assertArrayHasKey('tags.*', $rules);
            $this->assertStringContainsString('string', $rules['tags.*']);
            $this->assertStringContainsString('max:50', $rules['tags.*']);

            // Test combined enum and other constraints
            $this->assertStringContainsString('string', $rules['category']);
            $this->assertStringContainsString('in:electronics,books,clothing', $rules['category']);
            $this->assertStringContainsString('min:3', $rules['category']);

            // Verify no duplicate rules exist
            $nameParts = explode('|', $rules['name']);
            $this->assertEquals(count($nameParts), count(array_unique($nameParts)),
                'No duplicate rules should exist in name field');

        } finally {
            unlink($tempFile);
        }
    }

    public function test_nullable_field_mapping()
    {
        // Test nullable field mapping
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testNullableFields',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name'],
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'description' => [
                                                'type' => 'string',
                                                'nullable' => true,
                                            ],
                                            'age' => [
                                                'type' => 'integer',
                                                'nullable' => true,
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

        $tempFile = tempnam(sys_get_temp_dir(), 'nullable_fields_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Non-nullable field should not have nullable rule
            $this->assertStringNotContainsString('nullable', $rules['name']);

            // Nullable fields should have nullable rule
            $this->assertStringContainsString('nullable', $rules['description']);
            $this->assertStringContainsString('nullable', $rules['age']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_default_value_handling()
    {
        // Test default value handling
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testDefaultValues',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => [
                                                'type' => 'string',
                                                'default' => 'pending',
                                            ],
                                            'priority' => [
                                                'type' => 'integer',
                                                'default' => 1,
                                            ],
                                            'active' => [
                                                'type' => 'boolean',
                                                'default' => true,
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

        $tempFile = tempnam(sys_get_temp_dir(), 'default_values_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];

            // Check that fields with defaults exist in rules
            $this->assertArrayHasKey('status', $formRequest->validationRules);
            $this->assertArrayHasKey('priority', $formRequest->validationRules);
            $this->assertArrayHasKey('active', $formRequest->validationRules);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_validation_rule_order()
    {
        // Test proper rule ordering
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testRuleOrder',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email'],
                                        'properties' => [
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                                'minLength' => 5,
                                                'maxLength' => 100,
                                                'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                                            ],
                                            'optional_field' => [
                                                'type' => 'string',
                                                'nullable' => true,
                                                'minLength' => 1,
                                                'enum' => ['option1', 'option2', 'option3'],
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

        $tempFile = tempnam(sys_get_temp_dir(), 'rule_order_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Test preferred rule order for required field: required|type|size|format|pattern|enum
            $emailRuleParts = explode('|', $rules['email']);

            // Check that required comes first
            $this->assertEquals('required', $emailRuleParts[0], 'Required rule should come first');

            // Check that type comes early (string)
            $stringIndex = array_search('string', $emailRuleParts);
            $this->assertNotFalse($stringIndex, 'String type rule should exist');
            $this->assertLessThan(3, $stringIndex, 'Type rule should come early');

            // Check that format rules exist
            $this->assertContains('email', $emailRuleParts, 'Email format rule should exist');

            // Test preferred rule order for nullable field: nullable|type|size|format|pattern|enum
            $optionalRuleParts = explode('|', $rules['optional_field']);

            // Check that nullable comes first for optional fields
            $this->assertEquals('nullable', $optionalRuleParts[0], 'Nullable rule should come first for optional fields');

            // Check that type comes after nullable
            $stringIndex = array_search('string', $optionalRuleParts);
            $this->assertNotFalse($stringIndex, 'String type rule should exist');
            $this->assertGreaterThan(0, $stringIndex, 'Type rule should come after nullable');

            // Check that enum rule exists
            $this->assertStringContainsString('in:option1,option2,option3', $rules['optional_field']);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_unsupported_constraint_handling()
    {
        // Test handling of unsupported OpenAPI constraints
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testUnsupportedConstraints',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['name', 'data'],
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 1,
                                                'maxLength' => 100,
                                                'readOnly' => true, // Unsupported constraint
                                                'xml' => ['name' => 'userName'], // Unsupported constraint
                                            ],
                                            'data' => [
                                                'type' => 'object',
                                                'writeOnly' => true, // Unsupported constraint
                                                'properties' => [
                                                    'value' => [
                                                        'type' => 'string',
                                                        'deprecated' => true, // Unsupported constraint
                                                    ],
                                                ],
                                            ],
                                            'external_docs' => [
                                                'type' => 'string',
                                                'externalDocs' => ['url' => 'https://example.com'], // Unsupported constraint
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

        $tempFile = tempnam(sys_get_temp_dir(), 'unsupported_constraints_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Should still generate validation rules for supported constraints
            $this->assertArrayHasKey('name', $rules);
            $this->assertArrayHasKey('data', $rules);
            $this->assertArrayHasKey('data.value', $rules);
            $this->assertArrayHasKey('external_docs', $rules);

            // Should include supported constraints despite unsupported ones
            $this->assertStringContainsString('required', $rules['name']);
            $this->assertStringContainsString('string', $rules['name']);
            $this->assertStringContainsString('min:1', $rules['name']);
            $this->assertStringContainsString('max:100', $rules['name']);

            // Should handle nested objects with unsupported constraints
            $this->assertStringContainsString('required', $rules['data']);
            $this->assertStringContainsString('array', $rules['data']); // Objects as arrays
            $this->assertStringContainsString('nullable', $rules['data.value']); // Optional nested field
            $this->assertStringContainsString('string', $rules['data.value']);

            // Should handle optional fields with unsupported constraints
            $this->assertStringContainsString('nullable', $rules['external_docs']);
            $this->assertStringContainsString('string', $rules['external_docs']);

            // Should continue generation successfully despite unsupported constraints
            $this->assertGreaterThan(0, count($rules));

        } finally {
            unlink($tempFile);
        }
    }

    public function test_custom_validation_rule_generation()
    {
        // Test custom Laravel validation rule handling for complex constraints
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testCustomRules',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['price', 'score'],
                                        'properties' => [
                                            'price' => [
                                                'type' => 'number',
                                                'multipleOf' => 0.05, // Requires custom rule
                                            ],
                                            'score' => [
                                                'type' => 'number',
                                                'multipleOf' => 2.5, // Requires custom rule
                                            ],
                                            'complex_pattern' => [
                                                'type' => 'string',
                                                'pattern' => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'custom_rules_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Check that multipleOf constraints are handled
            $this->assertArrayHasKey('price', $rules);
            $this->assertArrayHasKey('score', $rules);
            $this->assertArrayHasKey('complex_pattern', $rules);

            // Verify that multipleOf generates custom rule references
            $this->assertStringContainsString('multiple_of:0.05', $rules['price']);
            $this->assertStringContainsString('multiple_of:2.5', $rules['score']);

            // Verify basic type and required rules are still present
            $this->assertStringContainsString('required', $rules['price']);
            $this->assertStringContainsString('numeric', $rules['price']);
            $this->assertStringContainsString('required', $rules['score']);
            $this->assertStringContainsString('numeric', $rules['score']);

            // Complex regex patterns should still work as regex rules
            $this->assertStringContainsString('regex:', $rules['complex_pattern']);
            $this->assertStringContainsString('nullable', $rules['complex_pattern']); // Optional field

            // Should generate complete rule sets including custom rule placeholders
            $priceParts = explode('|', $rules['price']);
            $this->assertGreaterThan(2, count($priceParts), 'Price should have multiple validation rules');

        } finally {
            unlink($tempFile);
        }
    }

    public function test_validation_message_customization()
    {
        // Test custom validation message handling
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testMessageCustomization',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['email', 'name'],
                                        'properties' => [
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                                'description' => 'User email address for notifications',
                                            ],
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 2,
                                                'maxLength' => 50,
                                                'description' => 'Full name of the user',
                                            ],
                                            'age' => [
                                                'type' => 'integer',
                                                'minimum' => 18,
                                                'maximum' => 120,
                                                'description' => 'Age must be between 18 and 120',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'message_customization_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];

            // Check that FormRequest includes custom messages capability
            $this->assertIsArray($formRequest->customMessages);

            // Check that default Laravel validation rules are generated
            $rules = $formRequest->validationRules;
            $this->assertArrayHasKey('email', $rules);
            $this->assertArrayHasKey('name', $rules);
            $this->assertArrayHasKey('age', $rules);

            // Verify that descriptions from OpenAPI could be used for custom messages
            // (The current implementation may not use descriptions yet, but the structure supports it)
            $this->assertStringContainsString('email', $rules['email']);
            $this->assertStringContainsString('string', $rules['name']);
            $this->assertStringContainsString('integer', $rules['age']);

            // Verify that the FormRequest can accept custom messages in generation
            $customMessages = ['email.required' => 'Please provide your email address'];
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');
            $customFormRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestCustomRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestCustomRequest.php',
                validationRules: $rules,
                sourceSchema: $schema,
                customMessages: $customMessages
            );

            $this->assertEquals($customMessages, $customFormRequest->customMessages);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_field_attribute_name_customization()
    {
        // Test custom field attribute name handling
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testAttributeCustomization',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['user_email', 'first_name'],
                                        'properties' => [
                                            'user_email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                                'title' => 'Email Address',
                                            ],
                                            'first_name' => [
                                                'type' => 'string',
                                                'minLength' => 1,
                                                'title' => 'First Name',
                                            ],
                                            'phone_number' => [
                                                'type' => 'string',
                                                'title' => 'Phone Number',
                                                'pattern' => '^\+\d{10,15}$',
                                            ],
                                            'date_of_birth' => [
                                                'type' => 'string',
                                                'format' => 'date',
                                                'title' => 'Date of Birth',
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

        $tempFile = tempnam(sys_get_temp_dir(), 'attribute_customization_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];

            // Check that FormRequest includes custom attributes capability
            $this->assertIsArray($formRequest->customAttributes);

            // Check that validation rules are generated correctly
            $rules = $formRequest->validationRules;
            $this->assertArrayHasKey('user_email', $rules);
            $this->assertArrayHasKey('first_name', $rules);
            $this->assertArrayHasKey('phone_number', $rules);
            $this->assertArrayHasKey('date_of_birth', $rules);

            // Verify that titles from OpenAPI could be used for custom attributes
            // (The current implementation may not extract titles yet, but the structure supports it)
            $this->assertStringContainsString('email', $rules['user_email']);
            $this->assertStringContainsString('string', $rules['first_name']);
            $this->assertStringContainsString('regex:', $rules['phone_number']);
            $this->assertStringContainsString('date_format:Y-m-d', $rules['date_of_birth']);

            // Verify that the FormRequest can accept custom attributes in generation
            $customAttributes = [
                'user_email' => 'Email Address',
                'first_name' => 'First Name',
                'phone_number' => 'Phone Number',
                'date_of_birth' => 'Date of Birth',
            ];

            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');
            $customFormRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestAttributeRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestAttributeRequest.php',
                validationRules: $rules,
                sourceSchema: $schema,
                customAttributes: $customAttributes
            );

            $this->assertEquals($customAttributes, $customFormRequest->customAttributes);

        } finally {
            unlink($tempFile);
        }
    }

    public function test_validation_mapping_performance()
    {
        // Test mapping performance with large schema
        $properties = [];

        // Generate 100+ fields to test performance
        for ($i = 1; $i <= 150; $i++) {
            $properties["field_{$i}"] = [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 100,
                'pattern' => '^[a-zA-Z0-9]+$',
            ];

            if ($i % 10 === 0) {
                $properties["nested_object_{$i}"] = [
                    'type' => 'object',
                    'properties' => [
                        'sub_field_1' => ['type' => 'string', 'format' => 'email'],
                        'sub_field_2' => ['type' => 'integer', 'minimum' => 0],
                        'sub_field_3' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ];
            }

            if ($i % 20 === 0) {
                $properties["array_field_{$i}"] = [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 10,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item_name' => ['type' => 'string', 'minLength' => 1],
                            'item_value' => ['type' => 'number', 'minimum' => 0],
                        ],
                    ],
                ];
            }
        }

        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'post' => [
                        'operationId' => 'testPerformance',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['field_1', 'field_50', 'field_100'],
                                        'properties' => $properties,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'performance_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        try {
            $parser = $this->createParser();
            $generator = $this->createGenerator();

            // Measure parsing and generation time
            $startTime = microtime(true);

            $parsedSpec = $parser->parseFromFile($tempFile);
            $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            $this->assertNotEmpty($formRequests);

            $formRequest = $formRequests[0];
            $rules = $formRequest->validationRules;

            // Should handle large number of fields efficiently
            $this->assertGreaterThan(150, count($rules), 'Should generate rules for 150+ fields');

            // Should complete in reasonable time (less than 2 seconds for this test)
            $this->assertLessThan(2.0, $executionTime,
                "Mapping 150+ fields should complete in under 2 seconds, took {$executionTime}s");

            // Check memory usage is reasonable
            $memoryUsage = memory_get_peak_usage(true);
            $memoryMB = $memoryUsage / 1024 / 1024;
            $this->assertLessThan(128, $memoryMB,
                "Memory usage should be under 128MB, used {$memoryMB}MB");

            // Verify that complex nested structures are handled
            $nestedFieldCount = 0;
            $arrayFieldCount = 0;

            foreach ($rules as $fieldPath => $rule) {
                if (str_contains($fieldPath, '.')) {
                    $nestedFieldCount++;
                }
                if (str_contains($fieldPath, '*')) {
                    $arrayFieldCount++;
                }
            }

            $this->assertGreaterThan(10, $nestedFieldCount, 'Should handle nested objects');
            $this->assertGreaterThan(5, $arrayFieldCount, 'Should handle array fields');

            // Verify that validation rules are still properly formatted
            $sampleRules = array_slice($rules, 0, 5, true);
            foreach ($sampleRules as $fieldPath => $rule) {
                $this->assertIsString($rule, "Rule for {$fieldPath} should be a string");
                $this->assertNotEmpty($rule, "Rule for {$fieldPath} should not be empty");
                $this->assertStringNotContainsString('||', $rule, "Rule for {$fieldPath} should not have duplicate separators");
            }

        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Helper method to get a comprehensive validation schema for testing
     */
    private function getComprehensiveValidationSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'email', 'age', 'tags'],
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 100,
                    'pattern' => '^[a-zA-Z ]+$',
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
                'website' => [
                    'type' => 'string',
                    'format' => 'uri',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                ],
                'score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                    'multipleOf' => 0.5,
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'maxLength' => 50,
                    ],
                    'minItems' => 1,
                    'maxItems' => 10,
                    'uniqueItems' => true,
                ],
                'verified' => [
                    'type' => 'boolean',
                ],
                'birthdate' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'created_at' => [
                    'type' => 'string',
                    'format' => 'date-time',
                ],
                'user_id' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
                'ip_address' => [
                    'type' => 'string',
                    'format' => 'ipv4',
                ],
            ],
        ];
    }
}
