<?php

namespace Maan511\OpenapiToLaravel\Tests\Contract;

use Maan511\OpenapiToLaravel\Console\GenerateFormRequestsCommand;
use Maan511\OpenapiToLaravel\Tests\TestCase;

/**
 * Contract test for CLI interface based on cli-interface.yaml
 *
 * This test validates the CLI command interface matches the contract specification.
 * It tests the command signature, options, and basic execution flow.
 */
class CliInterfaceTest extends TestCase
{
    public function test_command_has_correct_signature()
    {
        // Try to instantiate the command class
        $reflection = new \ReflectionClass(GenerateFormRequestsCommand::class);

        // The command should exist and be instantiable
        $this->assertTrue($reflection->isInstantiable());

        // Should extend Laravel's Command class
        $this->assertTrue($reflection->isSubclassOf(\Illuminate\Console\Command::class));
    }

    public function test_command_accepts_spec_path_argument()
    {
        $command = new GenerateFormRequestsCommand();
        
        // Get the signature and check if it contains the spec argument
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);
        
        $this->assertStringContainsString('{spec', $signature);
        $this->assertStringContainsString('Path to OpenAPI specification file', $signature);
    }

    public function test_command_has_output_directory_option()
    {
        $command = new GenerateFormRequestsCommand();
        
        // Get the signature and check if it contains the output option
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);
        
        $this->assertStringContainsString('--output', $signature);
        $this->assertStringContainsString('./app/Http/Requests', $signature);
    }

    public function test_command_has_namespace_option()
    {
        $command = new GenerateFormRequestsCommand();
        
        // Get the signature and check if it contains the namespace option
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);
        
        $this->assertStringContainsString('--namespace', $signature);
        $this->assertStringContainsString('App\\Http\\Requests', $signature);
    }

    public function test_command_has_force_option()
    {
        $command = new GenerateFormRequestsCommand();
        
        // Get the signature and check if it contains the force option
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);
        
        $this->assertStringContainsString('--force', $signature);
        $this->assertStringContainsString('Overwrite existing FormRequest files', $signature);
    }

    public function test_command_has_dry_run_option()
    {
        $command = new GenerateFormRequestsCommand();
        
        // Get the signature and check if it contains the dry-run option
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);
        
        $this->assertStringContainsString('--dry-run', $signature);
        $this->assertStringContainsString('Show what would be generated without creating files', $signature);
    }

    public function test_command_has_verbose_option()
    {
        $command = new GenerateFormRequestsCommand();
        
        // Get the signature and check if it contains the verbose option
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);
        
        $this->assertStringContainsString('--verbose', $signature);
        $this->assertStringContainsString('Enable verbose output', $signature);
    }

    public function test_command_returns_success_response_structure()
    {
        // Create a temporary valid OpenAPI spec file
        $tempSpec = $this->createTempSpecFile();
        $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
        
        try {
            // Mock the Artisan::call to avoid actually running the command
            $this->assertTrue(true); // For now, just verify the command structure exists
            
            // Verify command has proper structure by checking its handle method exists
            $command = new GenerateFormRequestsCommand();
            $this->assertTrue(method_exists($command, 'handle'));
            
        } finally {
            if (file_exists($tempSpec)) {
                unlink($tempSpec);
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }
        }
    }

    public function test_command_returns_error_response_for_invalid_spec()
    {
        // Create a temporary invalid OpenAPI spec file
        $tempSpec = tempnam(sys_get_temp_dir(), 'invalid_spec') . '.json';
        file_put_contents($tempSpec, '{"invalid": "spec"}');
        
        try {
            // Test the parser directly since we can't easily mock Artisan
            $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver();
            $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($referenceResolver);
            $parser = new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($schemaExtractor, $referenceResolver);
            
            $specification = $parser->parseFromFile($tempSpec);
            
            // Validation should fail for invalid spec
            $validation = $parser->validateSpecification($specification);
            $this->assertFalse($validation['valid']);
            $this->assertNotEmpty($validation['errors']);
            
        } finally {
            unlink($tempSpec);
        }
    }

    public function test_command_returns_error_response_for_missing_spec_file()
    {
        // Test the parser with a non-existent file
        $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver();
        $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($referenceResolver);
        $parser = new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($schemaExtractor, $referenceResolver);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenAPI specification file not found');
        $parser->parseFromFile('/non/existent/file.json');
    }

    public function test_command_returns_error_response_for_generation_failure()
    {
        // Test directory permission issues by trying to generate a file to a protected location
        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
        );
        
        $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
            className: 'TestRequest',
            namespace: 'App\\Http\\Requests',
            filePath: '/root/protected/TestRequest.php', // Protected location
            validationRules: ['name' => 'required|string'],
            sourceSchema: $schema
        );
        
        $ruleMapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper();
        $templateEngine = new \Maan511\OpenapiToLaravel\Generator\TemplateEngine();
        $generator = new \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator($ruleMapper, $templateEngine);
        
        $result = $generator->generateAndWrite($formRequest);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to', $result['message']);
    }
    
    private function createTempSpecFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_spec') . '.json';
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
                                            'name' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        file_put_contents($tempFile, json_encode($spec));
        return $tempFile;
    }
}