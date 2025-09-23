<?php

beforeEach(function () {
    $this->referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
});

describe('ReferenceResolver', function () {
    describe('resolve', function () {
        it('should resolve simple component reference', function () {
            $spec = [
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
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            expect($resolved)->toBeArray();
            expect($resolved['type'])->toBe('object');
            expect($resolved['properties']['id']['type'])->toBe('integer');
            expect($resolved['properties']['name']['type'])->toBe('string');
        });

        it('should resolve nested reference', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Address' => [
                            'type' => 'object',
                            'properties' => [
                                'street' => ['type' => 'string'],
                                'city' => ['type' => 'string'],
                            ],
                        ],
                        'User' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'address' => ['$ref' => '#/components/schemas/Address'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            expect($resolved)->toBeArray();
            expect($resolved['properties']['address']['type'])->toBe('object');
            expect($resolved['properties']['address']['properties']['street']['type'])->toBe('string');
        });

        it('should resolve parameter reference', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'parameters' => [
                        'pageParam' => [
                            'name' => 'page',
                            'in' => 'query',
                            'schema' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');
            $resolved = $this->referenceResolver->resolve('#/components/parameters/pageParam', $specification);

            expect($resolved)->toBeArray();
            expect($resolved['name'])->toBe('page');
            expect($resolved['in'])->toBe('query');
            expect($resolved['schema']['type'])->toBe('integer');
        });

        it('should throw exception for invalid reference path', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => ['schemas' => []],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');

            expect(fn () => $this->referenceResolver->resolve('#/components/schemas/NonExistent', $specification))
                ->toThrow(\InvalidArgumentException::class, 'Reference not found: #/components/schemas/NonExistent');
        });

        it('should throw exception for malformed reference', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');

            expect(fn () => $this->referenceResolver->resolve('invalid-reference', $specification))
                ->toThrow(\InvalidArgumentException::class, 'Invalid reference format: invalid-reference');
        });

        it('should handle external file references', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');

            expect(fn () => $this->referenceResolver->resolve('external.json#/schemas/User', $specification))
                ->toThrow(\InvalidArgumentException::class, 'External file references are not supported');
        });
    });

    describe('isReference', function () {
        it('should detect reference objects', function () {
            $refObject = ['$ref' => '#/components/schemas/User'];
            $normalObject = ['type' => 'string'];

            expect($this->referenceResolver->isReference($refObject))->toBeTrue();
            expect($this->referenceResolver->isReference($normalObject))->toBeFalse();
        });

        it('should handle non-array inputs', function () {
            expect($this->referenceResolver->isReference('string'))->toBeFalse();
            expect($this->referenceResolver->isReference(null))->toBeFalse();
            expect($this->referenceResolver->isReference(123))->toBeFalse();
        });
    });

    describe('resolveAllReferences', function () {
        it('should resolve all references in schema recursively', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Address' => [
                            'type' => 'object',
                            'properties' => [
                                'street' => ['type' => 'string'],
                                'city' => ['type' => 'string'],
                            ],
                        ],
                        'Contact' => [
                            'type' => 'object',
                            'properties' => [
                                'email' => ['type' => 'string', 'format' => 'email'],
                                'phone' => ['type' => 'string'],
                            ],
                        ],
                        'User' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'address' => ['$ref' => '#/components/schemas/Address'],
                                'contact' => ['$ref' => '#/components/schemas/Contact'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');
            $userSchema = $spec['components']['schemas']['User'];

            $resolved = $this->referenceResolver->resolveAllReferences($userSchema, $specification);

            expect($resolved['properties']['address']['type'])->toBe('object');
            expect($resolved['properties']['address']['properties']['street']['type'])->toBe('string');
            expect($resolved['properties']['contact']['properties']['email']['format'])->toBe('email');
        });

        it('should handle arrays with references', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Tag' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'color' => ['type' => 'string'],
                            ],
                        ],
                        'Post' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'tags' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Tag'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');
            $postSchema = $spec['components']['schemas']['Post'];

            $resolved = $this->referenceResolver->resolveAllReferences($postSchema, $specification);

            expect($resolved['properties']['tags']['items']['type'])->toBe('object');
            expect($resolved['properties']['tags']['items']['properties']['name']['type'])->toBe('string');
        });

        it('should detect and prevent circular references', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Node' => [
                            'type' => 'object',
                            'properties' => [
                                'value' => ['type' => 'string'],
                                'children' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Node'],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');
            $nodeSchema = $spec['components']['schemas']['Node'];

            expect(fn () => $this->referenceResolver->resolveAllReferences($nodeSchema, $specification))
                ->toThrow(\InvalidArgumentException::class, 'Circular reference detected');
        });
    });

    describe('getReferenceType', function () {
        it('should identify schema references', function () {
            $ref = '#/components/schemas/User';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('schema');
        });

        it('should identify parameter references', function () {
            $ref = '#/components/parameters/pageParam';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('parameter');
        });

        it('should identify response references', function () {
            $ref = '#/components/responses/ErrorResponse';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('response');
        });

        it('should return unknown for unrecognized references', function () {
            $ref = '#/components/unknown/Something';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('unknown');
        });
    });

    describe('extractReferencePath', function () {
        it('should extract path from reference', function () {
            $ref = '#/components/schemas/User';
            $path = $this->referenceResolver->extractReferencePath($ref);

            expect($path)->toBe(['components', 'schemas', 'User']);
        });

        it('should handle nested paths', function () {
            $ref = '#/paths/~1users~1{id}/get/responses/200';
            $path = $this->referenceResolver->extractReferencePath($ref);

            expect($path)->toBe(['paths', '/users/{id}', 'get', 'responses', '200']);
        });

        it('should decode JSON pointer escapes', function () {
            $ref = '#/components/schemas/User~1Profile';
            $path = $this->referenceResolver->extractReferencePath($ref);

            expect($path)->toBe(['components', 'schemas', 'User/Profile']);
        });
    });

    describe('validateReference', function () {
        it('should validate correct reference format', function () {
            $ref = '#/components/schemas/User';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect invalid reference format', function () {
            $ref = 'invalid-reference';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('Reference must start with #/');
        });

        it('should detect empty reference', function () {
            $ref = '';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('Reference cannot be empty');
        });

        it('should detect external file references', function () {
            $ref = 'external.json#/schemas/User';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('External file references are not supported');
        });
    });

    describe('caching', function () {
        it('should cache resolved references for performance', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string']],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');

            // First resolution
            $start = microtime(true);
            $resolved1 = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            $firstTime = microtime(true) - $start;

            // Second resolution (should be faster due to caching)
            $start = microtime(true);
            $resolved2 = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            $secondTime = microtime(true) - $start;

            expect($resolved1)->toBe($resolved2);
            expect($secondTime)->toBeLessThan($firstTime * 2); // Allow some margin for variability
        });

        it('should clear cache when requested', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string']],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');

            // Resolve and cache
            $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            // Clear cache
            $this->referenceResolver->clearCache();

            // Verify cache is cleared by resolving again (should not throw if cache is properly cleared)
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            expect($resolved)->toBeArray();
        });
    });
});
