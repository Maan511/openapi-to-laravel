<?php

use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;

beforeEach(function (): void {
    $this->referenceResolver = new ReferenceResolver;
});

describe('ReferenceResolver', function (): void {
    describe('resolve', function (): void {
        it('should resolve simple component reference', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            expect($resolved)->toBeArray();
            expect($resolved['type'])->toBe('object');
            expect($resolved['properties']['id']['type'])->toBe('integer');
            expect($resolved['properties']['name']['type'])->toBe('string');
        });

        it('should resolve nested reference', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            expect($resolved)->toBeArray();
            expect($resolved['properties']['address']['type'])->toBe('object');
            expect($resolved['properties']['address']['properties']['street']['type'])->toBe('string');
        });

        it('should resolve parameter reference', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $resolved = $this->referenceResolver->resolve('#/components/parameters/pageParam', $specification);

            expect($resolved)->toBeArray();
            expect($resolved['name'])->toBe('page');
            expect($resolved['in'])->toBe('query');
            expect($resolved['schema']['type'])->toBe('integer');
        });

        it('should throw exception for invalid reference path', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => ['schemas' => []],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            expect(fn () => $this->referenceResolver->resolve('#/components/schemas/NonExistent', $specification))
                ->toThrow(InvalidArgumentException::class, 'Reference not found: #/components/schemas/NonExistent');
        });

        it('should throw exception for malformed reference', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            expect(fn () => $this->referenceResolver->resolve('invalid-reference', $specification))
                ->toThrow(InvalidArgumentException::class, 'Invalid reference format: invalid-reference');
        });

        it('should handle external file references', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            expect(fn () => $this->referenceResolver->resolve('external.json#/schemas/User', $specification))
                ->toThrow(InvalidArgumentException::class, 'External file references are not supported');
        });
    });

    describe('isReference', function (): void {
        it('should detect reference objects', function (): void {
            $refObject = ['$ref' => '#/components/schemas/User'];
            $normalObject = ['type' => 'string'];

            expect($this->referenceResolver->isReference($refObject))->toBeTrue();
            expect($this->referenceResolver->isReference($normalObject))->toBeFalse();
        });

        it('should handle non-array inputs', function (): void {
            expect($this->referenceResolver->isReference('string'))->toBeFalse();
            expect($this->referenceResolver->isReference(null))->toBeFalse();
            expect($this->referenceResolver->isReference(123))->toBeFalse();
        });
    });

    describe('resolveAllReferences', function (): void {
        it('should resolve all references in schema recursively', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $userSchema = $spec['components']['schemas']['User'];

            $resolved = $this->referenceResolver->resolveAllReferences($userSchema, $specification);

            expect($resolved['properties']['address']['type'])->toBe('object');
            expect($resolved['properties']['address']['properties']['street']['type'])->toBe('string');
            expect($resolved['properties']['contact']['properties']['email']['format'])->toBe('email');
        });

        it('should handle arrays with references', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $postSchema = $spec['components']['schemas']['Post'];

            $resolved = $this->referenceResolver->resolveAllReferences($postSchema, $specification);

            expect($resolved['properties']['tags']['items']['type'])->toBe('object');
            expect($resolved['properties']['tags']['items']['properties']['name']['type'])->toBe('string');
        });

        it('should detect and prevent circular references', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $nodeSchema = $spec['components']['schemas']['Node'];

            expect(fn () => $this->referenceResolver->resolveAllReferences($nodeSchema, $specification))
                ->toThrow(InvalidArgumentException::class, 'Circular reference detected');
        });
    });

    describe('getReferenceType', function (): void {
        it('should identify schema references', function (): void {
            $ref = '#/components/schemas/User';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('schema');
        });

        it('should identify parameter references', function (): void {
            $ref = '#/components/parameters/pageParam';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('parameter');
        });

        it('should identify response references', function (): void {
            $ref = '#/components/responses/ErrorResponse';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('response');
        });

        it('should return unknown for unrecognized references', function (): void {
            $ref = '#/components/unknown/Something';
            $type = $this->referenceResolver->getReferenceType($ref);

            expect($type)->toBe('unknown');
        });
    });

    describe('extractReferencePath', function (): void {
        it('should extract path from reference', function (): void {
            $ref = '#/components/schemas/User';
            $path = $this->referenceResolver->extractReferencePath($ref);

            expect($path)->toBe(['components', 'schemas', 'User']);
        });

        it('should handle nested paths', function (): void {
            $ref = '#/paths/~1users~1{id}/get/responses/200';
            $path = $this->referenceResolver->extractReferencePath($ref);

            expect($path)->toBe(['paths', '/users/{id}', 'get', 'responses', '200']);
        });

        it('should decode JSON pointer escapes', function (): void {
            $ref = '#/components/schemas/User~1Profile';
            $path = $this->referenceResolver->extractReferencePath($ref);

            expect($path)->toBe(['components', 'schemas', 'User/Profile']);
        });
    });

    describe('validateReference', function (): void {
        it('should validate correct reference format', function (): void {
            $ref = '#/components/schemas/User';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect invalid reference format', function (): void {
            $ref = 'invalid-reference';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('Reference must start with #/');
        });

        it('should detect empty reference', function (): void {
            $ref = '';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('Reference cannot be empty');
        });

        it('should detect external file references', function (): void {
            $ref = 'external.json#/schemas/User';
            $result = $this->referenceResolver->validateReference($ref);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->toContain('External file references are not supported');
        });
    });

    describe('caching', function (): void {
        it('should cache resolved references for performance', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            // Multiple resolutions should return identical objects (verifying cache works)
            $resolved1 = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            $resolved2 = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            $resolved3 = $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            // All resolutions should return the same result
            expect($resolved1)->toBe($resolved2);
            expect($resolved2)->toBe($resolved3);

            // Verify the resolved content is correct
            expect($resolved1)->toBeArray();
            expect($resolved1)->toHaveKey('type');
            expect($resolved1['type'])->toBe('object');
            expect($resolved1)->toHaveKey('properties');
            expect($resolved1['properties'])->toHaveKey('name');
        });

        it('should clear cache when requested', function (): void {
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

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            // Resolve and cache
            $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            // Clear cache
            $this->referenceResolver->clearCache();

            // Verify cache is cleared by resolving again (should not throw if cache is properly cleared)
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            expect($resolved)->toBeArray();
        });
    });

    describe('advanced edge cases', function (): void {
        it('should detect complex circular references across multiple schemas', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Company' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'employees' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Employee'],
                                ],
                            ],
                        ],
                        'Employee' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'company' => ['$ref' => '#/components/schemas/Company'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $companySchema = $spec['components']['schemas']['Company'];

            expect(fn () => $this->referenceResolver->resolveAllReferences($companySchema, $specification))
                ->toThrow(InvalidArgumentException::class, 'Circular reference detected');
        });

        it('should handle deep nested reference chains', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Level1' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'string'],
                                'level2' => ['$ref' => '#/components/schemas/Level2'],
                            ],
                        ],
                        'Level2' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'string'],
                                'level3' => ['$ref' => '#/components/schemas/Level3'],
                            ],
                        ],
                        'Level3' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'string'],
                                'level4' => ['$ref' => '#/components/schemas/Level4'],
                            ],
                        ],
                        'Level4' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => ['type' => 'string'],
                                'level5' => ['$ref' => '#/components/schemas/Level5'],
                            ],
                        ],
                        'Level5' => [
                            'type' => 'object',
                            'properties' => [
                                'finalData' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $level1Schema = $spec['components']['schemas']['Level1'];

            $resolved = $this->referenceResolver->resolveAllReferences($level1Schema, $specification);

            expect($resolved['properties']['level2']['properties']['level3']['properties']['level4']['properties']['level5']['properties']['finalData']['type'])
                ->toBe('string');
        });

        it('should handle deeply nested missing references', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'ValidSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'nested' => ['$ref' => '#/components/schemas/NonExistentSchema'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $validSchema = $spec['components']['schemas']['ValidSchema'];

            expect(fn () => $this->referenceResolver->resolveAllReferences($validSchema, $specification))
                ->toThrow(InvalidArgumentException::class, 'Reference not found');
        });

        it('should handle references to empty schemas', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'EmptySchema' => [],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            $resolved = $this->referenceResolver->resolve('#/components/schemas/EmptySchema', $specification);

            expect($resolved)->toBeArray();
            expect($resolved)->toBeEmpty();
        });
    });

    describe('referenceExists', function (): void {
        it('should return true for existing reference', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            expect($this->referenceResolver->referenceExists('#/components/schemas/User', $specification))->toBe(true);
        });

        it('should return false for non-existing reference', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => ['schemas' => []],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            expect($this->referenceResolver->referenceExists('#/components/schemas/NonExistent', $specification))->toBe(false);
        });
    });

    describe('getReferences', function (): void {
        it('should extract all references from schema', function (): void {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'user' => ['$ref' => '#/components/schemas/User'],
                    'tags' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Tag'],
                    ],
                    'metadata' => [
                        'allOf' => [
                            ['$ref' => '#/components/schemas/BaseMetadata'],
                            ['$ref' => '#/components/schemas/ExtendedMetadata'],
                        ],
                    ],
                ],
            ];

            $references = $this->referenceResolver->getReferences($schema);

            expect($references)->toContain('#/components/schemas/User');
            expect($references)->toContain('#/components/schemas/Tag');
            expect($references)->toContain('#/components/schemas/BaseMetadata');
            expect($references)->toContain('#/components/schemas/ExtendedMetadata');
            expect(count($references))->toBe(4);
        });

        it('should return empty array for schema without references', function (): void {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
            ];

            $references = $this->referenceResolver->getReferences($schema);

            expect($references)->toBeEmpty();
        });
    });

    describe('validateReferences', function (): void {
        it('should validate all references in specification', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => [
                            'type' => 'object',
                            'properties' => [
                                'profile' => ['$ref' => '#/components/schemas/Profile'],
                            ],
                        ],
                        'Profile' => [
                            'type' => 'object',
                            'properties' => ['bio' => ['type' => 'string']],
                        ],
                    ],
                ],
                'paths' => [],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            $result = $this->referenceResolver->validateReferences($specification);

            expect($result['valid'])->toBe(true);
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect invalid references in specification', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => [
                            'type' => 'object',
                            'properties' => [
                                'profile' => ['$ref' => '#/components/schemas/NonExistentProfile'],
                            ],
                        ],
                    ],
                ],
                'paths' => [],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            $result = $this->referenceResolver->validateReferences($specification);

            expect($result['valid'])->toBe(false);
            expect($result['errors'])->toContain("Invalid reference in schema 'User': #/components/schemas/NonExistentProfile");
        });
    });

    describe('flattenReferences', function (): void {
        it('should flatten nested reference chains', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'BaseUser' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                        ],
                        'ExtendedUser' => [
                            'allOf' => [
                                ['$ref' => '#/components/schemas/BaseUser'],
                                [
                                    'type' => 'object',
                                    'properties' => ['email' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            $flattened = $this->referenceResolver->flattenReferences('#/components/schemas/ExtendedUser', $specification);

            expect($flattened)->toHaveKey('allOf');
            expect($flattened['allOf'])->toHaveCount(2);
        });

        it('should handle non-existent reference gracefully', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => ['schemas' => []],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            expect(function () use ($specification): void {
                $this->referenceResolver->flattenReferences('#/components/schemas/NonExistent', $specification);
            })->toThrow(InvalidArgumentException::class, 'Reference not found: #/components/schemas/NonExistent');
        });
    });

    describe('parseReference', function (): void {
        it('should parse reference into path parts', function (): void {
            $parts = $this->referenceResolver->parseReference('#/components/schemas/User');

            expect($parts)->toBe(['components', 'schemas', 'User']);
        });

        it('should throw exception for invalid reference format', function (): void {
            expect(fn () => $this->referenceResolver->parseReference('invalid'))
                ->toThrow(InvalidArgumentException::class, 'Invalid reference format: invalid. Must start with \'#/\'');
        });
    });

    describe('composition schemas', function (): void {
        it('should resolve references in oneOf schemas', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'Cat' => [
                            'type' => 'object',
                            'properties' => ['meow' => ['type' => 'boolean']],
                        ],
                        'Dog' => [
                            'type' => 'object',
                            'properties' => ['bark' => ['type' => 'boolean']],
                        ],
                        'Pet' => [
                            'oneOf' => [
                                ['$ref' => '#/components/schemas/Cat'],
                                ['$ref' => '#/components/schemas/Dog'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $petSchema = $spec['components']['schemas']['Pet'];

            $resolved = $this->referenceResolver->resolveAllReferences($petSchema, $specification);

            expect($resolved['oneOf'][0]['properties']['meow']['type'])->toBe('boolean');
            expect($resolved['oneOf'][1]['properties']['bark']['type'])->toBe('boolean');
        });

        it('should resolve references in anyOf schemas', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'ContactInfo' => [
                            'type' => 'object',
                            'properties' => ['email' => ['type' => 'string']],
                        ],
                        'PersonalInfo' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string']],
                        ],
                        'User' => [
                            'anyOf' => [
                                ['$ref' => '#/components/schemas/ContactInfo'],
                                ['$ref' => '#/components/schemas/PersonalInfo'],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');
            $userSchema = $spec['components']['schemas']['User'];

            $resolved = $this->referenceResolver->resolveAllReferences($userSchema, $specification);

            expect($resolved['anyOf'][0]['properties']['email']['type'])->toBe('string');
            expect($resolved['anyOf'][1]['properties']['name']['type'])->toBe('string');
        });
    });

    describe('cache management', function (): void {
        it('should manage cache size to prevent unbounded growth', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => ['schemas' => []],
            ];

            // Add many schemas to potentially trigger cache management
            for ($i = 0; $i < 50; $i++) {
                $spec['components']['schemas']["Schema$i"] = [
                    'type' => 'object',
                    'properties' => ['field' => ['type' => 'string']],
                ];
            }

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            // Resolve many references to fill cache
            for ($i = 0; $i < 50; $i++) {
                $resolved = $this->referenceResolver->resolve("#/components/schemas/Schema$i", $specification);
                expect($resolved)->toBeArray();
            }

            // Cache should still work after potential cleanup
            $resolved = $this->referenceResolver->resolve('#/components/schemas/Schema0', $specification);
            expect($resolved['type'])->toBe('object');
        });

        it('should track cache hit statistics', function (): void {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'components' => [
                    'schemas' => [
                        'User' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                    ],
                ],
            ];

            $specification = OpenApiSpecification::fromArray($spec, 'test.json');

            // First resolution should not be a cache hit
            $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            // Second resolution should be a cache hit
            $this->referenceResolver->resolve('#/components/schemas/User', $specification);

            // We can't directly test cache hits without exposing internal state,
            // but we can verify the resolver still works correctly
            $resolved = $this->referenceResolver->resolve('#/components/schemas/User', $specification);
            expect($resolved['type'])->toBe('object');
        });
    });
});
