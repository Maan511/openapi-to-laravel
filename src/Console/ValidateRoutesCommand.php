<?php

namespace Maan511\OpenapiToLaravel\Console;

use function app;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use Maan511\OpenapiToLaravel\Validation\LaravelRouteCollector;
use Maan511\OpenapiToLaravel\Validation\Reporters\ReporterFactory;
use Maan511\OpenapiToLaravel\Validation\RouteValidator;

/**
 * Console command for validating routes against OpenAPI specification
 */
class ValidateRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'openapi-to-laravel:validate-routes
                            {spec : Path to OpenAPI specification file}
                            {--base-path= : Override server base path (e.g., /api)}
                            {--include-pattern=* : Route URI patterns to include (supports wildcards)}
                            {--exclude-middleware=* : Middleware groups to exclude}
                            {--ignore-route=* : Route names/patterns to ignore}
                            {--report-format=table : Report format (console, json, html, table)}
                            {--output-file= : Save report to file}
                            {--strict : Fail command on any mismatches}
                            {--suggestions : Include fix suggestions in output}
                            {--filter-type=* : Filter by error type (missing-documentation, missing-implementation, method-mismatch, parameter-mismatch, path-mismatch, validation-error)}';

    /**
     * The console command description.
     */
    protected $description = 'Validate that Laravel routes match OpenAPI specification endpoints';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specPathValue = $this->argument('spec');
        $specPath = is_string($specPathValue) ? $specPathValue : '';

        /** @var string|null $basePath */
        $basePath = is_string($this->option('base-path')) ? $this->option('base-path') : null;
        /** @var array<string> $includePatterns */
        $includePatterns = is_array($this->option('include-pattern')) ? $this->option('include-pattern') : [];
        /** @var array<string> $excludeMiddleware */
        $excludeMiddleware = is_array($this->option('exclude-middleware')) ? $this->option('exclude-middleware') : [];
        /** @var array<string> $ignoreRoutes */
        $ignoreRoutes = is_array($this->option('ignore-route')) ? $this->option('ignore-route') : [];
        /** @var array<string> $filterTypes */
        $filterTypes = is_array($this->option('filter-type')) ? $this->option('filter-type') : [];
        /** @var string $reportFormat */
        $reportFormat = is_string($this->option('report-format')) ? $this->option('report-format') : 'console';
        /** @var string|null $outputFile */
        $outputFile = is_string($this->option('output-file')) ? $this->option('output-file') : null;
        $strict = (bool) $this->option('strict');
        $suggestions = (bool) $this->option('suggestions');

        try {
            // Initialize services
            $router = app(Router::class);
            $referenceResolver = new ReferenceResolver;
            $schemaExtractor = new SchemaExtractor($referenceResolver);
            $parser = new OpenApiParser($schemaExtractor);
            $routeCollector = new LaravelRouteCollector($router);
            $validator = new RouteValidator($routeCollector, $parser);

            $this->info('Starting route validation...');
            $this->info("OpenAPI spec: {$specPath}");

            if ($basePath !== null) {
                $this->info("Base path override: {$basePath}");
            }

            if (! empty($includePatterns)) {
                $this->info('Include patterns: ' . implode(', ', $includePatterns));
            }

            if (! empty($excludeMiddleware)) {
                $this->info('Exclude middleware: ' . implode(', ', $excludeMiddleware));
            }

            if (! empty($filterTypes)) {
                $normalizedTypes = $this->normalizeFilterTypes($filterTypes);
                if ($normalizedTypes !== []) {
                    $this->info('Filter by types: ' . implode(', ', $normalizedTypes));
                }
            }

            // Validate inputs
            $validationResult = $this->validateInputs($specPath);
            if (! $validationResult['success']) {
                $this->error($validationResult['message'] ?? 'Validation failed');

                return 1;
            }

            // Prepare validation options
            $options = [
                'base_path' => $basePath,
                'include_patterns' => $includePatterns,
                'exclude_middleware' => $excludeMiddleware,
                'ignore_routes' => $ignoreRoutes,
                'filter_types' => $this->normalizeFilterTypes($filterTypes),
            ];

            // Perform validation
            $result = $validator->validate($specPath, $options);

            // Display results based on format
            $this->displayResults($result, $reportFormat, $suggestions);

            // Save to file if requested
            if ($outputFile) {
                $this->saveReport($result, $outputFile, $reportFormat, $suggestions);
            }

            // Handle strict mode
            if ($strict && ! $result->isValid) {
                $this->error('Validation failed with mismatches (strict mode)');

                return 1;
            }

            $this->info('Route validation completed successfully');

            return 0;

        } catch (Exception $e) {
            $this->error('Validation failed: ' . $e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Validate command inputs
     *
     * @return array{success: bool, message?: string}
     */
    private function validateInputs(string $specPath): array
    {
        if (! file_exists($specPath)) {
            return [
                'success' => false,
                'message' => "OpenAPI specification file not found: {$specPath}",
            ];
        }

        if (! is_readable($specPath)) {
            return [
                'success' => false,
                'message' => "OpenAPI specification file is not readable: {$specPath}",
            ];
        }

        return ['success' => true];
    }

    /**
     * Display validation results
     */
    private function displayResults(ValidationResult $result, string $format, bool $includeSuggestions): void
    {
        // Use Laravel's native table for table format in console
        if ($format === 'table') {
            $this->displayTableResults($result, $includeSuggestions);

            return;
        }

        // Use reporter factory for other formats
        if ($format !== 'console' || ReporterFactory::isSupported($format)) {
            try {
                $reporter = ReporterFactory::create($format);
                $reportOptions = [
                    'include_suggestions' => $includeSuggestions,
                    'use_colors' => true,
                ];

                $report = $reporter->generateReport($result, $reportOptions);
                $this->line($report);

                return;
            } catch (InvalidArgumentException) {
                $this->warn("Unsupported format '{$format}', falling back to console format");
            }
        }

        // Fallback to legacy console format
        $this->displayConsoleSummary($result);

        if (! $result->isValid) {
            $this->displayMismatches($result, $includeSuggestions);
        }

        if ($result->hasWarnings()) {
            $this->displayWarnings($result);
        }

        $this->displayStatistics($result);
    }

    /**
     * Display validation results using Laravel's table method
     */
    private function displayTableResults(ValidationResult $result, bool $includeSuggestions): void
    {
        $this->info('Route Validation Report');
        $this->line('Generated: ' . date('Y-m-d H:i:s'));
        $this->newLine();

        $tableData = $this->buildTableData($result);

        if ($tableData === []) {
            $this->warn('No routes or endpoints found to validate.');
        } else {
            $headers = ['Method', 'Path', 'Laravel Params', 'OpenAPI Params', 'Source', 'Status'];
            $this->table($headers, $tableData);
        }

        $this->newLine();
        $this->displayTableSummary($result);

        if ($includeSuggestions && ! $result->isValid) {
            $this->newLine();
            $this->displaySuggestions($result);
        }
    }

    /**
     * Build table data from validation result
     *
     * @return array<array<string>>
     */
    private function buildTableData(ValidationResult $result): array
    {
        // If we have all routes/endpoints data (no filter applied), show everything
        if ($result->allRoutes !== null && $result->allEndpoints !== null) {
            return $this->buildCompleteTableData($result);
        }

        // Fallback to mismatch-only data (filtered results)
        return $this->buildMismatchTableData($result);
    }

    /**
     * Build table data showing all routes and endpoints (when no filter is applied)
     *
     * @return array<array<string>>
     */
    private function buildCompleteTableData(ValidationResult $result): array
    {
        $rows = [];
        $processedSignatures = [];

        // Create mismatch lookup for quick status determination
        $mismatchMap = [];
        foreach ($result->mismatches as $mismatch) {
            if ($mismatch->type === RouteMismatch::TYPE_METHOD_MISMATCH) {
                $methods = array_unique(array_merge(
                    $mismatch->details['laravel_methods'] ?? [],
                    $mismatch->details['openapi_methods'] ?? []
                ));
                foreach ($methods as $m) {
                    $mismatchMap[strtoupper((string) $m) . ':' . $mismatch->path] = $mismatch;
                }

                continue;
            }
            $signature = $mismatch->method . ':' . $mismatch->path;
            $mismatchMap[$signature] = $mismatch;
        }

        // Process all routes
        if ($result->allRoutes !== null) {
            foreach ($result->allRoutes as $route) {
                foreach ($route->methods as $method) {
                    $method = strtoupper($method);
                    if ($method === 'HEAD') {
                        continue;
                    }

                    $signature = $method . ':' . $route->getNormalizedPath();
                    if (isset($processedSignatures[$signature])) {
                        continue;
                    }
                    $processedSignatures[$signature] = true;

                    $status = isset($mismatchMap[$signature])
                        ? $this->getStatusFromMismatch($mismatchMap[$signature])
                        : '✓ Match';

                    $rows[] = [
                        $method,
                        $route->getNormalizedPath(),
                        $this->formatParameters($route->pathParameters),
                        '', // Will be filled if matching endpoint exists
                        'Laravel',
                        $status,
                    ];
                }
            }
        }

        // Process all endpoints and update existing rows or add new ones
        if ($result->allEndpoints !== null) {
            foreach ($result->allEndpoints as $endpoint) {
                $signature = $endpoint->method . ':' . $endpoint->path;

                // Find existing row for this signature
                $rowIndex = null;
                foreach ($rows as $index => $row) {
                    if ($row[0] === $endpoint->method && $row[1] === $endpoint->path) {
                        $rowIndex = $index;
                        break;
                    }
                }

                if ($rowIndex !== null) {
                    // Update existing row with endpoint parameters and source
                    $rows[$rowIndex][3] = $this->formatParameters($this->extractPathParameters($endpoint->path));
                    $rows[$rowIndex][4] = 'Both';
                } else {
                    // Add new row for endpoint-only item
                    if (isset($processedSignatures[$signature])) {
                        continue;
                    }
                    $processedSignatures[$signature] = true;

                    $status = isset($mismatchMap[$signature])
                        ? $this->getStatusFromMismatch($mismatchMap[$signature])
                        : '✓ Match';

                    $rows[] = [
                        $endpoint->method,
                        $endpoint->path,
                        '', // No Laravel parameters
                        $this->formatParameters($this->extractPathParameters($endpoint->path)),
                        'OpenAPI',
                        $status,
                    ];
                }
            }
        }

        // Sort rows by method and path
        usort($rows, function (array $a, array $b): int {
            $pathCompare = strcmp($a[1], $b[1]);
            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            return strcmp($a[0], $b[0]);
        });

        return $rows;
    }

    /**
     * Build table data from mismatches only (filtered results)
     *
     * @return array<array<string>>
     */
    private function buildMismatchTableData(ValidationResult $result): array
    {
        $rows = [];
        $routeMap = $this->buildRouteMap($result);
        $endpointMap = $this->buildEndpointMap($result);

        // Collect all unique signatures
        $allSignatures = array_unique(array_merge(
            array_keys($routeMap),
            array_keys($endpointMap)
        ));

        sort($allSignatures);

        foreach ($allSignatures as $signature) {
            [$method, $path] = explode(':', $signature, 2);

            $route = $routeMap[$signature] ?? null;
            $endpoint = $endpointMap[$signature] ?? null;
            $status = $this->determineStatus($signature, $result);
            $source = $this->determineSource($route, $endpoint);

            $rows[] = [
                $method,
                $path,
                $this->formatParameters($route['pathParameters'] ?? []),
                $this->formatParameters($endpoint['pathParameters'] ?? []),
                $source,
                $status,
            ];
        }

        return $rows;
    }

    /**
     * Get status from mismatch object
     */
    private function getStatusFromMismatch(RouteMismatch $mismatch): string
    {
        return match ($mismatch->type) {
            RouteMismatch::TYPE_MISSING_DOCUMENTATION => '✗ Missing Doc',
            RouteMismatch::TYPE_MISSING_IMPLEMENTATION => '✗ Missing Impl',
            RouteMismatch::TYPE_METHOD_MISMATCH => '⚠ Method Mismatch',
            RouteMismatch::TYPE_PARAMETER_MISMATCH => '⚠ Param Mismatch',
            default => '⚠ Other Issue',
        };
    }

    /**
     * Build route map from validation result
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildRouteMap(ValidationResult $result): array
    {
        $routeMap = [];

        foreach ($result->mismatches as $mismatch) {
            $signature = $mismatch->method . ':' . $mismatch->path;

            if ($mismatch->type === RouteMismatch::TYPE_MISSING_DOCUMENTATION) {
                $routeMap[$signature] = [
                    'name' => $mismatch->details['route_name'] ?? '',
                    'pathParameters' => $this->extractPathParameters($mismatch->path),
                ];
            } elseif (in_array($mismatch->type, [RouteMismatch::TYPE_PARAMETER_MISMATCH, RouteMismatch::TYPE_METHOD_MISMATCH])) {
                $routeMap[$signature] = [
                    'name' => $mismatch->details['route_name'] ?? '',
                    'pathParameters' => $mismatch->details['route_parameters'] ?? [],
                ];
            }
        }

        return $routeMap;
    }

    /**
     * Build endpoint map from validation result
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildEndpointMap(ValidationResult $result): array
    {
        $endpointMap = [];

        foreach ($result->mismatches as $mismatch) {
            $signature = $mismatch->method . ':' . $mismatch->path;

            if ($mismatch->type === RouteMismatch::TYPE_MISSING_IMPLEMENTATION) {
                $endpointMap[$signature] = [
                    'pathParameters' => $this->extractPathParameters($mismatch->path),
                    'tags' => $mismatch->details['tags'] ?? [],
                ];
            } elseif (in_array($mismatch->type, [RouteMismatch::TYPE_PARAMETER_MISMATCH, RouteMismatch::TYPE_METHOD_MISMATCH])) {
                $endpointMap[$signature] = [
                    'pathParameters' => $mismatch->details['endpoint_parameters'] ?? $this->extractPathParameters($mismatch->path),
                    'tags' => $mismatch->details['tags'] ?? [],
                ];
            }
        }

        return $endpointMap;
    }

    /**
     * Determine status for a route/endpoint pair
     */
    private function determineStatus(string $signature, ValidationResult $result): string
    {
        foreach ($result->mismatches as $mismatch) {
            $mismatchSignature = $mismatch->method . ':' . $mismatch->path;

            if ($mismatchSignature === $signature) {
                return match ($mismatch->type) {
                    RouteMismatch::TYPE_MISSING_DOCUMENTATION => '✗ Missing Doc',
                    RouteMismatch::TYPE_MISSING_IMPLEMENTATION => '✗ Missing Impl',
                    RouteMismatch::TYPE_METHOD_MISMATCH => '⚠ Method Mismatch',
                    RouteMismatch::TYPE_PARAMETER_MISMATCH => '⚠ Param Mismatch',
                    default => '⚠ Other Issue',
                };
            }
        }

        return '✓ Match';
    }

    /**
     * Determine source of route/endpoint
     *
     * @param  array<string, mixed>|null  $route
     * @param  array<string, mixed>|null  $endpoint
     */
    private function determineSource(?array $route, ?array $endpoint): string
    {
        if ($route && $endpoint) {
            return 'Both';
        }
        if ($route) {
            return 'Laravel';
        }
        if ($endpoint) {
            return 'OpenAPI';
        }

        return 'Unknown';
    }

    /**
     * Format parameter array for display
     *
     * @param  array<string>  $parameters
     */
    private function formatParameters(array $parameters): string
    {
        if ($parameters === []) {
            return '[]';
        }

        return '[' . implode(', ', $parameters) . ']';
    }

    /**
     * Extract path parameters from a path string
     *
     * @return array<string>
     */
    private function extractPathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return $matches[1];
    }

    /**
     * Display table summary
     */
    private function displayTableSummary(ValidationResult $result): void
    {
        $stats = $result->statistics;
        $mismatchCount = $result->getMismatchCount();

        $this->info('SUMMARY');
        $this->line(str_repeat('-', 7));

        if (isset($stats['total_routes'], $stats['total_endpoints'])) {
            // Calculate detailed coverage statistics
            $totalRoutes = (int) $stats['total_routes'];
            $totalEndpoints = (int) $stats['total_endpoints'];
            $coveredRoutes = (int) ($stats['covered_routes'] ?? $totalRoutes);
            $coveredEndpoints = (int) ($stats['covered_endpoints'] ?? $totalEndpoints);

            $routeCoveragePercent = $totalRoutes > 0 ? round(($coveredRoutes / $totalRoutes) * 100, 1) : 100;
            $endpointCoveragePercent = $totalEndpoints > 0 ? round(($coveredEndpoints / $totalEndpoints) * 100, 1) : 100;

            $totalItems = $totalRoutes + $totalEndpoints;
            $totalCovered = $coveredRoutes + $coveredEndpoints;
            $overallCoveragePercent = $totalItems > 0 ? round(($totalCovered / $totalItems) * 100, 1) : 100;

            // Display route statistics
            $this->line("Laravel Routes: {$totalRoutes} total, {$coveredRoutes} covered ({$routeCoveragePercent}%)");
            $this->line("OpenAPI Endpoints: {$totalEndpoints} total, {$coveredEndpoints} covered ({$endpointCoveragePercent}%)");
            $this->line("Overall Coverage: {$totalCovered}/{$totalItems} ({$overallCoveragePercent}%)");
            $this->line("Total Issues: {$mismatchCount}");

            $this->newLine();
            if ($result->isValid) {
                $this->info('✓ All routes and endpoints are properly aligned');
            } else {
                $this->error("✗ Found {$mismatchCount} mismatch(es)");
            }
        }

        if (isset($stats['mismatch_breakdown']) && is_array($stats['mismatch_breakdown'])) {
            $this->newLine();
            $this->line('Issue breakdown:');
            foreach ($stats['mismatch_breakdown'] as $type => $count) {
                $displayType = match ($type) {
                    RouteMismatch::TYPE_MISSING_DOCUMENTATION => 'Missing documentation',
                    RouteMismatch::TYPE_MISSING_IMPLEMENTATION => 'Missing implementation',
                    RouteMismatch::TYPE_METHOD_MISMATCH => 'Method mismatches',
                    RouteMismatch::TYPE_PARAMETER_MISMATCH => 'Parameter mismatches',
                    default => $type,
                };
                $this->line("  {$displayType}: {$count}");
            }
        }
    }

    /**
     * Display suggestions for mismatches
     */
    private function displaySuggestions(ValidationResult $result): void
    {
        $this->info('SUGGESTIONS');
        $this->line(str_repeat('-', 11));

        foreach ($result->mismatches as $mismatch) {
            if ($mismatch->suggestions !== []) {
                $this->line('');
                $this->warn("• {$mismatch->message}");
                foreach ($mismatch->suggestions as $suggestion) {
                    $this->line("  - {$suggestion}");
                }
            }
        }
    }

    /**
     * Display console summary
     */
    private function displayConsoleSummary(ValidationResult $result): void
    {
        $this->line('');
        $this->info('=== Route Validation Summary ===');

        if ($result->isValid) {
            $this->info('✓ All routes and endpoints are properly aligned');
        } else {
            $this->error('✗ Found ' . $result->getMismatchCount() . ' mismatch(es)');
        }
    }

    /**
     * Display mismatches in console format
     */
    private function displayMismatches(ValidationResult $result, bool $includeSuggestions): void
    {
        $this->line('');
        $this->info('=== Mismatches ===');

        foreach ($result->mismatches as $mismatch) {
            $this->line('');
            $this->error("• {$mismatch->message}");
            $this->line("  Path: {$mismatch->path}");
            $this->line("  Method: {$mismatch->method}");
            $this->line("  Type: {$mismatch->type}");

            if ($mismatch->details !== []) {
                $this->line('  Details: ' . json_encode($mismatch->details, JSON_PRETTY_PRINT));
            }

            if ($includeSuggestions && $mismatch->suggestions !== []) {
                $this->line('  Suggestions:');
                foreach ($mismatch->suggestions as $suggestion) {
                    $this->line("    - {$suggestion}");
                }
            }
        }
    }

    /**
     * Display warnings
     */
    private function displayWarnings(ValidationResult $result): void
    {
        $this->line('');
        $this->info('=== Warnings ===');
        foreach ($result->warnings as $warning) {
            $this->warn("• {$warning}");
        }
    }

    /**
     * Display statistics
     */
    private function displayStatistics(ValidationResult $result): void
    {
        $this->line('');
        $this->info('=== Statistics ===');

        $stats = $result->statistics;

        if (isset($stats['total_routes'], $stats['covered_routes'], $stats['route_coverage_percentage'])) {
            $this->line("Total Laravel routes: {$stats['total_routes']} ({$stats['covered_routes']} covered, {$stats['route_coverage_percentage']}%)");
        }

        if (isset($stats['total_endpoints'], $stats['covered_endpoints'], $stats['endpoint_coverage_percentage'])) {
            $this->line("Total OpenAPI endpoints: {$stats['total_endpoints']} ({$stats['covered_endpoints']} covered, {$stats['endpoint_coverage_percentage']}%)");
        }

        if (isset($stats['total_coverage_percentage'])) {
            $this->line("Total coverage: {$stats['total_coverage_percentage']}%");
        }

        if (isset($stats['mismatch_breakdown']) && ! empty($stats['mismatch_breakdown'])) {
            $this->line('Mismatch breakdown:');
            foreach ($stats['mismatch_breakdown'] as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
        }
    }

    /**
     * Save report to file
     */
    private function saveReport(ValidationResult $result, string $filename, string $format, bool $includeSuggestions): void
    {
        try {
            // For file output, always use the reporter factory (including TableReporter for table format)
            $reporter = ReporterFactory::create($format);
            $reportOptions = [
                'include_suggestions' => $includeSuggestions,
                'use_colors' => false, // Disable colors for file output
            ];

            $content = $reporter->generateReport($result, $reportOptions);

            // Add appropriate file extension if not provided
            if (pathinfo($filename, PATHINFO_EXTENSION) === '' || pathinfo($filename, PATHINFO_EXTENSION) === '0') {
                $extension = $reporter->getFileExtension();
                $filename .= ".{$extension}";
            }
        } catch (InvalidArgumentException) {
            // Fall back to legacy text format
            $content = $this->generateTextReport($result);
            if (pathinfo($filename, PATHINFO_EXTENSION) === '' || pathinfo($filename, PATHINFO_EXTENSION) === '0') {
                $filename .= '.txt';
            }
        }

        file_put_contents($filename, $content);
        $this->info("Report saved to: {$filename}");
    }

    /**
     * Generate text report
     */
    private function generateTextReport(ValidationResult $result): string
    {
        $report = "Route Validation Report\n";
        $report .= 'Generated: ' . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";

        $report .= "Summary:\n";
        $report .= "--------\n";
        $report .= $result->isValid ? "Status: PASSED\n" : "Status: FAILED\n";
        $report .= 'Total mismatches: ' . $result->getMismatchCount() . "\n\n";

        if (! $result->isValid) {
            $report .= "Mismatches:\n";
            $report .= "-----------\n";
            foreach ($result->mismatches as $mismatch) {
                $report .= "• {$mismatch->message}\n";
                $report .= "  Path: {$mismatch->path}\n";
                $report .= "  Method: {$mismatch->method}\n";
                $report .= "  Type: {$mismatch->type}\n\n";
            }
        }

        if ($result->statistics !== []) {
            $report .= "Statistics:\n";
            $report .= "-----------\n";
            foreach ($result->statistics as $key => $value) {
                $report .= "{$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }

        return $report;
    }

    /**
     * Normalize filter types from user input to internal constants
     *
     * Accepts both dash-separated (missing-documentation) and underscore-separated (missing_documentation) formats
     *
     * @param  array<string>  $filterTypes
     * @return array<string>
     */
    private function normalizeFilterTypes(array $filterTypes): array
    {
        // Map both dash and underscore formats to internal constants
        $typeMap = [
            // Dash format (user-facing)
            'missing-documentation' => RouteMismatch::TYPE_MISSING_DOCUMENTATION,
            'missing-implementation' => RouteMismatch::TYPE_MISSING_IMPLEMENTATION,
            'method-mismatch' => RouteMismatch::TYPE_METHOD_MISMATCH,
            'parameter-mismatch' => RouteMismatch::TYPE_PARAMETER_MISMATCH,
            'path-mismatch' => RouteMismatch::TYPE_PATH_MISMATCH,
            'validation-error' => RouteMismatch::TYPE_VALIDATION_ERROR,
            // Underscore format (internal constants, also accept as input)
            'missing_documentation' => RouteMismatch::TYPE_MISSING_DOCUMENTATION,
            'missing_implementation' => RouteMismatch::TYPE_MISSING_IMPLEMENTATION,
            'method_mismatch' => RouteMismatch::TYPE_METHOD_MISMATCH,
            'parameter_mismatch' => RouteMismatch::TYPE_PARAMETER_MISMATCH,
            'path_mismatch' => RouteMismatch::TYPE_PATH_MISMATCH,
            'validation_error' => RouteMismatch::TYPE_VALIDATION_ERROR,
        ];

        $normalized = [];
        $validDashTypes = ['missing-documentation', 'missing-implementation', 'method-mismatch', 'parameter-mismatch', 'path-mismatch', 'validation-error'];

        foreach ($filterTypes as $type) {
            $normalizedType = strtolower(trim($type));

            if (isset($typeMap[$normalizedType])) {
                $normalized[] = $typeMap[$normalizedType];
            } elseif (in_array($type, $typeMap)) {
                // Allow direct usage of internal constants (already normalized)
                $normalized[] = $type;
            } else {
                $this->warn("Invalid filter type: {$type}. Valid types: " . implode(', ', $validDashTypes));
            }
        }

        return array_unique($normalized);
    }
}
