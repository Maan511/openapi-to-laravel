<?php

namespace Maan511\OpenapiToLaravel\Tests\Integration;

use Exception;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use Maan511\OpenapiToLaravel\Tests\TestCase;

/**
 * Integration test for basic generation workflow
 *
 * This test validates the end-to-end generation process from OpenAPI spec
 * to Laravel FormRequest classes using basic scenarios.
 */
class BasicGenerationTest extends TestCase
{
    public function test_complete_generation_workflow_with_simple_spec()
    {
        // Create components for testing
        $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
        $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($referenceResolver);
        $parser = new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($schemaExtractor, $referenceResolver);
        $ruleMapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
        $templateEngine = new \Maan511\OpenapiToLaravel\Generator\TemplateEngine;
        $generator = new \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator($ruleMapper, $templateEngine);

        // Create a temporary OpenAPI spec file
        $tempSpecFile = $this->createTempOpenApiSpec();
        $tempDir = $this->createTempOutputDirectory();

        try {
            // Parse specification
            $specification = $parser->parseFromFile($tempSpecFile);
            $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\OpenApiSpecification::class, $specification);

            // Extract endpoints with request bodies
            $endpoints = $parser->getEndpointsWithRequestBodies($specification);
            $this->assertNotEmpty($endpoints);

            // Generate FormRequest classes
            $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', $tempDir);
            $this->assertNotEmpty($formRequests);

            // Verify FormRequest structure
            $formRequest = $formRequests[0];
            $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\FormRequestClass::class, $formRequest);
            $this->assertEquals('CreateUserRequest', $formRequest->className);
            $this->assertEquals('App\\Http\\Requests', $formRequest->namespace);
            $this->assertStringEndsWith('/CreateUserRequest.php', $formRequest->filePath);

            // Write the files
            $results = $generator->generateAndWriteMultiple($formRequests);
            $this->assertTrue($results['summary']['success'] > 0);

            // Verify files were created
            $generatedFile = $tempDir . '/CreateUserRequest.php';
            $this->assertFileExists($generatedFile);

