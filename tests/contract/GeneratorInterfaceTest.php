<?php

namespace Maan511\OpenapiToLaravel\Tests\Contract;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Models\SchemaObject;
use Maan511\OpenapiToLaravel\Models\ValidationConstraints;
use Maan511\OpenapiToLaravel\Tests\TestCase;
use ReflectionClass;

/**
 * Contract test for Generator interface based on generator-interface.yaml
 *
 * This test validates the generator components match the contract specification.
 * It tests FormRequest generation and validation rule mapping.
 */
class GeneratorInterfaceTest extends TestCase
{
    public function test_form_request_generator_class_exists(): void
    {
        // Try to instantiate the generator class
        $reflection = new ReflectionClass(FormRequestGenerator::class);

        // The generator should exist and be instantiable
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_validation_rule_mapper_class_exists(): void
    {
        // Try to instantiate the mapper class
        $reflection = new ReflectionClass(ValidationRuleMapper::class);

        // The mapper should exist and be instantiable
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_generator_creates_form_request_from_schema(): void
    {
        $generator = new FormRequestGenerator(
            new ValidationRuleMapper
        );

        $schema = $this->getSampleRequestSchema();
        $result = $generator->generateFromSchema(
            $schema,
            'CreateUserRequest',
            'App\\Http\\Requests',
            '/tmp'
        );

        $this->assertInstanceOf(FormRequestClass::class, $result);
        $this->assertEquals('CreateUserRequest', $result->className);
        $this->assertEquals('App\\Http\\Requests', $result->namespace);
        $this->assertArrayHasKey('name', $result->validationRules);
        $this->assertArrayHasKey('email', $result->validationRules);
        $this->assertArrayHasKey('age', $result->validationRules);
    }

    public function test_generator_validates_class_name_format(): void
    {
        $schema = $this->getSampleRequestSchema();

        // Test that invalid class names are rejected during FormRequestClass creation
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name');

        FormRequestClass::create(
            className: 'invalid-class-name',
            namespace: 'App\\Http\\Requests',
            filePath: '/tmp/invalid-class-name.php',
            validationRules: ['name' => 'required|string'],
            sourceSchema: $schema
        );
    }

    public function test_generator_validates_namespace_format(): void
    {
        $schema = $this->getSampleRequestSchema();

        // Test that invalid namespaces are rejected during FormRequestClass creation
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid namespace');

        FormRequestClass::create(
            className: 'TestRequest',
            namespace: 'invalid-namespace',
            filePath: '/tmp/TestRequest.php',
            validationRules: ['name' => 'required|string'],
            sourceSchema: $schema
        );
    }

    public function test_generator_produces_valid_php_code(): void
    {
        $generator = new FormRequestGenerator(
            new ValidationRuleMapper
        );

        $schema = $this->getSampleRequestSchema();
        $formRequest = $generator->generateFromSchema(
            $schema,
            'CreateUserRequest',
            'App\\Http\\Requests',
            '/tmp'
        );

        // Verify the FormRequest structure
        $this->assertEquals('CreateUserRequest', $formRequest->className);
        $this->assertEquals('App\\Http\\Requests', $formRequest->namespace);
        $this->assertStringEndsWith('/CreateUserRequest.php', $formRequest->filePath);
        $this->assertArrayHasKey('name', $formRequest->validationRules);
        $this->assertArrayHasKey('email', $formRequest->validationRules);

        // Verify PHP code generation
        $phpCode = $formRequest->generatePhpCode();
        $this->assertStringContainsString('<?php', $phpCode);
        $this->assertStringContainsString('namespace App\\Http\\Requests;', $phpCode);
        $this->assertStringContainsString('class CreateUserRequest extends FormRequest', $phpCode);
        $this->assertStringContainsString('public function rules(): array', $phpCode);
        $this->assertStringContainsString('public function authorize(): bool', $phpCode);

        // Verify rules are present in generated code
        $this->assertStringContainsString("'name'", $phpCode);
        $this->assertStringContainsString("'email'", $phpCode);
    }

    public function test_generator_includes_generation_options(): void
    {
        $generator = new FormRequestGenerator(
            new ValidationRuleMapper
        );

        $schema = $this->getSampleRequestSchema();
        $options = [
            'includeAuthorization' => true,
            'authorizationMethod' => 'return false;',
            'customMessages' => ['name.required' => 'Name is required'],
            'customAttributes' => ['name' => 'Full Name'],
        ];

        $formRequest = $generator->generateFromSchema(
            $schema,
            'TestRequest',
            'App\\Http\\Requests',
            '/tmp',
            $options
        );

        $this->assertEquals($options, $formRequest->options);
        $this->assertEquals('return false;', $formRequest->authorizationMethod);
        $this->assertEquals(['name.required' => 'Name is required'], $formRequest->customMessages);
        $this->assertEquals(['name' => 'Full Name'], $formRequest->customAttributes);
    }

    /**
     * Helper method to get a sample request schema for testing
     */
    private function getSampleRequestSchema(): SchemaObject
    {
        return new SchemaObject(
            type: 'object',
            properties: [
                'name' => new SchemaObject(
                    type: 'string',
                    validation: new ValidationConstraints(
                        minLength: 2,
                        maxLength: 100
                    )
                ),
                'email' => new SchemaObject(
                    type: 'string',
                    format: 'email'
                ),
                'age' => new SchemaObject(
                    type: 'integer',
                    validation: new ValidationConstraints(
                        minimum: 0,
                        maximum: 120
                    )
                ),
            ],
            required: ['name', 'email']
        );
    }
}
