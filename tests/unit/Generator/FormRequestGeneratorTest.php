<?php

use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Models\SchemaObject;

beforeEach(function (): void {
    $this->ruleMapper = new ValidationRuleMapper;
    $this->templateEngine = new TemplateEngine;
    $this->generator = new FormRequestGenerator($this->ruleMapper);
});

describe('FormRequestGenerator', function (): void {
    describe('generateFromEndpoint', function (): void {
        it('should generate FormRequest from endpoint with request body', function (): void {
            $requestSchema = new SchemaObject(
                type: 'object',
                properties: [
                    'name' => new SchemaObject(type: 'string'),
                    'email' => new SchemaObject(type: 'string', format: 'email'),
                ],
                required: ['name', 'email']
            );

            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                requestSchema: $requestSchema,
                summary: 'Create a new user'
            );

            $formRequest = $this->generator->generateFromEndpoint(
                $endpoint,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequest)->toBeInstanceOf(FormRequestClass::class);
            expect($formRequest->className)->toBe('CreateUserRequest');
            expect($formRequest->namespace)->toBe('App\\Http\\Requests');
            expect($formRequest->filePath)->toBe('/app/Http/Requests/CreateUserRequest.php');
            expect($formRequest->validationRules)->toHaveKey('name');
            expect($formRequest->validationRules)->toHaveKey('email');
        });

        it('should throw exception for endpoint without request body', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers',
                summary: 'Get all users'
            );

            expect(fn () => $this->generator->generateFromEndpoint(
                $endpoint,
                'App\\Http\\Requests',
                '/app/Http/Requests'
            ))->toThrow(InvalidArgumentException::class, 'has no request body');
        });

        it('should handle options parameter', function (): void {
            $requestSchema = new SchemaObject(
                type: 'object',
                properties: [
                    'name' => new SchemaObject(type: 'string'),
                ]
            );

            $endpoint = new EndpointDefinition(
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

    describe('generateFromSchema', function (): void {
        it('should generate FormRequest from schema directly', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'title' => new SchemaObject(type: 'string'),
                    'content' => new SchemaObject(type: 'string'),
                ],
                required: ['title']
            );

            $formRequest = $this->generator->generateFromSchema(
                $schema,
                'CreatePostRequest',
                'App\\Http\\Requests',
                '/app/Http/Requests'
            );

            expect($formRequest)->toBeInstanceOf(FormRequestClass::class);
            expect($formRequest->className)->toBe('CreatePostRequest');
            expect($formRequest->namespace)->toBe('App\\Http\\Requests');
            expect($formRequest->validationRules)->toHaveKey('title');
            expect($formRequest->validationRules)->toHaveKey('content');
            expect($formRequest->validationRules['title'])->toContain('required');
            expect($formRequest->validationRules['content'])->toContain('nullable');
        });
    });

    describe('generateFromEndpoints', function (): void {
        it('should generate multiple FormRequests from endpoints', function (): void {
            $schema1 = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $schema2 = new SchemaObject(
                type: 'object',
                properties: ['title' => new SchemaObject(type: 'string')]
            );

            $endpoints = [
                new EndpointDefinition(
                    path: '/users',
                    method: 'POST',
                    operationId: 'createUser',
                    requestSchema: $schema1
                ),
                new EndpointDefinition(
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

        it('should handle naming conflicts', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['data' => new SchemaObject(type: 'string')]
            );

            $endpoints = [
                new EndpointDefinition(
                    path: '/users',
                    method: 'POST',
                    operationId: 'createUser',
                    requestSchema: $schema
                ),
                new EndpointDefinition(
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

        it('should skip endpoints without request bodies', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $endpoints = [
                new EndpointDefinition(
                    path: '/users',
                    method: 'GET',
                    operationId: 'getUsers'
                ),
                new EndpointDefinition(
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

    });

    describe('generateAndWrite', function (): void {
        it('should write FormRequest to file', function (): void {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

        it('should skip existing file without force flag', function (): void {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $filePath = $tempDir . '/ExistingRequest.php';
            file_put_contents($filePath, '<?php // existing content');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

        it('should overwrite existing file with force flag', function (): void {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $filePath = $tempDir . '/ForceRequest.php';
            file_put_contents($filePath, '<?php // old content');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

    describe('generateAndWriteMultiple', function (): void {
        it('should generate multiple FormRequests and provide summary', function (): void {
            $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
            mkdir($tempDir, 0755, true);

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequests = [
                FormRequestClass::create(
                    className: 'FirstRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: $tempDir . '/FirstRequest.php',
                    validationRules: ['name' => 'required|string'],
                    sourceSchema: $schema
                ),
                FormRequestClass::create(
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

    });

    describe('validate', function (): void {
        it('should validate correct FormRequest classes', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequests = [
                FormRequestClass::create(
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

    });

    describe('getStats', function (): void {
        it('should generate correct statistics', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'name' => new SchemaObject(type: 'string'),
                    'email' => new SchemaObject(type: 'string'),
                ]
            );

            $formRequests = [
                FormRequestClass::create(
                    className: 'FirstRequest',
                    namespace: 'App\\Http\\Requests',
                    filePath: '/app/Http/Requests/FirstRequest.php',
                    validationRules: ['name' => 'required|string', 'email' => 'nullable|email'],
                    sourceSchema: $schema
                ),
                FormRequestClass::create(
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

        it('should handle empty FormRequest array', function (): void {
            $stats = $this->generator->getStats([]);

            expect($stats['totalClasses'])->toBe(0);
            expect($stats['totalRules'])->toBe(0);
            expect($stats['averageComplexity'])->toBe(0);
            expect($stats['namespaces'])->toBeEmpty();
            expect($stats['mostComplex'])->toBeNull();
        });
    });

    describe('dryRun', function (): void {
        it('should show what would be generated without creating files', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequests = [
                FormRequestClass::create(
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
