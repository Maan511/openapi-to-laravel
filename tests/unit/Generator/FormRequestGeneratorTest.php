<?php

beforeEach(function () {
    $this->ruleMapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
    $this->templateEngine = new \Maan511\OpenapiToLaravel\Generator\TemplateEngine;
    $this->generator = new \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator($this->ruleMapper, $this->templateEngine);
});

describe('FormRequestGenerator', function () {
    describe('generateFromEndpoint', function () {
        it('should generate FormRequest from endpoint with request body', function () {
            $requestSchema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'email' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string', format: 'email'),
                ],
                required: ['name', 'email']
            );

            $endpoint = new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                summary: 'Create a new user',
                requestSchema: $requestSchema
            );

            $formRequest = $this->generator->generateFromEndpoint(
                $endpoint,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequest)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\FormRequestClass::class);
            expect($formRequest->className)->toBe('CreateUserRequest');
            expect($formRequest->namespace)->toBe('App\\Http\\Requests');
            expect($formRequest->filePath)->toBe('/app/Http/Requests/CreateUserRequest.php');
            expect($formRequest->validationRules)->toHaveKey('name');
            expect($formRequest->validationRules)->toHaveKey('email');
        });

        it('should throw exception for endpoint without request body', function () {
            $endpoint = new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers',
                summary: 'Get all users'
            );

            expect(fn () => $this->generator->generateFromEndpoint(
                $endpoint,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            ))->toThrow(\InvalidArgumentException::class, 'has no request body');
        });

        it('should handle options parameter', function () {
            $requestSchema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                ]
            );

            $endpoint = new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                requestSchema: $requestSchema
            );

            $options = ['template' => 'minimal', 'authorize_return' => 'false'];

            $formRequest = $this->generator->generateFromEndpoint(
                $endpoint,
                'App\\Http\\Requests',
                '/app/Http/Requests',
                $options
            );

            expect($formRequest->options)->toBe($options);
        });
    });

    describe('generateFromSchema', function () {
        it('should generate FormRequest from schema directly', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'title' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'content' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                ],
                required: ['title']
            );

            $formRequest = $this->generator->generateFromSchema(
                $schema,
                'CreatePostRequest',
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequest)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\FormRequestClass::class);
            expect($formRequest->className)->toBe('CreatePostRequest');
            expect($formRequest->namespace)->toBe('App\\Http\\Requests');
            expect($formRequest->validationRules)->toHaveKey('title');
            expect($formRequest->validationRules)->toHaveKey('content');
            expect($formRequest->validationRules['title'])->toContain('required');
            expect($formRequest->validationRules['content'])->toContain('nullable');
        });
    });

    describe('generateFromEndpoints', function () {
        it('should generate multiple FormRequests from endpoints', function () {
            $schema1 = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $schema2 = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['title' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $endpoints = [
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/users',
                    method: 'POST',
                    operationId: 'createUser',
                    requestSchema: $schema1
                ),
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/posts',
                    method: 'POST',
                    operationId: 'createPost',
                    requestSchema: $schema2
                ),
            ];

            $formRequests = $this->generator->generateFromEndpoints(
                $endpoints,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequests)->toHaveCount(2);
            expect($formRequests[0]->className)->toBe('CreateUserRequest');
            expect($formRequests[1]->className)->toBe('CreatePostRequest');
        });

        it('should handle naming conflicts', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['data' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $endpoints = [
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/users',
                    method: 'POST',
                    operationId: 'createUser',
                    requestSchema: $schema
                ),
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/users/{id}',
                    method: 'PUT',
                    operationId: 'createUser',  // Same operationId to create conflict
                    requestSchema: $schema
                ),
            ];

            $formRequests = $this->generator->generateFromEndpoints(
                $endpoints,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequests)->toHaveCount(2);
            expect($formRequests[0]->className)->toBe('CreateUserRequest');
            expect($formRequests[1]->className)->toBe('CreateUserPutRequest');
        });

        it('should skip endpoints without request bodies', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $endpoints = [
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/users',
                    method: 'GET',
                    operationId: 'getUsers'
                ),
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/users',
                    method: 'POST',
                    operationId: 'createUser',
                    requestSchema: $schema
                ),
            ];

            $formRequests = $this->generator->generateFromEndpoints(
                $endpoints,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequests)->toHaveCount(1);
            expect($formRequests[0]->className)->toBe('CreateUserRequest');
        });

        it('should throw exception for invalid endpoint types', function () {
            $endpoints = [
                'not an endpoint',
                new \Maan511\OpenapiToLaravel\Models\EndpointDefinition(
                    path: '/users',
                    method: 'POST',
                    operationId: 'createUser'
                ),
            ];

            expect(fn () => $this->generator->generateFromEndpoints(
                $endpoints,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            ))->toThrow(\InvalidArgumentException::class, 'All items must be EndpointDefinition instances');
        });
    });

    describe('generateAndWrite', function () {
        it('should write FormRequest to file', function () {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: $tempDir . '/TestRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            $result = $this->generator->generateAndWrite($formRequest);

            expect($result['success'])->toBeTrue();
            expect($result['className'])->toBe('TestRequest');
            expect(file_exists($formRequest->filePath))->toBeTrue();

            $content = file_get_contents($formRequest->filePath);
            expect($content)->toContain('class TestRequest extends FormRequest');
            expect($content)->toContain("'name' => 'required|string'");

            // Cleanup
            unlink($formRequest->filePath);
            rmdir($tempDir);
        });

        it('should skip existing file without force flag', function () {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $filePath = $tempDir . '/ExistingRequest.php';
            file_put_contents($filePath, '<?php // existing content');

            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'ExistingRequest',
                namespace: 'App\\Http\\Requests',
                filePath: $filePath,
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            $result = $this->generator->generateAndWrite($formRequest, false);

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('already exists');

            $content = file_get_contents($filePath);
            expect($content)->toBe('<?php // existing content');

            // Cleanup
            unlink($filePath);
            rmdir($tempDir);
        });

        it('should overwrite existing file with force flag', function () {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $filePath = $tempDir . '/ForceRequest.php';
            file_put_contents($filePath, '<?php // old content');

            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'ForceRequest',
                namespace: 'App\\Http\\Requests',
                filePath: $filePath,
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            $result = $this->generator->generateAndWrite($formRequest, true);

            expect($result['success'])->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('class ForceRequest extends FormRequest');

            // Cleanup
            unlink($filePath);
            rmdir($tempDir);
        });
    });

    describe('generateAndWriteMultiple', function () {
        it('should generate multiple FormRequests and provide summary', function () {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequests = [
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'FirstRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: $tempDir . '/FirstRequest.php',
                    validationRules: ['name' => 'required|string'],
                    sourceSchema: $schema
                ),
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'SecondRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: $tempDir . '/SecondRequest.php',
                    validationRules: ['title' => 'required|string'],
                    sourceSchema: $schema
                ),
            ];

            $result = $this->generator->generateAndWriteMultiple($formRequests);

            expect($result['summary']['total'])->toBe(2);
            expect($result['summary']['success'])->toBe(2);
            expect($result['summary']['failed'])->toBe(0);
            expect($result['summary']['skipped'])->toBe(0);
            expect($result['results'])->toHaveCount(2);

            // Cleanup
            unlink($tempDir . '/FirstRequest.php');
            unlink($tempDir . '/SecondRequest.php');
            rmdir($tempDir);
        });

        it('should handle invalid FormRequestClass instances', function () {
            $formRequests = [
                'not a FormRequestClass',
                null,
            ];

            $result = $this->generator->generateAndWriteMultiple($formRequests);

            expect($result['summary']['total'])->toBe(2);
            expect($result['summary']['success'])->toBe(0);
            expect($result['summary']['failed'])->toBe(2);
            expect($result['results'][0]['success'])->toBeFalse();
            expect($result['results'][0]['message'])->toBe('Invalid FormRequestClass instance');
        });
    });

    describe('validate', function () {
        it('should validate correct FormRequest classes', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequests = [
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'ValidRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: '/app/Http/Requests/ValidRequest.php',
                    validationRules: ['name' => 'required|string'],
                    sourceSchema: $schema
                ),
            ];

            $validation = $this->generator->validate($formRequests);

            expect($validation['valid'])->toBeTrue();
            expect($validation['errors'])->toBeEmpty();
        });

        it('should detect invalid class names', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            // Test should check that the creation itself throws the exception
            expect(fn () => \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'invalid-class-name',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/invalid-class-name.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            ))->toThrow(\InvalidArgumentException::class, 'Invalid class name: invalid-class-name');
        });

        it('should detect invalid namespaces', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            // Test should check that the creation itself throws the exception
            expect(fn () => \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'invalid-namespace',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            ))->toThrow(\InvalidArgumentException::class, 'Invalid namespace: invalid-namespace');
        });

        it('should warn about missing validation rules', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            // Test should check that the creation itself throws the exception for empty rules
            expect(fn () => \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'EmptyRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/EmptyRequest.php',
                validationRules: [],
                sourceSchema: $schema
            ))->toThrow(\InvalidArgumentException::class, 'Validation rules cannot be empty');
        });
    });

    describe('getStats', function () {
        it('should generate correct statistics', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'email' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                ]
            );

            $formRequests = [
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'FirstRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: '/app/Http/Requests/FirstRequest.php',
                    validationRules: ['name' => 'required|string', 'email' => 'nullable|email'],
                    sourceSchema: $schema
                ),
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'SecondRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: '/app/Http/Requests/SecondRequest.php',
                    validationRules: ['title' => 'required|string'],
                    sourceSchema: $schema
                ),
            ];

            $stats = $this->generator->getStats($formRequests);

            expect($stats['totalClasses'])->toBe(2);
            expect($stats['totalRules'])->toBe(3);
            expect($stats['averageComplexity'])->toBeGreaterThan(0);
            expect($stats['namespaces'])->toContain('App\\Http\\Requests');
            expect($stats['mostComplex'])->toHaveKey('className');
            expect($stats['mostComplex'])->toHaveKey('complexity');
        });

        it('should handle empty FormRequest array', function () {
            $stats = $this->generator->getStats([]);

            expect($stats['totalClasses'])->toBe(0);
            expect($stats['totalRules'])->toBe(0);
            expect($stats['averageComplexity'])->toBe(0);
            expect($stats['namespaces'])->toBeEmpty();
            expect($stats['mostComplex'])->toBeNull();
        });
    });

    describe('dryRun', function () {
        it('should show what would be generated without creating files', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequests = [
                \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                    className: 'DryRunRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: '/app/Http/Requests/DryRunRequest.php',
                    validationRules: ['name' => 'required|string'],
                    sourceSchema: $schema
                ),
            ];

            $results = $this->generator->dryRun($formRequests);

            expect($results)->toHaveCount(1);
            expect($results[0])->toHaveKey('className');
            expect($results[0])->toHaveKey('filePath');
            expect($results[0])->toHaveKey('rulesCount');
            expect($results[0])->toHaveKey('fileExists');
            expect($results[0])->toHaveKey('estimatedSize');
            expect($results[0]['className'])->toBe('DryRunRequest');
            expect($results[0]['fileExists'])->toBeFalse();
        });
    });
});
