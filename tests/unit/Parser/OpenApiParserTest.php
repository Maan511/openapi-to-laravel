<?php

beforeEach(function () {
    $this->referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
    $this->schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($this->referenceResolver);
    $this->parser = new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($this->schemaExtractor);
});

describe('OpenApiParser', function () {
    describe('parseFromString', function () {
        it('should parse valid JSON OpenAPI specification', function () {
            $json = json_encode([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/test' => [
                        'post' => [
                            'operationId' => 'testOperation',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $specification = $this->parser->parseFromString($json, 'json');

            expect($specification)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\OpenApiSpecification::class);
            expect($specification->version)->toBe('3.0.0');
            expect($specification->info['title'])->toBe('Test API');
        });

        it('should parse valid YAML OpenAPI specification', function () {
            $yaml = "openapi: '3.0.0'\ninfo:\n  title: 'Test API'\n  version: '1.0.0'\npaths:\n  /test:\n    post:\n      operationId: testOperation\n      requestBody:\n        content:\n          application/json:\n            schema:\n              type: object\n              properties:\n                name:\n                  type: string";

            $specification = $this->parser->parseFromString($yaml, 'yaml');

            expect($specification)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\OpenApiSpecification::class);
            expect($specification->version)->toBe('3.0.0');
            expect($specification->info['title'])->toBe('Test API');
        });

        it('should throw exception for invalid JSON', function () {
            expect(fn () => $this->parser->parseFromString('invalid json', 'json'))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('should throw exception for invalid YAML', function () {
            expect(fn () => $this->parser->parseFromString('invalid: yaml: content: [', 'yaml'))
                ->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('parseFromFile', function () {
        it('should throw exception for non-existent file', function () {
            expect(fn () => $this->parser->parseFromFile('/non/existent/file.json'))
                ->toThrow(\InvalidArgumentException::class, 'OpenAPI specification file not found');
        });

        it('should detect JSON format from extension', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, json_encode([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => [],
            ]));

            $specification = $this->parser->parseFromFile($tempFile);
            expect($specification->version)->toBe('3.0.0');

            unlink($tempFile);
        });

        it('should detect YAML format from extension', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.yaml';
            file_put_contents($tempFile, "openapi: '3.0.0'\ninfo:\n  title: 'Test'\n  version: '1.0.0'\npaths: {}");

            $specification = $this->parser->parseFromFile($tempFile);
            expect($specification->version)->toBe('3.0.0');

            unlink($tempFile);
        });

        it('should handle relative paths correctly', function () {
            // Create a temporary file in the current directory
            $relativePath = 'temp_openapi_test.json';
            file_put_contents($relativePath, json_encode([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => [],
            ]));

            // Test parsing with relative path
            $specification = $this->parser->parseFromFile($relativePath);
            expect($specification->version)->toBe('3.0.0');
            expect($specification->info['title'])->toBe('Test');

            // Clean up
            unlink($relativePath);
        });
    });

    describe('extractEndpoints', function () {
        it('should extract all endpoints from specification', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/users' => [
                        'get' => ['operationId' => 'getUsers'],
                        'post' => [
                            'operationId' => 'createUser',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '/users/{id}' => [
                        'get' => ['operationId' => 'getUser'],
                        'put' => [
                            'operationId' => 'updateUser',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $endpoints = $this->parser->extractEndpoints($specification);

            expect($endpoints)->toHaveCount(4);
            expect($endpoints[0]->path)->toBe('/users');
            expect($endpoints[0]->method)->toBe('GET');
            expect($endpoints[1]->method)->toBe('POST');
        });

        it('should skip non-HTTP methods', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/test' => [
                        'get' => ['operationId' => 'getTest'],
                        'parameters' => [['name' => 'id', 'in' => 'path']],
                        'summary' => 'Test endpoint',
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $endpoints = $this->parser->extractEndpoints($specification);

            expect($endpoints)->toHaveCount(1);
            expect($endpoints[0]->method)->toBe('GET');
        });
    });

    describe('getEndpointsWithRequestBodies', function () {
        it('should filter endpoints that have request bodies', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/users' => [
                        'get' => ['operationId' => 'getUsers'],
                        'post' => [
                            'operationId' => 'createUser',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $endpoints = $this->parser->getEndpointsWithRequestBodies($specification);

            expect($endpoints)->toHaveCount(1);
            expect($endpoints[0]->method)->toBe('POST');
            expect($endpoints[0]->hasRequestBody())->toBeTrue();
        });

        it('should return empty array when no endpoints have request bodies', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/users' => [
                        'get' => ['operationId' => 'getUsers'],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $endpoints = $this->parser->getEndpointsWithRequestBodies($specification);

            expect($endpoints)->toHaveCount(0);
        });
    });

    describe('validateSpecification', function () {
        it('should validate correct specification', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/test' => [
                        'post' => [
                            'operationId' => 'test',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $validation = $this->parser->validateSpecification($specification);

            expect($validation['valid'])->toBeTrue();
            expect($validation['errors'])->toHaveCount(0);
        });

        it('should detect missing info section', function () {
            $specData = [
                'openapi' => '3.0.0',
                'paths' => [],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $validation = $this->parser->validateSpecification($specification);

            expect($validation['valid'])->toBeFalse();
            expect($validation['errors'])->toContain('Missing required info section');
        });

        it('should detect missing paths section', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $validation = $this->parser->validateSpecification($specification);

            expect($validation['valid'])->toBeFalse();
            expect($validation['errors'])->toContain('Missing required paths section');
        });

        it('should warn about unsupported OpenAPI version', function () {
            $specData = [
                'openapi' => '2.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => [],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $validation = $this->parser->validateSpecification($specification);

            expect($validation['warnings'])->toContain('OpenAPI version 2.0.0 may not be fully supported');
        });

        it('should warn when no endpoints have request bodies', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => [
                    '/test' => [
                        'get' => ['operationId' => 'getTest'],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $validation = $this->parser->validateSpecification($specification);

            expect($validation['warnings'])->toContain('No endpoints with request bodies found - no FormRequests will be generated');
        });
    });

    describe('getSpecificationStats', function () {
        it('should generate correct statistics', function () {
            $specData = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/users' => [
                        'get' => ['operationId' => 'getUsers', 'tags' => ['users']],
                        'post' => [
                            'operationId' => 'createUser',
                            'tags' => ['users'],
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '/posts' => [
                        'get' => ['operationId' => 'getPosts', 'tags' => ['posts']],
                    ],
                ],
                'components' => [
                    'schemas' => [
                        'User' => ['type' => 'object'],
                        'Post' => ['type' => 'object'],
                    ],
                ],
            ];

            $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($specData, 'test-spec.json');
            $stats = $this->parser->getSpecificationStats($specification);

            expect($stats['totalEndpoints'])->toBe(3);
            expect($stats['endpointsWithRequestBodies'])->toBe(1);
            expect($stats['httpMethods'])->toContain('GET', 'POST');
            expect($stats['tags'])->toContain('users', 'posts');
            expect($stats['hasComponents'])->toBeTrue();
            expect($stats['schemaCount'])->toBe(2);
        });
    });

    describe('isValidOpenApiFile', function () {
        it('should return true for valid OpenAPI file', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, json_encode([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => [],
            ]));

            expect($this->parser->isValidOpenApiFile($tempFile))->toBeTrue();

            unlink($tempFile);
        });

        it('should return false for invalid OpenAPI file', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test') . '.json';
            file_put_contents($tempFile, 'invalid json content');

            expect($this->parser->isValidOpenApiFile($tempFile))->toBeFalse();

            unlink($tempFile);
        });

        it('should return false for non-existent file', function () {
            expect($this->parser->isValidOpenApiFile('/non/existent/file.json'))->toBeFalse();
        });
    });

    describe('getSupportedExtensions', function () {
        it('should return correct supported extensions', function () {
            $extensions = $this->parser->getSupportedExtensions();

            expect($extensions)->toBe(['json', 'yaml', 'yml']);
        });
    });
});
