<?php

namespace Maan511\OpenapiToLaravel\Tests\Contract;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Tests\TestCase;
use ReflectionClass;

/**
 * Contract test for Parser interface based on parser-interface.yaml
 *
 * This test validates the parser components match the contract specification.
 * It tests OpenAPI specification parsing and endpoint schema extraction.
 */
class ParserInterfaceTest extends TestCase
{
    public function test_parser_class_exists(): void
    {
        // Try to instantiate the parser class
        $reflection = new ReflectionClass(OpenApiParser::class);

        // The parser should exist and be instantiable
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_parser_can_parse_json_specification(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $spec = json_encode($this->getSampleOpenApiSpec()) ?: '{}';
        $result = $parser->parseFromString($spec, 'json');

        $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\OpenApiSpecification::class, $result);
        $this->assertEquals('3.0.0', $result->version);
        $this->assertEquals('Test API', $result->info['title']);
        $this->assertNotEmpty($result->paths);
    }

    public function test_parser_can_parse_yaml_specification(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $specArray = $this->getSampleOpenApiSpec();
        // Use simple YAML string instead of yaml_emit which might not be available
        $spec = "openapi: '3.0.0'\ninfo:\n  title: 'Test API'\n  version: '1.0.0'\n  description: 'A test API specification'\npaths:\n  /users:\n    post:\n      operationId: createUser\n      summary: 'Create a new user'\n      tags: [users]\n      requestBody:\n        content:\n          application/json:\n            schema:\n              type: object\n              properties:\n                name:\n                  type: string\n                email:\n                  type: string\n                  format: email\n              required: [name, email]\ncomponents:\n  schemas:\n    User:\n      type: object\n      properties:\n        id:\n          type: integer\n        name:\n          type: string\n        email:\n          type: string\n          format: email";
        $result = $parser->parseFromString($spec, 'yaml');

        $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\OpenApiSpecification::class, $result);
        $this->assertEquals('3.0.0', $result->version);
        $this->assertEquals('Test API', $result->info['title']);
        $this->assertNotEmpty($result->paths);
    }

    public function test_parser_validates_openapi_version(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $spec = json_encode(['openapi' => '2.0.0', 'info' => ['title' => 'Test', 'version' => '1.0'], 'paths' => []]) ?: '{}';
        $specification = $parser->parseFromString($spec, 'json');

        // Validation should warn about unsupported version
        $validation = $parser->validateSpecification($specification);
        $this->assertNotEmpty($validation['warnings']);
        $this->assertStringContainsString('2.0.0', $validation['warnings'][0]);
    }

    public function test_parser_extracts_specification_metadata(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $spec = json_encode($this->getSampleOpenApiSpec()) ?: '{}';
        $specification = $parser->parseFromString($spec, 'json');

        // Verify metadata extraction
        $this->assertEquals('3.0.0', $specification->version);
        $this->assertEquals('Test API', $specification->info['title']);
        $this->assertEquals('1.0.0', $specification->info['version']);
        $this->assertEquals('A test API specification', $specification->info['description']);
        $this->assertNotEmpty($specification->paths);
        $this->assertNotEmpty($specification->components);
    }

    public function test_parser_extracts_endpoint_definitions(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $spec = json_encode($this->getSampleOpenApiSpec()) ?: '{}';
        $specification = $parser->parseFromString($spec, 'json');
        $endpoints = $parser->extractEndpoints($specification);

        $this->assertNotEmpty($endpoints);
        $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\EndpointDefinition::class, $endpoints[0]);

        $endpoint = $endpoints[0];
        $this->assertEquals('/users', $endpoint->path);
        $this->assertEquals('POST', $endpoint->method);
        $this->assertEquals('createUser', $endpoint->operationId);
        $this->assertEquals('Create a new user', $endpoint->summary);
        $this->assertEquals(['users'], $endpoint->tags);
        $this->assertTrue($endpoint->hasRequestBody());
    }

    public function test_parser_extracts_request_schemas(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $spec = json_encode($this->getSampleOpenApiSpec()) ?: '{}';
        $specification = $parser->parseFromString($spec, 'json');
        $endpoints = $parser->getEndpointsWithRequestBodies($specification);

        $this->assertNotEmpty($endpoints);

        $endpoint = $endpoints[0];
        $this->assertTrue($endpoint->hasRequestBody());
        $this->assertInstanceOf(\Maan511\OpenapiToLaravel\Models\SchemaObject::class, $endpoint->requestSchema);

        $schema = $endpoint->requestSchema;
        $this->assertEquals('object', $schema->type);
        $this->assertArrayHasKey('name', $schema->properties);
        $this->assertArrayHasKey('email', $schema->properties);
        $this->assertEquals(['name', 'email'], $schema->required);
    }

    public function test_parser_resolves_reference_objects(): void
    {
        // Test basic reference resolution capabilities
        $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;

        $spec = $this->getSampleOpenApiSpec();
        $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($spec, 'test.json');

        // Test that we can resolve a reference to the User schema
        $userSchema = $referenceResolver->resolve('#/components/schemas/User', $specification);

        $this->assertNotNull($userSchema);
        $this->assertEquals('object', $userSchema['type']);
        $this->assertArrayHasKey('properties', $userSchema);
        $this->assertArrayHasKey('id', $userSchema['properties']);
        $this->assertArrayHasKey('name', $userSchema['properties']);
        $this->assertArrayHasKey('email', $userSchema['properties']);
    }

    public function test_parser_handles_parameters_as_request_source(): void
    {
        $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(
            new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver
        );

        $parameters = [
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ['name' => 'filter', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ];

        $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray([], 'test.json');
        $schema = $schemaExtractor->extractFromParameters($parameters, $specification);

        $this->assertNotNull($schema);
        $this->assertEquals('object', $schema->type);
        $this->assertArrayHasKey('id', $schema->properties);
        $this->assertArrayHasKey('filter', $schema->properties);
        $this->assertEquals(['id'], $schema->required);
    }

    public function test_parser_validates_required_specification_sections(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        // Test spec missing required sections
        $incompleteSpec = [
            'openapi' => '3.0.0',
            // Missing info and paths
        ];

        $specification = \Maan511\OpenapiToLaravel\Models\OpenApiSpecification::fromArray($incompleteSpec, 'test.json');
        $validation = $parser->validateSpecification($specification);

        $this->assertFalse($validation['valid']);
        $this->assertContains('Missing required info section', $validation['errors']);
        $this->assertContains('Missing required paths section', $validation['errors']);
    }

    public function test_parser_handles_malformed_specifications(): void
    {
        $parser = new OpenApiParser(
            new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor(new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver)
        );

        $this->expectException(InvalidArgumentException::class);
        $parser->parseFromString('invalid json', 'json');
    }

    /**
     * Helper method to get sample OpenAPI specification for tests
     */
    protected function getSampleOpenApiSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
                'description' => 'A test API specification',
            ],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'summary' => 'Create a new user',
                        'tags' => ['users'],
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
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
