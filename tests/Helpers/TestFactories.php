<?php

use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;

function createTestParser(): OpenApiParser
{
    $referenceResolver = new ReferenceResolver;
    $schemaExtractor = new SchemaExtractor($referenceResolver);

    return new OpenApiParser($schemaExtractor);
}

function createTestGenerator(): FormRequestGenerator
{
    $ruleMapper = new ValidationRuleMapper;

    return new FormRequestGenerator($ruleMapper);
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

    $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test_');
    unlink($tempFile); // Remove the empty temp file created by tempnam()
    $tempFile .= '.json'; // Add .json extension
    file_put_contents($tempFile, json_encode($spec));

    return $tempFile;
}

function createTempOutputDirectory(): string
{
    $tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
    mkdir($tempDir, 0755, true);

    return $tempDir;
}