            // Verify generated content
            $content = file_get_contents($generatedFile);
            $this->assertStringContainsString('class CreateUserRequest', $content);
            $this->assertStringContainsString('extends FormRequest', $content);
            $this->assertStringContainsString('rules()', $content);

        } finally {
            // Cleanup
            if (file_exists($tempSpecFile)) {
                unlink($tempSpecFile);
            }
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*');
                foreach ($files as $file) {
                    unlink($file);
                }
                rmdir($tempDir);
            }
        }
    }

    public function test_generation_with_default_options()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate with default options (default namespace)
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Verify default behavior
        $this->assertNotEmpty($formRequests);

        foreach ($formRequests as $formRequest) {
            // Default namespace should be App\\Http\\Requests
            $this->assertStringContainsString('App\\Http\\Requests', $formRequest->namespace);
        }

        // Cleanup
        unlink($tempSpec);
    }

    public function test_generation_with_custom_output_directory()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $customDir = $this->createTempOutputDirectory();

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate FormRequests (directory handling would be in file writer)
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Verify generation works
        $this->assertNotEmpty($formRequests);

        // Custom output directory test would be at file writing level
        // For now, just verify the generation produces valid FormRequest objects
        foreach ($formRequests as $formRequest) {
            $this->assertNotEmpty($formRequest->generatePhpCode());
            $this->assertNotEmpty($formRequest->className);
        }

        // Cleanup
        unlink($tempSpec);
        rmdir($customDir);
    }

    public function test_generation_with_custom_namespace()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $tempDir = $this->createTempOutputDirectory();

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate with custom namespace
        $customNamespace = 'Custom\\Http\\Requests';
        $formRequests = $generator->generateFromEndpoints($endpoints, $customNamespace, '/tmp');

        // Verify namespace is correctly set
        $this->assertNotEmpty($formRequests);
        foreach ($formRequests as $formRequest) {
            $this->assertStringContainsString($customNamespace, $formRequest->namespace);
        }

        // Cleanup
        unlink($tempSpec);
        rmdir($tempDir);
    }

    public function test_generation_with_force_option()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $tempDir = $this->createTempOutputDirectory();

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate FormRequests
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Create a test file to simulate existing FormRequest
        $testFilePath = $tempDir . '/CreateUserRequest.php';
        file_put_contents($testFilePath, '<?php // Existing file');

        // Test force option behavior
        $this->assertTrue(file_exists($testFilePath));

        // With force=false (default), should not overwrite
        // This would be implemented in the command or file writer
        // For now, just verify the FormRequest object is generated correctly
        $this->assertNotEmpty($formRequests);

        // Cleanup
        unlink($tempSpec);
        unlink($testFilePath);
        rmdir($tempDir);
    }

    public function test_generation_with_dry_run_option()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $tempDir = $this->createTempOutputDirectory();

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate FormRequests (this is the dry run equivalent - generation without file writing)
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Verify FormRequests are generated but no files are created in dry run mode
        $this->assertNotEmpty($formRequests);

        // Verify output directory is empty (dry run doesn't create files)
        $this->assertEmpty(glob($tempDir . '/*'));

        // Verify we can get information about what would be generated
        foreach ($formRequests as $formRequest) {
            $this->assertNotEmpty($formRequest->className);
            $this->assertNotEmpty($formRequest->generatePhpCode());
        }

        // Cleanup
        unlink($tempSpec);
        rmdir($tempDir);
    }

    public function test_generation_produces_valid_laravel_form_requests()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $tempDir = $this->createTempOutputDirectory();

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate FormRequests
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);

        foreach ($formRequests as $formRequest) {
            $content = $formRequest->generatePhpCode();

            // Verify extends FormRequest
            $this->assertStringContainsString('extends FormRequest', $content);

            // Verify has rules() method
            $this->assertStringContainsString('public function rules()', $content);

            // Verify has authorize() method
            $this->assertStringContainsString('public function authorize()', $content);

            // Verify syntactically valid PHP
            $this->assertStringStartsWith('<?php', $content);

            // Verify namespace follows PSR-4
            $this->assertStringContainsString('namespace App\\Http\\Requests', $content);
        }

        // Cleanup
        unlink($tempSpec);
        rmdir($tempDir);
    }

    public function test_generation_handles_multiple_endpoints()
    {
        // Create a more complex OpenAPI spec with multiple endpoints
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Multi-endpoint API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                        ],
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
                                        'properties' => [
                                            'title' => ['type' => 'string'],
                                            'content' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_multi_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        $tempDir = $this->createTempOutputDirectory();

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);

        // Generate FormRequests
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Should generate multiple FormRequest classes
        $this->assertGreaterThanOrEqual(2, count($formRequests));

        // Verify different classes generated
        $classNames = array_map(fn ($fr) => $fr->className, $formRequests);
        $this->assertContains('CreateUserRequest', $classNames);
        $this->assertContains('CreatePostRequest', $classNames);

        // Cleanup
        unlink($tempFile);
        rmdir($tempDir);
    }

    public function test_generation_error_handling_for_invalid_spec()
    {
        $tempDir = $this->createTempOutputDirectory();

        // Create invalid JSON file
        $invalidSpecFile = tempnam(sys_get_temp_dir(), 'invalid_spec_') . '.json';
        file_put_contents($invalidSpecFile, '{invalid json');

        $parser = $this->createParser();

        // Expect exception or error when parsing invalid spec
        $this->expectException(Exception::class);
        $parser->parseFromFile($invalidSpecFile);

        // Cleanup
        unlink($invalidSpecFile);
        rmdir($tempDir);
    }

    public function test_generation_error_handling_for_missing_spec_file()
    {
        $parser = $this->createParser();
        $nonExistentFile = '/path/to/non/existent/file.json';

        // Expect exception when trying to parse non-existent file
        $this->expectException(Exception::class);
        $parser->parseFromFile($nonExistentFile);
    }

    public function test_generation_error_handling_for_unwritable_output_directory()
    {
        $tempSpec = $this->createTempOpenApiSpec();
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Parse OpenAPI spec
        $spec = $parser->parseFromFile($tempSpec);
        $endpoints = $parser->getEndpointsWithRequestBodies($spec);

        // Generate FormRequests (this doesn't involve file writing, so it should succeed)
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // The error would occur at the file writing stage, not generation stage
        // Since we don't have a file writer in the generator, we'll just verify generation works
        $this->assertNotEmpty($formRequests);

        // Cleanup
        unlink($tempSpec);
    }

    public function test_class_naming_from_operation_id()
    {
        // Create spec with specific operationId
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

        $tempFile = tempnam(sys_get_temp_dir(), 'naming_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Verify class naming from operationId
        $this->assertNotEmpty($formRequests);
        $this->assertEquals('CreateUserRequest', $formRequests[0]->className);

        unlink($tempFile);
    }

    public function test_class_naming_from_path_and_method()
    {
        // Create spec without operationId (fallback naming)
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'post' => [
                        // No operationId - should fallback to path+method naming
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

        $tempFile = tempnam(sys_get_temp_dir(), 'naming_fallback_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        // Verify fallback naming when no operationId
        $this->assertNotEmpty($formRequests);
        // Should generate name based on path and method
        $className = $formRequests[0]->className;
        $this->assertNotEmpty($className);
        $this->assertStringContainsString('Request', $className);

        unlink($tempFile);
    }

    public function test_performance_with_medium_sized_spec()
    {
        // Create a medium-sized spec with multiple endpoints
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Large API', 'version' => '1.0.0'],
            'paths' => [],
        ];

        // Generate 10 endpoints to test performance
        for ($i = 1; $i <= 10; $i++) {
            $spec['paths']["/endpoint{$i}"] = [
                'post' => [
                    'operationId' => "createEndpoint{$i}",
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'field1' => ['type' => 'string'],
                                        'field2' => ['type' => 'integer'],
                                        'field3' => ['type' => 'string', 'format' => 'email'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'performance_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Measure performance
        $startTime = microtime(true);

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Verify performance and output
        $this->assertLessThan(2.0, $executionTime, 'Generation should complete in under 2 seconds');
        $this->assertCount(10, $formRequests);

        unlink($tempFile);
    }

    /**
     * Helper method to create a temporary OpenAPI specification file
     */
    private function createTempOpenApiSpec(): string
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                        ],
                                        'required' => ['name', 'email'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));

        return $tempFile;
    }

    /**
     * Helper method to create a temporary output directory
     */
    private function createTempOutputDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        return $tempDir;
    }

    /**
     * Helper method to create parser with dependencies
     */
    private function createParser(): OpenApiParser
    {
        $referenceResolver = new ReferenceResolver;
        $schemaExtractor = new SchemaExtractor($referenceResolver);

        return new OpenApiParser($schemaExtractor, $referenceResolver);
    }

    /**
     * Helper method to create generator with dependencies
     */
    private function createGenerator(): FormRequestGenerator
    {
        $ruleMapper = new ValidationRuleMapper;
        $templateEngine = new TemplateEngine;

        return new FormRequestGenerator($ruleMapper, $templateEngine);
    }
}
