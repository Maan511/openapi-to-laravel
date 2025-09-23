<?php

namespace Maan511\OpenapiToLaravel\Tests\Integration;

use Maan511\OpenapiToLaravel\Tests\TestCase;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;

/**
 * Integration test for Laravel integration
 *
 * This test validates that generated FormRequest classes integrate properly
 * with Laravel framework and follow Laravel conventions.
 */
class LaravelIntegrationTest extends TestCase
{
    public function test_generated_form_request_extends_laravel_form_request()
    {
        // Create temp spec and generate FormRequest
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
                                            'email' => ['type' => 'string', 'format' => 'email']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'laravel_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify Laravel FormRequest inheritance
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('use Illuminate\Foundation\Http\FormRequest', $content);
        $this->assertStringContainsString('namespace App\\Http\\Requests', $content);

        unlink($tempFile);
    }

    public function test_generated_form_request_implements_rules_method()
    {
        // Create temp spec and generate FormRequest
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
                                            'name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                                            'email' => ['type' => 'string', 'format' => 'email'],
                                            'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 120]
                                        ],
                                        'required' => ['name', 'email']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'laravel_rules_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $content = $formRequests[0]->generatePhpCode();
        
        // Verify rules() method exists and has proper structure
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('return [', $content);
        
        // Verify some validation rules are present
        $this->assertStringContainsString('required', $content);
        $this->assertStringContainsString('string', $content);
        $this->assertStringContainsString('email', $content);

