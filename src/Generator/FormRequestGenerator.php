<?php

namespace Maan511\OpenapiToLaravel\Generator;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Models\SchemaObject;

/**
 * Generates Laravel FormRequest classes from OpenAPI schemas
 */
class FormRequestGenerator
{
    public function __construct(
        private readonly ValidationRuleMapper $ruleMapper
    ) {}

    /**
     * Generate FormRequest class from endpoint and schema
     *
     * @param  array<string, mixed>  $options
     */
    public function generateFromEndpoint(
        EndpointDefinition $endpoint,
        string $namespace,
        string $outputDir,
        array $options = []
    ): FormRequestClass {
        if (! $endpoint->hasRequestBody()) {
            throw new InvalidArgumentException("Endpoint {$endpoint->getDisplayName()} has no request body");
        }

        $className = $endpoint->generateFormRequestClassName();
        $filePath = $this->buildFilePath($outputDir, $className);

        // Get validation rules as string array for Laravel
        $validationRulesArray = $this->ruleMapper->mapValidationRules($endpoint->requestSchema);

        // Get validation rules as ValidationRule objects for the model
        $validationRuleObjects = $this->ruleMapper->createValidationRules($endpoint->requestSchema);

        // Create extended options for internal use without modifying the original
        $extendedOptions = array_merge($options, ['validationRuleObjects' => $validationRuleObjects]);

        return FormRequestClass::create(
            className: $className,
            namespace: $namespace,
            filePath: $filePath,
            validationRules: $validationRulesArray,
            sourceSchema: $endpoint->requestSchema,
            endpoint: $endpoint,
            options: $options // Use original options, not extended
        );
    }

    /**
     * Generate FormRequest class from schema directly
     *
     * @param  array<string, mixed>  $options
     */
    public function generateFromSchema(
        SchemaObject $schema,
        string $className,
        string $namespace,
        string $outputDir,
        array $options = []
    ): FormRequestClass {
        $filePath = $this->buildFilePath($outputDir, $className);

        $validationRules = $this->ruleMapper->mapValidationRules($schema);

        return FormRequestClass::create(
            className: $className,
            namespace: $namespace,
            filePath: $filePath,
            validationRules: $validationRules,
            sourceSchema: $schema,
            options: $options
        );
    }

    /**
     * Generate multiple FormRequest classes from endpoints
     *
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<string, mixed>  $options
     * @return array<FormRequestClass>
     */
    public function generateFromEndpoints(
        array $endpoints,
        string $namespace,
        string $outputDir,
        array $options = []
    ): array {

        $formRequests = [];
        $classNames = [];

        foreach ($endpoints as $endpoint) {

            if (! $endpoint->hasRequestBody()) {
                continue; // Skip endpoints without request bodies
            }

            $className = $endpoint->generateFormRequestClassName();

            // Handle naming conflicts
            if (in_array($className, $classNames)) {
                $className = $this->resolveNamingConflict($className, $endpoint, $classNames);
            }

            $classNames[] = $className;

            $filePath = $this->buildFilePath($outputDir, $className);
            $validationRules = $this->ruleMapper->mapValidationRules($endpoint->requestSchema);

            $formRequest = FormRequestClass::create(
                className: $className,
                namespace: $namespace,
                filePath: $filePath,
                validationRules: $validationRules,
                sourceSchema: $endpoint->requestSchema,
                endpoint: $endpoint,
                options: $options
            );

            $formRequests[] = $formRequest;
        }

        return $formRequests;
    }

    /**
     * Generate and write FormRequest class to file
     *
     * @return array{success: bool, message: string, filePath: string, className: string}
     */
    public function generateAndWrite(
        FormRequestClass $formRequest,
        bool $force = false
    ): array {
        $result = [
            'success' => false,
            'message' => '',
            'filePath' => $formRequest->filePath,
            'className' => $formRequest->className,
        ];

        // Check if file exists and force flag
        if (file_exists($formRequest->filePath) && ! $force) {
            $result['message'] = "File already exists and force flag not set: {$formRequest->filePath}";

            return $result;
        }

        // Ensure output directory exists
        $outputDir = dirname($formRequest->filePath);
        if (! is_dir($outputDir)) {
            if (! mkdir($outputDir, 0755, true)) {
                $result['message'] = "Failed to create output directory: {$outputDir}";

                return $result;
            }
        }

        // Generate PHP code
        $phpCode = $formRequest->generatePhpCode();

        // Write to file
        if (file_put_contents($formRequest->filePath, $phpCode) === false) {
            $result['message'] = "Failed to write file: {$formRequest->filePath}";

            return $result;
        }

        $result['success'] = true;
        $result['message'] = "Generated FormRequest: {$formRequest->className}";

        return $result;
    }

