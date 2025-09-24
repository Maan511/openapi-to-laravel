<?php

namespace Maan511\OpenapiToLaravel\Parser;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Exception;
use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;
use Maan511\OpenapiToLaravel\Models\SchemaObject;
use stdClass;
use Throwable;

/**
 * Main OpenAPI parsing service
 */
class OpenApiParser
{
    public function __construct(
        private readonly SchemaExtractor $schemaExtractor
    ) {}

    /**
     * Parse OpenAPI specification from file
     */
    public function parseFromFile(string $filePath): OpenApiSpecification
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("OpenAPI specification file not found: {$filePath}");
        }

        if (! is_readable($filePath)) {
            throw new InvalidArgumentException("OpenAPI specification file is not readable: {$filePath}");
        }

        try {
            // Convert to absolute path to handle references properly
            $absolutePath = realpath($filePath);
            if ($absolutePath === false) {
                throw new InvalidArgumentException("Unable to resolve absolute path for: {$filePath}");
            }

            // Detect format from file extension
            $format = $this->detectFormat($absolutePath);

            if ($format === 'yaml') {
                $spec = Reader::readFromYamlFile($absolutePath);
            } else {
                $spec = Reader::readFromJsonFile($absolutePath);
            }

            return $this->parseOpenApiSpec($spec, $absolutePath);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                "Failed to parse OpenAPI specification from {$filePath}: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Parse OpenAPI specification from string content
     */
    public function parseFromString(string $content, string $format = 'json', string $sourcePath = ''): OpenApiSpecification
    {
        try {
            if ($format === 'yaml') {
                $spec = Reader::readFromYaml($content);
            } else {
                $spec = Reader::readFromJson($content);
            }

            return $this->parseOpenApiSpec($spec, $sourcePath ?: 'string');
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                'Failed to parse OpenAPI specification from string: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Extract all endpoints with request schemas from specification
     *
     * @return array<EndpointDefinition>
     */
    public function extractEndpoints(OpenApiSpecification $specification): array
    {
        $endpoints = [];

        foreach ($specification->paths as $path => $pathItem) {
            $operations = $specification->getOperationsForPath($path);

            foreach ($operations as $method => $operation) {
                $method = strtoupper($method);

                // Skip non-HTTP methods (like parameters, summary, etc.)
                if (! in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'])) {
                    continue;
                }

                $requestSchema = $this->extractRequestSchema($operation, $specification);

                $endpoint = EndpointDefinition::fromOperation($path, $method, $operation, $requestSchema);
                $endpoints[] = $endpoint;
            }
        }

        return $endpoints;
    }

    /**
     * Get endpoints that have request bodies (for FormRequest generation)
     *
     * @return array<EndpointDefinition>
     */
    public function getEndpointsWithRequestBodies(OpenApiSpecification $specification): array
    {
        $allEndpoints = $this->extractEndpoints($specification);

        return array_values(array_filter($allEndpoints, fn (EndpointDefinition $endpoint) => $endpoint->hasRequestBody()));
    }

    /**
     * Parse specification and return complete analysis
     *
     * @return array{specification: OpenApiSpecification, endpoints: array<EndpointDefinition>}
     */
    public function parseSpecification(string $content, string $format): array
    {
        $specification = $this->parseFromString($content, $format);
        $endpoints = $this->extractEndpoints($specification);

        return [
            'specification' => $specification,
            'endpoints' => $endpoints,
        ];
    }

    /**
     * Validate OpenAPI specification structure
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateSpecification(OpenApiSpecification $specification): array
    {
        $errors = [];
        $warnings = [];

        // Check for required sections
        if (empty($specification->info)) {
            $errors[] = 'Missing required info section';
        }

        if (empty($specification->paths)) {
            $errors[] = 'Missing required paths section';
        }

        // Check version compatibility
        if (! preg_match('/^3\.[0-1]\.\d+$/', $specification->version)) {
            $warnings[] = "OpenAPI version {$specification->version} may not be fully supported";
        }

        // Check for endpoints with request bodies
        $endpointsWithBodies = $this->getEndpointsWithRequestBodies($specification);
        if (empty($endpointsWithBodies)) {
            $warnings[] = 'No endpoints with request bodies found - no FormRequests will be generated';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get specification statistics
     *
     * @return array{totalEndpoints: int, endpointsWithRequestBodies: int, httpMethods: array<string>, tags: array<string>, hasComponents: bool, schemaCount: int}
     */
    public function getSpecificationStats(OpenApiSpecification $specification): array
    {
        $endpoints = $this->extractEndpoints($specification);
        $endpointsWithBodies = array_filter($endpoints, fn ($e) => $e->hasRequestBody());

        $methods = [];
        $tags = [];
        foreach ($endpoints as $endpoint) {
            $methods[] = $endpoint->method;
            $tags = array_merge($tags, $endpoint->tags);
        }

        return [
            'totalEndpoints' => count($endpoints),
            'endpointsWithRequestBodies' => count($endpointsWithBodies),
            'httpMethods' => array_unique($methods),
            'tags' => array_unique($tags),
            'hasComponents' => $specification->hasComponents(),
            'schemaCount' => count($specification->getSchemas()),
        ];
    }

    /**
     * Parse cebe OpenApi object to our model
     */
    private function parseOpenApiSpec(OpenApi $spec, string $filePath): OpenApiSpecification
    {
        // Convert to array for easier processing
        $specData = $spec->getSerializableData();

        // Convert stdClass to array if necessary
        if ($specData instanceof stdClass) {
            $jsonString = json_encode($specData);
            if ($jsonString === false) {
                throw new InvalidArgumentException('Failed to encode OpenAPI specification to JSON');
            }
            /** @var array<string, mixed> $specArray */
            $specArray = json_decode($jsonString, true);
        } else {
            /** @var array<string, mixed> $specArray */
            $specArray = $specData;
        }

        return OpenApiSpecification::fromArray($specArray, $filePath);
    }

    /**
     * Extract request schema from operation
     *
     * @param array<string, mixed> $operation
     */
    private function extractRequestSchema(array $operation, OpenApiSpecification $specification): ?SchemaObject
    {
        // Try to get schema from requestBody
        if (isset($operation['requestBody'])) {
            return $this->schemaExtractor->extractFromRequestBody($operation['requestBody'], $specification);
        }

        // Try to get schema from parameters
        if (isset($operation['parameters']) && ! empty($operation['parameters'])) {
            return $this->schemaExtractor->extractFromParameters($operation['parameters'], $specification);
        }

        return null;
    }

    /**
     * Detect file format from extension
     */
    private function detectFormat(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'yaml', 'yml' => 'yaml',
            'json' => 'json',
            default => 'json', // Default to JSON
        };
    }

    /**
     * Check if file is a valid OpenAPI specification
     */
    public function isValidOpenApiFile(string $filePath): bool
    {
        try {
            $this->parseFromFile($filePath);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get supported file extensions
     *
     * @return array<string>
     */
    public function getSupportedExtensions(): array
    {
        return ['json', 'yaml', 'yml'];
    }

    /**
     * Resolve all references in specification
     */
    public function resolveReferences(OpenApiSpecification $specification): OpenApiSpecification
    {
        // This would use the ReferenceResolver to resolve all $ref objects
        // For now, return the specification as-is
        // The actual resolution happens during schema extraction
        return $specification;
    }
}