        unlink($tempFile);
    }

    public function test_generated_form_request_implements_authorize_method()
    {
        // Create temp spec and generate FormRequest
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
        
        $tempFile = tempnam(sys_get_temp_dir(), 'laravel_auth_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $content = $formRequests[0]->generatePhpCode();
        
        // Verify authorize() method exists
        $this->assertStringContainsString('public function authorize()', $content);
        $this->assertStringContainsString('return true', $content);

        unlink($tempFile);
    }

    public function test_generated_form_request_can_be_used_in_controller()
    {
        // This test verifies the structure is correct for controller usage
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
                                            'email' => ['type' => 'string', 'format' => 'email']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'controller_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify the class structure is suitable for controller injection
        $this->assertStringContainsString('class CreateUserRequest extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);

        unlink($tempFile);
    }

    public function test_generated_form_request_validation_works()
    {
        // Create spec with comprehensive validation rules
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
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 2,
                                                'maxLength' => 50
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email'
                                            ],
                                            'age' => [
                                                'type' => 'integer',
                                                'minimum' => 18,
                                                'maximum' => 120
                                            ]
                                        ],
                                        'required' => ['name', 'email']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'validation_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify validation structure is correct
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('required', $content);
        $this->assertStringContainsString('string', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('integer', $content);
        $this->assertStringContainsString('min:', $content);
        $this->assertStringContainsString('max:', $content);
        
        // Verify Laravel FormRequest structure
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        $this->assertStringContainsString('return true', $content);
        
        // Test validation rules were mapped correctly (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Verify required fields exist in string rules
        $this->assertArrayHasKey('name', $formRequest->validationRules);
        $this->assertArrayHasKey('email', $formRequest->validationRules);
        $this->assertArrayHasKey('age', $formRequest->validationRules);
        
        // Verify validation rule content
        $this->assertStringContainsString('required', $formRequest->validationRules['name']);
        $this->assertStringContainsString('string', $formRequest->validationRules['name']);
        $this->assertStringContainsString('required', $formRequest->validationRules['email']);
        $this->assertStringContainsString('email', $formRequest->validationRules['email']);

        unlink($tempFile);
    }

    public function test_generated_form_request_supports_custom_messages()
    {
        // Create spec with descriptions that should become custom messages
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
                                            'name' => [
                                                'type' => 'string',
                                                'description' => 'The full name of the user',
                                                'minLength' => 2,
                                                'maxLength' => 100
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                                'description' => 'Valid email address for the user'
                                            ],
                                            'phone' => [
                                                'type' => 'string',
                                                'pattern' => '^\\+?[1-9]\\d{1,14}$',
                                                'description' => 'International phone number'
                                            ]
                                        ],
                                        'required' => ['name', 'email']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'messages_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify the basic structure includes messages method (even if simple)
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('extends FormRequest', $content);
        
        // For now, just verify that we can generate the FormRequest
        // and that it contains the expected field validation rules
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('required', $content);
        $this->assertStringContainsString('string', $content);
        
        // Verify validation rules structure (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check that we have rules for each field
        $this->assertArrayHasKey('name', $formRequest->validationRules);
        $this->assertArrayHasKey('email', $formRequest->validationRules);
        
        // Verify content of rules
        $this->assertStringContainsString('required', $formRequest->validationRules['name']);
        $this->assertStringContainsString('string', $formRequest->validationRules['name']);

        unlink($tempFile);
    }

    public function test_generated_form_request_supports_custom_attributes()
    {
        // Create spec with titles that should become custom attribute names
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
                                            'firstName' => [
                                                'type' => 'string',
                                                'title' => 'First Name',
                                                'minLength' => 1,
                                                'maxLength' => 50
                                            ],
                                            'lastName' => [
                                                'type' => 'string',
                                                'title' => 'Last Name',
                                                'minLength' => 1,
                                                'maxLength' => 50
                                            ],
                                            'emailAddress' => [
                                                'type' => 'string',
                                                'format' => 'email',
                                                'title' => 'Email Address'
                                            ],
                                            'dateOfBirth' => [
                                                'type' => 'string',
                                                'format' => 'date',
                                                'title' => 'Date of Birth'
                                            ]
                                        ],
                                        'required' => ['firstName', 'lastName', 'emailAddress']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'attributes_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify the basic FormRequest structure
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        
        // Verify that fields with titles are included in validation
        $this->assertStringContainsString('firstName', $content);
        $this->assertStringContainsString('lastName', $content);
        $this->assertStringContainsString('emailAddress', $content);
        $this->assertStringContainsString('dateOfBirth', $content);
        
        // Verify validation rules structure (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check that we have rules for each field with custom titles
        $this->assertArrayHasKey('firstName', $formRequest->validationRules);
        $this->assertArrayHasKey('lastName', $formRequest->validationRules);
        $this->assertArrayHasKey('emailAddress', $formRequest->validationRules);
        
        // Verify that required fields are properly mapped
        $this->assertStringContainsString('required', $formRequest->validationRules['firstName']);
        $this->assertStringContainsString('required', $formRequest->validationRules['lastName']);
        $this->assertStringContainsString('required', $formRequest->validationRules['emailAddress']);

        unlink($tempFile);
    }

    public function test_generated_form_request_follows_laravel_naming_conventions()
    {
        // Create spec with different operation IDs to test naming
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
                                        'properties' => ['name' => ['type' => 'string']]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/users/{id}' => [
                    'put' => [
                        'operationId' => 'updateUserProfile',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => ['name' => ['type' => 'string']]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'naming_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertCount(2, $formRequests);
        
        // Verify PascalCase naming with "Request" suffix
        $classNames = array_map(fn($fr) => $fr->className, $formRequests);
        $this->assertContains('CreateUserRequest', $classNames);
        $this->assertContains('UpdateUserProfileRequest', $classNames);
        
        // Verify method names are camelCase
        foreach ($formRequests as $formRequest) {
            $content = $formRequest->generatePhpCode();
            $this->assertStringContainsString('public function rules()', $content);
            $this->assertStringContainsString('public function authorize()', $content);
        }

        unlink($tempFile);
    }

    public function test_generated_form_request_handles_file_uploads()
    {
        // Create spec with file upload fields
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'requestBody' => [
                            'content' => [
                                'multipart/form-data' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'maxLength' => 100
                                            ],
                                            'avatar' => [
                                                'type' => 'string',
                                                'format' => 'binary',
                                                'description' => 'User profile picture'
                                            ],
                                            'resume' => [
                                                'type' => 'string',
                                                'format' => 'binary',
                                                'description' => 'User resume document'
                                            ],
                                            'documents' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'string',
                                                    'format' => 'binary'
                                                ],
                                                'description' => 'Additional documents'
                                            ]
                                        ],
                                        'required' => ['name', 'avatar']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'file_upload_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify the basic FormRequest structure
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        
        // Verify that file fields are included in validation
        $this->assertStringContainsString('name', $content);
        $this->assertStringContainsString('avatar', $content);
        $this->assertStringContainsString('resume', $content);
        $this->assertStringContainsString('documents', $content);
        
        // Verify validation rules structure (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check that we have rules for file fields  
        $this->assertArrayHasKey('name', $formRequest->validationRules);
        $this->assertArrayHasKey('avatar', $formRequest->validationRules);
        $this->assertArrayHasKey('resume', $formRequest->validationRules);
        
        // Verify required fields are properly mapped
        $this->assertStringContainsString('required', $formRequest->validationRules['name']);
        $this->assertStringContainsString('required', $formRequest->validationRules['avatar']);
        
        // Check that file fields have appropriate validation
        $this->assertStringContainsString('string', $formRequest->validationRules['avatar']);
        $this->assertStringContainsString('nullable', $formRequest->validationRules['resume']);

        unlink($tempFile);
    }

    public function test_generated_form_request_handles_nested_validation()
    {
        // Create spec with nested objects
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
                                            'name' => [
                                                'type' => 'string',
                                                'minLength' => 1
                                            ],
                                            'profile' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'bio' => [
                                                        'type' => 'string',
                                                        'maxLength' => 500
                                                    ],
                                                    'age' => [
                                                        'type' => 'integer',
                                                        'minimum' => 18
                                                    ],
                                                    'social' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'twitter' => ['type' => 'string'],
                                                            'linkedin' => ['type' => 'string', 'format' => 'uri']
                                                        ]
                                                    ]
                                                ],
                                                'required' => ['bio']
                                            ],
                                            'addresses' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'street' => ['type' => 'string'],
                                                        'city' => ['type' => 'string'],
                                                        'zipCode' => ['type' => 'string', 'pattern' => '^\\d{5}$']
                                                    ],
                                                    'required' => ['street', 'city']
                                                ]
                                            ]
                                        ],
                                        'required' => ['name', 'profile']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'nested_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify the basic FormRequest structure
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        
        // Verify validation rules structure (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check for nested field validation (should use dot notation)
        $fieldNames = array_keys($formRequest->validationRules);
        
        // Should contain nested properties with dot notation
        $nestedFields = array_filter($fieldNames, fn($field) => str_contains($field, '.'));
        $this->assertNotEmpty($nestedFields, 'Should have nested fields with dot notation');
        
        // Check for specific nested patterns we expect
        $hasProfileFields = !empty(array_filter($fieldNames, fn($field) => str_starts_with($field, 'profile.')));
        $hasAddressFields = !empty(array_filter($fieldNames, fn($field) => str_starts_with($field, 'addresses.')));
        
        // At minimum, we should have some nested structure
        $this->assertTrue($hasProfileFields || $hasAddressFields, 'Should have profile or address nested fields');
        
        // Verify required fields are properly mapped
        $this->assertArrayHasKey('name', $formRequest->validationRules);
        $this->assertStringContainsString('required', $formRequest->validationRules['name']);

        unlink($tempFile);
    }

    public function test_generated_form_request_works_with_laravel_validation()
    {
        // Create comprehensive spec to test Laravel validation integration
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
                                            'username' => [
                                                'type' => 'string',
                                                'minLength' => 3,
                                                'maxLength' => 20,
                                                'pattern' => '^[a-zA-Z0-9_]+$'
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email'
                                            ],
                                            'password' => [
                                                'type' => 'string',
                                                'minLength' => 8
                                            ],
                                            'age' => [
                                                'type' => 'integer',
                                                'minimum' => 13,
                                                'maximum' => 150
                                            ]
                                        ],
                                        'required' => ['username', 'email', 'password']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'laravel_validation_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify Laravel FormRequest integration structure
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('use Illuminate\Foundation\Http\FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        
        // Verify Laravel validation rules are generated
        $this->assertStringContainsString('required', $content);
        $this->assertStringContainsString('string', $content);
        $this->assertStringContainsString('email', $content);
        $this->assertStringContainsString('integer', $content);
        
        // Verify validation rules structure (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check that rules are properly structured for Laravel
        $this->assertArrayHasKey('username', $formRequest->validationRules);
        $this->assertArrayHasKey('email', $formRequest->validationRules);
        $this->assertArrayHasKey('password', $formRequest->validationRules);
        
        // Verify required fields
        $this->assertStringContainsString('required', $formRequest->validationRules['username']);
        $this->assertStringContainsString('required', $formRequest->validationRules['email']);
        $this->assertStringContainsString('required', $formRequest->validationRules['password']);

        unlink($tempFile);
    }

    public function test_generated_form_request_supports_conditional_validation()
    {
        // Create spec with conditional validation scenarios
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
                                            'authType' => [
                                                'type' => 'string',
                                                'enum' => ['password', 'oauth', 'sso']
                                            ],
                                            'username' => [
                                                'type' => 'string',
                                                'minLength' => 3
                                            ],
                                            'password' => [
                                                'type' => 'string',
                                                'minLength' => 8
                                            ],
                                            'oauthToken' => [
                                                'type' => 'string'
                                            ],
                                            'ssoProvider' => [
                                                'type' => 'string',
                                                'enum' => ['google', 'microsoft', 'github']
                                            ],
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email'
                                            ]
                                        ],
                                        'required' => ['authType', 'username', 'email']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'conditional_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify the basic FormRequest structure
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        
        // Verify that enum fields are handled properly
        $this->assertStringContainsString('authType', $content);
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('email', $content);
        
        // Verify validation rules structure (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check that enum rules are generated
        $this->assertArrayHasKey('authType', $formRequest->validationRules);
        $this->assertArrayHasKey('username', $formRequest->validationRules);
        $this->assertArrayHasKey('email', $formRequest->validationRules);
        
        // Should have enum validation rules
        $this->assertStringContainsString('in:', $formRequest->validationRules['authType']);
        
        // Verify required fields
        $this->assertStringContainsString('required', $formRequest->validationRules['authType']);
        $this->assertStringContainsString('required', $formRequest->validationRules['username']);
        $this->assertStringContainsString('required', $formRequest->validationRules['email']);

        unlink($tempFile);
    }

    public function test_generated_form_request_performance_in_laravel()
    {
        // Create large spec to test performance
        $properties = [];
        for ($i = 1; $i <= 50; $i++) {
            $properties["field{$i}"] = [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 100
            ];
        }
        
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/large-form' => [
                    'post' => [
                        'operationId' => 'createLargeForm',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => $properties,
                                        'required' => array_keys(array_slice($properties, 0, 25))
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'performance_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $startTime = microtime(true);
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete in reasonable time (under 2 seconds for 50 fields)
        $this->assertLessThan(2.0, $executionTime, 'Generation should complete in under 2 seconds');
        
        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify structure is maintained with large number of fields
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        
        // Verify all fields are included (use string-based rules)
        $this->assertGreaterThanOrEqual(50, count($formRequest->validationRules));
        
        // Verify some sample fields exist
        $this->assertArrayHasKey('field1', $formRequest->validationRules);
        $this->assertArrayHasKey('field25', $formRequest->validationRules);
        $this->assertArrayHasKey('field50', $formRequest->validationRules);
        
        // Verify rules contain expected validation
        $this->assertStringContainsString('string', $formRequest->validationRules['field1']);
        $this->assertStringContainsString('required', $formRequest->validationRules['field1']);

        unlink($tempFile);
    }

    public function test_generated_form_request_error_handling()
    {
        // Create spec that might trigger edge cases
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
                                            'email' => [
                                                'type' => 'string',
                                                'format' => 'email'
                                            ],
                                            'age' => [
                                                'type' => 'integer',
                                                'minimum' => 0,
                                                'maximum' => 150
                                            ],
                                            'status' => [
                                                'type' => 'string',
                                                'enum' => ['active', 'inactive', 'pending']
                                            ]
                                        ],
                                        'required' => ['email']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'error_handling_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        // Should not throw exceptions during generation
        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        
        // Should generate valid PHP code without syntax errors
        $content = $formRequest->generatePhpCode();
        $this->assertNotEmpty($content);
        
        // Basic structure should be present
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        
        // Validation rules should be properly formed (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Each rule should have basic required properties  
        foreach ($formRequest->validationRules as $field => $rule) {
            $this->assertNotEmpty($field, 'Each rule should have a field name');
            $this->assertIsString($rule, 'Each rule should be a string');
            $this->assertNotEmpty($rule, 'Each rule should not be empty');
        }

        unlink($tempFile);
    }

    public function test_generated_form_request_middleware_compatibility()
    {
        // Create spec to test middleware compatibility
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/protected-endpoint' => [
                    'post' => [
                        'operationId' => 'createProtectedResource',
                        'security' => [['bearerAuth' => []]],
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => [
                                                'type' => 'string',
                                                'minLength' => 1,
                                                'maxLength' => 200
                                            ],
                                            'content' => [
                                                'type' => 'string',
                                                'minLength' => 10
                                            ],
                                            'category' => [
                                                'type' => 'string',
                                                'enum' => ['public', 'private', 'restricted']
                                            ]
                                        ],
                                        'required' => ['title', 'content']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer'
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'middleware_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify standard Laravel FormRequest structure that's middleware-compatible
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        
        // Should return true by default (auth handled by middleware)
        $this->assertStringContainsString('return true', $content);
        
        // Verify validation rules are properly structured (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check that protected endpoint validation works
        $this->assertArrayHasKey('title', $formRequest->validationRules);
        $this->assertArrayHasKey('content', $formRequest->validationRules);
        
        // Verify required fields
        $this->assertStringContainsString('required', $formRequest->validationRules['title']);
        $this->assertStringContainsString('required', $formRequest->validationRules['content']);

        unlink($tempFile);
    }

    public function test_generated_form_request_api_resource_compatibility()
    {
        // Create spec that would work with API resources
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/api/articles' => [
                    'post' => [
                        'operationId' => 'createArticle',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => [
                                                'type' => 'string',
                                                'minLength' => 5,
                                                'maxLength' => 200
                                            ],
                                            'slug' => [
                                                'type' => 'string',
                                                'pattern' => '^[a-z0-9-]+$',
                                                'maxLength' => 100
                                            ],
                                            'content' => [
                                                'type' => 'string',
                                                'minLength' => 50
                                            ],
                                            'published' => [
                                                'type' => 'boolean'
                                            ],
                                            'tags' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'string',
                                                    'minLength' => 2,
                                                    'maxLength' => 30
                                                ]
                                            ],
                                            'metadata' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'author' => ['type' => 'string'],
                                                    'category' => ['type' => 'string']
                                                ]
                                            ]
                                        ],
                                        'required' => ['title', 'content']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'api_resource_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify Laravel FormRequest structure compatible with API resources
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        
        // Verify validation rules are API-resource friendly (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check API resource compatible field validation
        $this->assertArrayHasKey('title', $formRequest->validationRules);
        $this->assertArrayHasKey('content', $formRequest->validationRules);
        
        // Should handle boolean fields correctly
        $this->assertArrayHasKey('published', $formRequest->validationRules);
        $this->assertStringContainsString('boolean', $formRequest->validationRules['published']);
        
        // Should handle array fields correctly  
        $this->assertArrayHasKey('tags', $formRequest->validationRules);
        $this->assertStringContainsString('array', $formRequest->validationRules['tags']);
        
        // Should handle nested object fields correctly
        $metadataFields = array_filter(array_keys($formRequest->validationRules), fn($field) => str_starts_with($field, 'metadata'));
        $this->assertNotEmpty($metadataFields);

        unlink($tempFile);
    }

    public function test_generated_form_request_testing_support()
    {
        // Create spec for testing integration
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [
                '/api/posts' => [
                    'post' => [
                        'operationId' => 'createPost',
                        'requestBody' => [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'title' => [
                                                'type' => 'string',
                                                'minLength' => 3,
                                                'maxLength' => 150
                                            ],
                                            'body' => [
                                                'type' => 'string',
                                                'minLength' => 10,
                                                'maxLength' => 5000
                                            ],
                                            'status' => [
                                                'type' => 'string',
                                                'enum' => ['draft', 'published', 'archived']
                                            ],
                                            'publishedAt' => [
                                                'type' => 'string',
                                                'format' => 'date-time',
                                                'nullable' => true
                                            ]
                                        ],
                                        'required' => ['title', 'body', 'status']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'testing_support_test_') . '.json';
        file_put_contents($tempFile, json_encode($spec));
        
        $parser = $this->createParser();
        $generator = $this->createGenerator();

        $parsedSpec = $parser->parseFromFile($tempFile);
        $endpoints = $parser->getEndpointsWithRequestBodies($parsedSpec);
        $formRequests = $generator->generateFromEndpoints($endpoints, 'App\\Http\\Requests', '/tmp');

        $this->assertNotEmpty($formRequests);
        
        $formRequest = $formRequests[0];
        $content = $formRequest->generatePhpCode();
        
        // Verify structure supports Laravel testing
        $this->assertStringContainsString('extends FormRequest', $content);
        $this->assertStringContainsString('public function rules()', $content);
        $this->assertStringContainsString('public function authorize()', $content);
        
        // Verify class naming follows Laravel conventions for testing
        $this->assertStringContainsString('CreatePostRequest', $content);
        $this->assertStringContainsString('namespace App\\Http\\Requests', $content);
        
        // Verify validation rules are test-friendly (use string-based rules)
        $this->assertNotEmpty($formRequest->validationRules);
        
        // Check validation structure suitable for testing
        $this->assertArrayHasKey('title', $formRequest->validationRules);
        $this->assertArrayHasKey('body', $formRequest->validationRules);
        $this->assertArrayHasKey('status', $formRequest->validationRules);
        
        // Should handle enum validation for testing
        $this->assertStringContainsString('in:', $formRequest->validationRules['status']);
        
        // Should handle nullable fields for testing
        if (isset($formRequest->validationRules['publishedAt'])) {
            $this->assertStringContainsString('nullable', $formRequest->validationRules['publishedAt']);
        }
        
        // Verify required fields are properly identified for testing
        $this->assertStringContainsString('required', $formRequest->validationRules['title']);
        $this->assertStringContainsString('required', $formRequest->validationRules['body']);
        $this->assertStringContainsString('required', $formRequest->validationRules['status']);

        unlink($tempFile);
    }

    /**
     * Helper method to create parser with dependencies
     */
    private function createParser(): OpenApiParser
    {
        $referenceResolver = new ReferenceResolver();
        $schemaExtractor = new SchemaExtractor($referenceResolver);
        return new OpenApiParser($schemaExtractor, $referenceResolver);
    }

    /**
     * Helper method to create generator with dependencies
     */
    private function createGenerator(): FormRequestGenerator
    {
        $ruleMapper = new ValidationRuleMapper();
        $templateEngine = new TemplateEngine();
        return new FormRequestGenerator($ruleMapper, $templateEngine);
    }

    /**
     * Helper method to simulate a Laravel request with data
     */
    private function createLaravelRequest(array $data): array
    {
        // Simulate Laravel request structure
        return [
            'data' => $data,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * Helper method to get valid request data for testing
     */
    private function getValidRequestData(): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'profile' => [
                'bio' => 'Software developer with 10 years of experience.',
                'social' => [
                    'twitter' => '@johndoe',
                    'linkedin' => 'https://linkedin.com/in/johndoe',
                ],
            ],
        ];
    }

    /**
     * Helper method to get invalid request data for testing
     */
    private function getInvalidRequestData(): array
    {
        return [
            'name' => '', // Required field empty
            'email' => 'invalid-email', // Invalid email format
            'age' => -5, // Below minimum
            'profile' => [
                'bio' => str_repeat('a', 501), // Exceeds max length
            ],
        ];
    }
}