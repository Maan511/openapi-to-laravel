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
            $headers = ['Method', 'Path', 'Laravel', 'OpenAPI', 'Status'];
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
        $rows = [];

        foreach ($result->matches as $match) {
            $rows[] = [
                $match->method,
                $match->path,
                $match->route ? '✓' : '',
                $match->endpoint ? '✓' : '',
                $match->getDisplayStatus(),
            ];
        }

        return $rows;
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
            // For file output, always use the reporter factory
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
