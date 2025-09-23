<?php

beforeEach(function () {
    $this->referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
    $this->schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($this->referenceResolver);
});

describe('SchemaExtractor', function () {
    describe('extractFromRequestBody', function () {
        it('should extract schema from simple request body', function () {
            $requestBody = [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'email' => ['type' => 'string', 'format' => 'email'],
                            ],
                            'required' => ['name'],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromRequestBody($requestBody, $specification);

            expect($schema)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\SchemaObject::class);
            expect($schema->type)->toBe('object');
            expect($schema->properties)->toHaveKey('name');
            expect($schema->properties)->toHaveKey('email');
            expect($schema->required)->toBe(['name']);
        });

        it('should prefer application/json content type', function () {
            $requestBody = [
                'content' => [
                    'application/xml' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['xml_field' => ['type' => 'string']],
                        ],
                    ],
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['json_field' => ['type' => 'string']],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromRequestBody($requestBody, $specification);

            expect($schema->properties)->toHaveKey('json_field');
            expect($schema->properties)->not->toHaveKey('xml_field');
        });

        it('should fall back to first available content type', function () {
            $requestBody = [
                'content' => [
                    'application/xml' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['xml_field' => ['type' => 'string']],
                        ],
                    ],
                    'application/x-www-form-urlencoded' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => ['form_field' => ['type' => 'string']],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromRequestBody($requestBody, $specification);

            expect($schema->properties)->toHaveKey('xml_field');
        });

        it('should resolve schema references', function () {
            $requestBody = [
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/User'],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromRequestBody($requestBody, $specification);

            expect($schema->type)->toBe('object');
            expect($schema->properties)->toHaveKey('id');
            expect($schema->properties)->toHaveKey('name');
        });

        it('should throw exception for missing content', function () {
            $requestBody = [];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            expect(fn () => $this->schemaExtractor->extractFromRequestBody($requestBody, $specification))
                ->toThrow(\InvalidArgumentException::class, 'No content found in request body');
        });
    });

    describe('extractFromParameters', function () {
        it('should extract schema from parameters', function () {
            $parameters = [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'integer'],
                ],
                [
                    'name' => 'filter',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                ],
                [
                    'name' => 'sort',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => ['name', 'date']],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromParameters($parameters, $specification);

            expect($schema->type)->toBe('object');
            expect($schema->properties)->toHaveKey('id');
            expect($schema->properties)->toHaveKey('filter');
            expect($schema->properties)->toHaveKey('sort');
            expect($schema->required)->toBe(['id']);
            expect($schema->properties['sort']->validation->enum)->toBe(['name', 'date']);
        });

        it('should skip header and cookie parameters', function () {
            $parameters = [
                [
                    'name' => 'user_id',
                    'in' => 'query',
                    'required' => true,
                    'schema' => ['type' => 'integer'],
                ],
                [
                    'name' => 'Authorization',
                    'in' => 'header',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ],
                [
                    'name' => 'session_id',
                    'in' => 'cookie',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromParameters($parameters, $specification);

            expect($schema->properties)->toHaveKey('user_id');
            expect($schema->properties)->not->toHaveKey('Authorization');
            expect($schema->properties)->not->toHaveKey('session_id');
        });

        it('should resolve parameter references', function () {
            $parameters = [
                ['$ref' => '#/components/parameters/PageParam'],
                [
                    'name' => 'filter',
                    'in' => 'query',
                    'schema' => ['type' => 'string'],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'parameters' => [
                        'PageParam' => [
                            'name' => 'page',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromParameters($parameters, $specification);

            expect($schema->properties)->toHaveKey('page');
            expect($schema->properties)->toHaveKey('filter');
            expect($schema->properties['page']->validation->minimum)->toBe(1);
        });

        it('should return null for empty parameters', function () {
            $parameters = [];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ], 'test.json');

            $schema = $this->schemaExtractor->extractFromParameters($parameters, $specification);

            expect($schema)->toBeNull();
        });
    });

    describe('createSchemaObject', function () {
        it('should create simple schema object', function () {
            $schemaData = [
                'type' => 'string',
                'minLength' => 3,
                'maxLength' => 50,
            ];

            $schema = $this->schemaExtractor->createSchemaObject($schemaData);

            expect($schema)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\SchemaObject::class);
            expect($schema->type)->toBe('string');
            expect($schema->validation->minLength)->toBe(3);
            expect($schema->validation->maxLength)->toBe(50);
        });

        it('should create object schema with properties', function () {
            $schemaData = [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer', 'minimum' => 0],
                ],
                'required' => ['name'],
            ];

            $schema = $this->schemaExtractor->createSchemaObject($schemaData);

            expect($schema->type)->toBe('object');
            expect($schema->properties)->toHaveKey('name');
            expect($schema->properties)->toHaveKey('age');
            expect($schema->required)->toBe(['name']);
            expect($schema->properties['age']->validation->minimum)->toBe(0);
        });

        it('should create array schema with items', function () {
            $schemaData = [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'minItems' => 1,
                'maxItems' => 10,
            ];

            $schema = $this->schemaExtractor->createSchemaObject($schemaData);

            expect($schema->type)->toBe('array');
            expect($schema->items->type)->toBe('string');
            expect($schema->validation->minItems)->toBe(1);
            expect($schema->validation->maxItems)->toBe(10);
        });

        it('should handle schema with format', function () {
            $schemaData = [
                'type' => 'string',
                'format' => 'email',
            ];

            $schema = $this->schemaExtractor->createSchemaObject($schemaData);

            expect($schema->type)->toBe('string');
            expect($schema->format)->toBe('email');
        });

        it('should handle schema with enum', function () {
            $schemaData = [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'pending'],
            ];

            $schema = $this->schemaExtractor->createSchemaObject($schemaData);

            expect($schema->validation->enum)->toBe(['active', 'inactive', 'pending']);
        });

        it('should handle schema with pattern', function () {
            $schemaData = [
                'type' => 'string',
                'pattern' => '^[A-Z][a-z]+$',
            ];

            $schema = $this->schemaExtractor->createSchemaObject($schemaData);

            expect($schema->validation->pattern)->toBe('^[A-Z][a-z]+$');
        });
    });

    describe('extractValidationConstraints', function () {
        it('should extract string constraints', function () {
            $schemaData = [
                'type' => 'string',
                'minLength' => 5,
                'maxLength' => 100,
                'pattern' => '^[a-zA-Z]+$',
                'enum' => ['option1', 'option2'],
            ];

            $constraints = $this->schemaExtractor->extractValidationConstraints($schemaData);

            expect($constraints->minLength)->toBe(5);
            expect($constraints->maxLength)->toBe(100);
            expect($constraints->pattern)->toBe('^[a-zA-Z]+$');
            expect($constraints->enum)->toBe(['option1', 'option2']);
        });

        it('should extract numeric constraints', function () {
            $schemaData = [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 1000,
                'multipleOf' => 5,
            ];

            $constraints = $this->schemaExtractor->extractValidationConstraints($schemaData);

            expect($constraints->minimum)->toBe(0);
            expect($constraints->maximum)->toBe(1000);
            expect($constraints->multipleOf)->toBe(5);
        });

        it('should extract array constraints', function () {
            $schemaData = [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 20,
                'uniqueItems' => true,
            ];

            $constraints = $this->schemaExtractor->extractValidationConstraints($schemaData);

            expect($constraints->minItems)->toBe(1);
            expect($constraints->maxItems)->toBe(20);
            expect($constraints->uniqueItems)->toBeTrue();
        });

        it('should handle missing constraints', function () {
            $schemaData = ['type' => 'string'];

            $constraints = $this->schemaExtractor->extractValidationConstraints($schemaData);

            expect($constraints->minLength)->toBeNull();
            expect($constraints->maxLength)->toBeNull();
            expect($constraints->pattern)->toBeNull();
        });
    });

    describe('getSchemaType', function () {
        it('should return schema type from type field', function () {
            $schemaData = ['type' => 'string'];
            $type = $this->schemaExtractor->getSchemaType($schemaData);

            expect($type)->toBe('string');
        });

        it('should return object for schemas with properties', function () {
            $schemaData = [
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ];
            $type = $this->schemaExtractor->getSchemaType($schemaData);

            expect($type)->toBe('object');
        });

        it('should return array for schemas with items', function () {
            $schemaData = [
                'items' => ['type' => 'string'],
            ];
            $type = $this->schemaExtractor->getSchemaType($schemaData);

            expect($type)->toBe('array');
        });

        it('should return null for schemas without clear type', function () {
            $schemaData = ['description' => 'Some description'];
            $type = $this->schemaExtractor->getSchemaType($schemaData);

            expect($type)->toBeNull();
        });
    });

    describe('isSchemaObject', function () {
        it('should identify object schemas', function () {
            $objectSchema = ['type' => 'object'];
            $nonObjectSchema = ['type' => 'string'];

            expect($this->schemaExtractor->isSchemaObject($objectSchema))->toBeTrue();
            expect($this->schemaExtractor->isSchemaObject($nonObjectSchema))->toBeFalse();
        });

        it('should identify implicit object schemas', function () {
            $implicitObjectSchema = [
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ];

            expect($this->schemaExtractor->isSchemaObject($implicitObjectSchema))->toBeTrue();
        });
    });

    describe('mergeSchemas', function () {
        it('should merge object schemas', function () {
            $schema1 = [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
                'required' => ['name'],
            ];

            $schema2 = [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'age' => ['type' => 'number'], // Should override
                ],
                'required' => ['email'],
            ];

            $merged = $this->schemaExtractor->mergeSchemas($schema1, $schema2);

            expect($merged['properties'])->toHaveKey('name');
            expect($merged['properties'])->toHaveKey('email');
            expect($merged['properties']['age']['type'])->toBe('number'); // Second schema takes precedence
            expect($merged['required'])->toBe(['name', 'email']);
        });

        it('should handle non-object schemas', function () {
            $schema1 = ['type' => 'string'];
            $schema2 = ['type' => 'integer'];

            $merged = $this->schemaExtractor->mergeSchemas($schema1, $schema2);

            expect($merged['type'])->toBe('integer'); // Second schema overwrites
        });
    });

    describe('validateSchemaData', function () {
        it('should validate correct schema data', function () {
            $schemaData = [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ];

            $result = $this->schemaExtractor->validateSchemaData($schemaData);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect invalid schema structure', function () {
            $schemaData = [
                'properties' => 'invalid_properties_format',
            ];

            $result = $this->schemaExtractor->validateSchemaData($schemaData);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });

        it('should detect missing required fields', function () {
            $schemaData = []; // Empty schema

            $result = $this->schemaExtractor->validateSchemaData($schemaData);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('Schema must have either type, properties, or items');
        });
    });
});
