<?php

use Exception;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;

beforeEach(function () {
    $this->parser = createTestParser();
    $this->generator = createTestGenerator();
});

describe('Basic Generation Workflow', function () {
    it('should handle complete end-to-end generation workflow', function () {
        $tempSpecFile = createTempOpenApiSpec();
        $tempDir = createTempOutputDirectory();

        try {
            // Parse specification
            $specification = $this->parser->parseFromFile($tempSpecFile);
            expect($specification)->toBeInstanceOf(OpenApiSpecification::class);

            // Extract endpoints with request bodies
            $endpoints = $this->parser->getEndpointsWithRequestBodies($specification);
            expect($endpoints)->not->toBeEmpty();

            // Generate FormRequest classes
            $formRequests = $this->generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', $tempDir);
            expect($formRequests)->not->toBeEmpty();

            // Verify FormRequest structure
            $formRequest = $formRequests[0];
            expect($formRequest)->toBeInstanceOf(FormRequestClass::class);
            expect($formRequest->className)->toBe('CreateUserRequest');
            expect($formRequest->namespace)->toBe('App\\Http\\Requests');
            expect($formRequest->filePath)->toEndWith('/CreateUserRequest.php');

            // Write the files
            $results = $this->generator->generateAndWriteMultiple($formRequests);
            expect($results['summary']['success'])->toBeGreaterThan(0);

            // Verify files were created
            $generatedFile = $tempDir . '/CreateUserRequest.php';
            expect($generatedFile)->toBeFile();

            // Verify generated content
            $content = file_get_contents($generatedFile) ?: '';
            expect($content)->toContain('class CreateUserRequest');
            expect($content)->toContain('extends FormRequest');
            expect($content)->toContain('rules()');
        } finally {
            // Cleanup
            if (file_exists($tempSpecFile)) {
                unlink($tempSpecFile);
            }
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*') ?: [];
                foreach ($files as $file) {
                    unlink($file);
                }
                rmdir($tempDir);
            }
        }
    });

    it('should generate with custom namespace and valid Laravel structure', function () {
        $tempSpec = createTempOpenApiSpec();

        try {
            $spec = $this->parser->parseFromFile($tempSpec);
            $endpoints = $this->parser->getEndpointsWithRequestBodies($spec);

            // Test custom namespace
            $customNamespace = 'Custom\\Http\\Requests';
            $customFormRequests = $this->generator->generateFromEndpoints($endpoints, $customNamespace, '/tmp');

            expect($customFormRequests)->not->toBeEmpty();
            foreach ($customFormRequests as $formRequest) {
                expect($formRequest->namespace)->toContain($customNamespace);
            }

            // Test standard Laravel FormRequest structure
            $standardFormRequests = $this->generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            expect($standardFormRequests)->not->toBeEmpty();
            foreach ($standardFormRequests as $formRequest) {
                $content = $formRequest->generatePhpCode();

                expect($content)->toContain('extends FormRequest');
                expect($content)->toContain('public function rules()');
                expect($content)->toContain('public function authorize()');
                expect($content)->toStartWith('<?php');
                expect($content)->toContain('namespace App\\Http\\Requests');
            }
        } finally {
            unlink($tempSpec);
        }
    });

    it('should handle multiple endpoints', function () {
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Multi-endpoint API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => ['name' => ['type' => 'string']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/posts' => [
                    'post' => [
                        'operationId' => 'createPost',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => ['title' => ['type' => 'string']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_multi_');
        unlink($tempFile); // Remove the empty temp file created by tempnam()
        $tempFile .= '.json'; // Add .json extension
        file_put_contents($tempFile, json_encode($spec));

        $parsedSpec = $this->parser->parseFromFile($tempFile);
        $endpoints = $this->parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $this->generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        expect($formRequests)->toHaveCount(2);

        $classNames = array_map(fn ($fr) => $fr->className, $formRequests);
        expect($classNames)->toContain('CreateUserRequest');
        expect($classNames)->toContain('CreatePostRequest');

        unlink($tempFile);
    });

    describe('error handling', function () {
        it('should handle invalid spec files', function () {
            $invalidSpecFile = tempnam(sys_get_temp_dir(), 'invalid_spec_');
            unlink($invalidSpecFile); // Remove the empty temp file created by tempnam()
            $invalidSpecFile .= '.json'; // Add .json extension
            file_put_contents($invalidSpecFile, '{invalid json');

            expect(fn () => $this->parser->parseFromFile($invalidSpecFile))
                ->toThrow(Exception::class);

            unlink($invalidSpecFile);
        });

        it('should handle missing spec files', function () {
            $nonExistentFile = '/path/to/non/existent/file.json';

            expect(fn () => $this->parser->parseFromFile($nonExistentFile))
                ->toThrow(Exception::class);
        });
    });

    describe('class naming', function () {
        it('should use operationId for class naming', function () {
            $spec = [
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test API', 'version' => '1.0.0'],
                'paths' => [
                    '/users' => [
                        'post' => [
                            'operationId' => 'createUser',
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => ['name' => ['type' => 'string']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            $tempFile = tempnam(sys_get_temp_dir(), 'naming_test_');
            unlink($tempFile); // Remove the empty temp file created by tempnam()
            $tempFile .= '.json'; // Add .json extension
            file_put_contents($tempFile, json_encode($spec));

            $parsedSpec = $this->parser->parseFromFile($tempFile);
            $endpoints = $this->parser->getEndpointsWithRequestBodies($parsedSpec);
            $formRequests = $this->generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

            expect($formRequests)->not->toBeEmpty();
            expect($formRequests[0]->className)->toBe('CreateUserRequest');

            unlink($tempFile);
        });
    });
});
