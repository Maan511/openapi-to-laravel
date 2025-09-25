<?php

function createTestParser(): \Maan511\OpenapiToLaravel\Parser\OpenApiParser
{
    $referenceResolver = new \Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
    $schemaExtractor = new \Maan511\OpenapiToLaravel\Parser\SchemaExtractor($referenceResolver);

    return new \Maan511\OpenapiToLaravel\Parser\OpenApiParser($schemaExtractor);
}

function createTestGenerator(): \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator
{
    $ruleMapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;

    return new \Maan511\OpenapiToLaravel\Generator\FormRequestGenerator($ruleMapper);
}

function createTempOpenApiSpec(): string
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

function createTempOutputDirectory(): string
{
    $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
    mkdir($tempDir, 0755, true);

    return $tempDir;
}
