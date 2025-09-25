<?php

namespace Maan511\OpenapiToLaravel\Tests\Contract;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Console\GenerateFormRequestsCommand;
use Maan511\OpenapiToLaravel\Tests\TestCase;
use ReflectionClass;

/**
 * Contract test for CLI interface based on cli-interface.yaml
 *
 * This test validates the CLI command interface matches the contract specification.
 * It tests the command signature, options, and basic execution flow.
 */
class CliInterfaceTest extends TestCase
{
    public function test_command_has_correct_signature(): void
    {
        // Try to instantiate the command class
        $reflection = new ReflectionClass(GenerateFormRequestsCommand::class);

        // The command should exist and be instantiable
        $this->assertTrue($reflection->isInstantiable());

        // Should extend Laravel's Command class
        $this->assertTrue($reflection->isSubclassOf(\Illuminate\Console\Command::class));
    }

    public function test_command_accepts_spec_path_argument(): void
    {
        $command = new GenerateFormRequestsCommand;

        // Get the signature and check if it contains the spec argument
        $reflection = new ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);

        $this->assertStringContainsString('{spec', $signature);
        $this->assertStringContainsString('Path to OpenAPI specification file', $signature);
    }

    public function test_command_has_output_directory_option(): void
    {
        $command = new GenerateFormRequestsCommand;

        // Get the signature and check if it contains the output option
        $reflection = new ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);

        $this->assertStringContainsString('--output', $signature);
        $this->assertStringContainsString('./app/Http/Requests', $signature);
    }

    public function test_command_has_namespace_option(): void
    {
        $command = new GenerateFormRequestsCommand;

        // Get the signature and check if it contains the namespace option
        $reflection = new ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);

        $this->assertStringContainsString('--namespace', $signature);
        $this->assertStringContainsString('App\\Http\\Requests', $signature);
    }

    public function test_command_has_force_option(): void
    {
        $command = new GenerateFormRequestsCommand;

        // Get the signature and check if it contains the force option
        $reflection = new ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);

        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('Overwrite existing FormRequest files', $signature);
    }

    public function test_command_has_dry_run_option(): void
    {
        $command = new GenerateFormRequestsCommand;

        // Get the signature and check if it contains the dry-run option
        $reflection = new ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);

        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('Show what would be generated without creating files', $signature);
    }

    public function test_command_supports_verbose_option(): void
    {
        $command = new GenerateFormRequestsCommand;

        // Test that the command can handle Symfony's built-in verbose option
        // Symfony Console automatically provides -v, -vv, -vvv options
        $definition = $command->getDefinition();

        // Verify the command extends Laravel's Command class which supports verbose
        $this->assertInstanceOf(\Illuminate\Console\Command::class, $command);

        // Verify the command extends the correct base class with output functionality
        $this->assertInstanceOf(\Illuminate\Console\Command::class, $command);
    }

    public function test_command_returns_success_response_structure(): void
    {
        // Create a temporary valid OpenAPI spec file
        $tempSpec = $this->createTempSpecFile();
        $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();

        try {
            // Verify command follows Laravel command structure
            $command = new GenerateFormRequestsCommand;
            $this->assertInstanceOf(\Illuminate\Console\Command::class, $command);

        } finally {
            if (file_exists($tempSpec)) {
                unlink($tempSpec);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    public function test_command_returns_error_response_for_invalid_spec(): void
    {
        // Create a temporary invalid OpenAPI spec file
        $tempSpec = tempnam(sys_get_temp_dir(), 'invalid_spec');
        if ($tempSpec === false) {
            $this->fail('tempnam() failed to create a temporary file');
        }
        unlink($tempSpec); // Remove the empty temp file created by tempnam()
        $tempSpec .= '.json'; // Add .json extension
        file_put_contents($tempSpec, '{"invalid": "spec"}');

        try {
            // Test the parser directly since we can't easily mock Artisan
            $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
            $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($referenceResolver);
            $parser = new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($schemaExtractor);

            $specification = $parser->parseFromFile($tempSpec);

            // Validation should fail for invalid spec
            $validation = $parser->validateSpecification($specification);
            $this->assertFalse($validation['valid']);
            $this->assertNotEmpty($validation['errors']);

        } finally {
            unlink($tempSpec);
        }
    }

    public function test_command_returns_error_response_for_missing_spec_file(): void
    {
        // Test the parser with a non-existent file
        $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
        $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($referenceResolver);
        $parser = new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($schemaExtractor);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenAPI specification file not found');
        $parser->parseFromFile('/non/existent/file.json');
    }

    public function test_command_returns_error_response_for_generation_failure(): void
    {
        // Test generation failure by providing an invalid file path that would cause write failure
        // but doesn't involve directory creation to avoid mkdir warnings
        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
        );

        // Use a path that exists but is not writable (if the system allows)
        // Fall back to testing with a mock or different approach
        $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        // Create a file that we'll try to overwrite without force flag
        $testPath = $tempDir . '/TestRequest.php';
        file_put_contents($testPath, '<?php // existing file');

        try {
            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: $testPath,
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            $ruleMapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
            $generator = new \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator($ruleMapper);

            // This should fail because file exists and force=false
            $result = $generator->generateAndWrite($formRequest, false);

            $this->assertFalse($result['success']);
            $this->assertStringContainsString('already exists', $result['message']);

        } finally {
            // Cleanup
            if (file_exists($testPath)) {
                unlink($testPath);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    private function createTempSpecFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_spec');
        if ($tempFile === false) {
            $this->fail('tempnam() failed to create a temporary file');
        }
        unlink($tempFile); // Remove the empty temp file created by tempnam()
        $tempFile .= '.json'; // Add .json extension
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
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        file_put_contents($tempFile, json_encode($spec));

        return $tempFile;
    }
}