    /**
     * Generate multiple FormRequest classes and write to files
     *
     * @param array<FormRequestClass> $formRequests
     * @return array{summary: array{total: int, success: int, skipped: int, failed: int}, results: array<array{success: bool, message: string, filePath: string, className: string}>}
     */
    public function generateAndWriteMultiple(
        array $formRequests,
        bool $force = false
    ): array {
        $results = [];
        $summary = [
            'total' => count($formRequests),
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($formRequests as $formRequest) {
            $result = $this->generateAndWrite($formRequest, $force);
            $results[] = $result;

            if ($result['success']) {
                $summary['success']++;
            } elseif (str_contains($result['message'], 'already exists')) {
                $summary['skipped']++;
            } else {
                $summary['failed']++;
            }
        }

        return [
            'summary' => $summary,
            'results' => $results,
        ];
    }

    /**
     * Dry run - show what would be generated without writing files
     *
     * @param array<FormRequestClass> $formRequests
     * @return array<array{className: string, namespace: string, filePath: string, sourceEndpoint: string, rulesCount: int, complexity: int, fileExists: bool, estimatedSize: int}>
     */
    public function dryRun(array $formRequests): array
    {
        $results = [];

        foreach ($formRequests as $formRequest) {

            $results[] = [
                'className' => $formRequest->className,
                'namespace' => $formRequest->namespace,
                'filePath' => $formRequest->filePath,
                'sourceEndpoint' => $formRequest->getSourceEndpoint(),
                'rulesCount' => $formRequest->getValidationRulesCount(),
                'complexity' => $formRequest->getComplexityScore(),
                'fileExists' => $formRequest->fileExists(),
                'estimatedSize' => $formRequest->getFileSizeEstimate(),
            ];
        }

        return $results;
    }

    /**
     * Validate generated FormRequest classes
     *
     * @param array<FormRequestClass> $formRequests
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validate(array $formRequests): array
    {
        $errors = [];
        $warnings = [];

        foreach ($formRequests as $formRequest) {

            // Validate class name
            if (! preg_match('/^[A-Z][a-zA-Z0-9]*Request$/', $formRequest->className)) {
                $errors[] = "Invalid class name: {$formRequest->className}";
            }

            // Validate namespace
            if (! preg_match('/^[A-Z][a-zA-Z0-9_\\\\]*[a-zA-Z0-9]$/', $formRequest->namespace)) {
                $errors[] = "Invalid namespace: {$formRequest->namespace}";
            }

            // Validate validation rules
            if (empty($formRequest->validationRules)) {
                $warnings[] = "No validation rules for {$formRequest->className}";
            }

            // Check for validation rule syntax
            $ruleErrors = $this->ruleMapper->validateLaravelRules($formRequest->validationRules);
            foreach ($ruleErrors as $error) {
                $errors[] = "In {$formRequest->className}: {$error}";
            }

            // Check file path
            if (! str_ends_with($formRequest->filePath, '.php')) {
                $errors[] = "Invalid file path: {$formRequest->filePath}";
            }

            // Check complexity
            if ($formRequest->getComplexityScore() > 100) {
                $warnings[] = "High complexity in {$formRequest->className} (score: {$formRequest->getComplexityScore()})";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get generation statistics
     *
     * @param array<FormRequestClass> $formRequests
     * @return array{totalClasses: int, totalRules: int, totalComplexity: int, estimatedTotalSize: int, namespaces: array<string>, mostComplex: ?array{className: string, complexity: int}, averageComplexity: float}
     */
    public function getStats(array $formRequests): array
    {
        $stats = [
            'totalClasses' => count($formRequests),
            'totalRules' => 0,
            'totalComplexity' => 0,
            'estimatedTotalSize' => 0,
            'namespaces' => [],
            'mostComplex' => null,
            'averageComplexity' => 0,
        ];

        $complexities = [];

        foreach ($formRequests as $formRequest) {
            $stats['totalRules'] += $formRequest->getValidationRulesCount();
            $complexity = $formRequest->getComplexityScore();
            $stats['totalComplexity'] += $complexity;
            $stats['estimatedTotalSize'] += $formRequest->getFileSizeEstimate();

            $complexities[] = $complexity;

            if (! in_array($formRequest->namespace, $stats['namespaces'])) {
                $stats['namespaces'][] = $formRequest->namespace;
            }

            if ($stats['mostComplex'] === null || $complexity > $stats['mostComplex']['complexity']) {
                $stats['mostComplex'] = [
                    'className' => $formRequest->className,
                    'complexity' => $complexity,
                ];
            }
        }

        if (! empty($complexities)) {
            $stats['averageComplexity'] = array_sum($complexities) / count($complexities);
        }

        return $stats;
    }

    /**
     * Build file path for FormRequest class
     */
    private function buildFilePath(string $outputDir, string $className): string
    {
        $outputDir = rtrim($outputDir, '/\\');

        return $outputDir . DIRECTORY_SEPARATOR . $className . '.php';
    }

    /**
     * Resolve naming conflicts by appending method or path info
     *
     * @param  array<string>  $existingNames
     */
    private function resolveNamingConflict(
        string $className,
        EndpointDefinition $endpoint,
        array $existingNames
    ): string {
        $baseClassName = rtrim($className, 'Request');
        $counter = 2;

        // Try appending method
        $newClassName = $baseClassName . ucfirst(strtolower($endpoint->method)) . 'Request';
        if (! in_array($newClassName, $existingNames)) {
            return $newClassName;
        }

        // Try appending counter
        do {
            $newClassName = $baseClassName . $counter . 'Request';
            $counter++;
        } while (in_array($newClassName, $existingNames));

        return $newClassName;
    }
}
