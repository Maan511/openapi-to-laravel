<?php

namespace Maan511\OpenapiToLaravel\Tests\Contract;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
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

        $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\FormRequestClass::class, $result);
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

        \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
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

        \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
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

    public function test_validation_rule_mapper_maps_string_constraints(): void
    {
        $mapper = new ValidationRuleMapper;

        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'string',
            format: 'email',
            validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 255,
                pattern: '^[a-zA-Z0-9]+$',
                enum: ['active', 'inactive']
            )
        );

        $rules = $mapper->mapValidationRules($schema, 'email');

        $this->assertArrayHasKey('email', $rules);
        $rule = $rules['email'];

        // Check that it contains expected rules
        $this->assertStringContainsString('string', $rule);
        $this->assertStringContainsString('email', $rule);
        $this->assertStringContainsString('min:5', $rule);
        $this->assertStringContainsString('max:255', $rule);
        $this->assertStringContainsString('regex:', $rule);
        $this->assertStringContainsString('in:active,inactive', $rule);
    }

    public function test_validation_rule_mapper_maps_numeric_constraints(): void
    {
        $mapper = new ValidationRuleMapper;

        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'integer',
            validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minimum: 0,
                maximum: 100,
                multipleOf: 5
            )
        );

        $rules = $mapper->mapValidationRules($schema, 'score');

        $this->assertArrayHasKey('score', $rules);
        $rule = $rules['score'];

        // Check that it contains expected rules
        $this->assertStringContainsString('integer', $rule);
        $this->assertStringContainsString('min:0', $rule);
        $this->assertStringContainsString('max:100', $rule);
        // Note: multipleOf may need custom validation rule implementation
    }

    public function test_validation_rule_mapper_maps_array_constraints(): void
    {
        $mapper = new ValidationRuleMapper;

        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'array',
            items: new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
            validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minItems: 1,
                maxItems: 10,
                uniqueItems: true
            )
        );

        $rules = $mapper->mapValidationRules($schema, 'tags');

        $this->assertArrayHasKey('tags', $rules);
        $this->assertArrayHasKey('tags.*', $rules);

        $arrayRule = $rules['tags'];
        $itemRule = $rules['tags.*'];

        // Check array constraints
        $this->assertStringContainsString('array', $arrayRule);
        $this->assertStringContainsString('min:1', $arrayRule);
        $this->assertStringContainsString('max:10', $arrayRule);
        // Note: uniqueItems translates to distinct rule which may need special handling

        // Check item type
        $this->assertStringContainsString('string', $itemRule);
    }

    public function test_validation_rule_mapper_handles_nested_objects(): void
    {
        $mapper = new ValidationRuleMapper;

        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: [
                'profile' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'object',
                    properties: [
                        'bio' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                            type: 'string',
                            validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(maxLength: 500)
                        ),
                        'preferences' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                            type: 'object',
                            properties: [
                                'theme' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                                    type: 'string',
                                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                                        enum: ['light', 'dark']
                                    )
                                ),
                            ]
                        ),
                    ],
                    required: ['bio']
                ),
            ],
            required: ['profile']
        );

        $rules = $mapper->mapValidationRules($schema);

        // Should generate dot notation rules
        $this->assertArrayHasKey('profile', $rules);
        $this->assertArrayHasKey('profile.bio', $rules);
        $this->assertArrayHasKey('profile.preferences', $rules);
        $this->assertArrayHasKey('profile.preferences.theme', $rules);

        // Check required/nullable
        $this->assertStringContainsString('required', $rules['profile']);
        $this->assertStringContainsString('required', $rules['profile.bio']);
        $this->assertStringContainsString('nullable', $rules['profile.preferences']);
        $this->assertStringContainsString('nullable', $rules['profile.preferences.theme']);

        // Check constraints
        $this->assertStringContainsString('max:500', $rules['profile.bio']);
        $this->assertStringContainsString('in:light,dark', $rules['profile.preferences.theme']);
    }

    public function test_validation_rule_mapper_handles_required_fields(): void
    {
        $mapper = new ValidationRuleMapper;

        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: [
                'required_field' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                'optional_field' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
            ],
            required: ['required_field']
        );

        $rules = $mapper->mapValidationRules($schema);

        $this->assertArrayHasKey('required_field', $rules);
        $this->assertArrayHasKey('optional_field', $rules);

        // Required field should have 'required' rule
        $this->assertStringContainsString('required', $rules['required_field']);
        $this->assertStringNotContainsString('nullable', $rules['required_field']);

        // Optional field should have 'nullable' rule
        $this->assertStringContainsString('nullable', $rules['optional_field']);
        $this->assertStringNotContainsString('required', $rules['optional_field']);
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

    public function test_generator_handles_complex_validation_constraints(): void
    {
        $mapper = new ValidationRuleMapper;

        // Create a complex schema with multiple constraint types
        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: [
                'username' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'string',
                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                        minLength: 3,
                        maxLength: 20,
                        pattern: '^[a-zA-Z0-9_]+$'
                    )
                ),
                'score' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'integer',
                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                        minimum: 0,
                        maximum: 100
                    )
                ),
                'tags' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'array',
                    items: new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                        minItems: 1,
                        maxItems: 5,
                        uniqueItems: true
                    )
                ),
            ],
            required: ['username', 'score']
        );

        $rules = $mapper->mapValidationRules($schema);

        // Verify complex constraints are handled
        $this->assertArrayHasKey('username', $rules);
        $this->assertArrayHasKey('score', $rules);
        $this->assertArrayHasKey('tags', $rules);
        $this->assertArrayHasKey('tags.*', $rules);

        // Test that multiple constraints are combined properly
        $usernameRule = $rules['username'];
        $this->assertStringContainsString('required', $usernameRule);
        $this->assertStringContainsString('string', $usernameRule);
        $this->assertStringContainsString('min:3', $usernameRule);
        $this->assertStringContainsString('max:20', $usernameRule);
        $this->assertStringContainsString('regex:', $usernameRule);
    }

    public function test_mapper_returns_correct_validation_rule_format(): void
    {
        $mapper = new ValidationRuleMapper;

        $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: [
                'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'string',
                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(maxLength: 255)
                ),
                'email' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'string',
                    format: 'email'
                ),
                'profile' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'object',
                    properties: [
                        'bio' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                            type: 'string',
                            validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(maxLength: 1000)
                        ),
                    ]
                ),
            ],
            required: ['name', 'email']
        );

        $rules = $mapper->mapValidationRules($schema);

        // Check expected format is returned (more specific assertion)
        $this->assertGreaterThan(0, count($rules));
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('profile', $rules);
        $this->assertArrayHasKey('profile.bio', $rules);

        // Verify rule format (should be pipe-separated strings)
        $this->assertIsString($rules['name']);
        $this->assertStringContainsString('required|string|max:255', $rules['name']);

        $this->assertIsString($rules['email']);
        $this->assertStringContainsString('required|string|email', $rules['email']);

        $this->assertIsString($rules['profile.bio']);
        $this->assertStringContainsString('nullable|string|max:1000', $rules['profile.bio']);
    }

    /**
     * Helper method to get a sample request schema for testing
     */
    private function getSampleRequestSchema(): \Maan511\OpenapiToLaravel\Models\SchemaObject
    {
        return new \Maan511\OpenapiToLaravel\Models\SchemaObject(
            type: 'object',
            properties: [
                'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'string',
                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                        minLength: 2,
                        maxLength: 100
                    )
                ),
                'email' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'string',
                    format: 'email'
                ),
                'age' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'integer',
                    validation: new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                        minimum: 0,
                        maximum: 120
                    )
                ),
            ],
            required: ['name', 'email']
        );
    }
}
