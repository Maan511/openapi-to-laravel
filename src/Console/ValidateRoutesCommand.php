<?php

namespace Maan511\OpenapiToLaravel\Console;

use function app;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use Maan511\OpenapiToLaravel\Validation\LaravelRouteCollector;
use Maan511\OpenapiToLaravel\Validation\RouteComparator;
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
                            {--include-pattern=* : Route URI patterns to include (supports wildcards)}
                            {--exclude-middleware=* : Middleware groups to exclude}
                            {--ignore-route=* : Route names/patterns to ignore}
                            {--report-format=console : Report format (console, json, html)}
                            {--output-file= : Save report to file}
                            {--strict : Fail command on any mismatches}
                            {--suggestions : Include fix suggestions in output}';

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

        /** @var array<string> $includePatterns */
        $includePatterns = is_array($this->option('include-pattern')) ? $this->option('include-pattern') : [];
        /** @var array<string> $excludeMiddleware */
        $excludeMiddleware = is_array($this->option('exclude-middleware')) ? $this->option('exclude-middleware') : [];
        /** @var array<string> $ignoreRoutes */
        $ignoreRoutes = is_array($this->option('ignore-route')) ? $this->option('ignore-route') : [];
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

            if (! empty($includePatterns)) {
                $this->info('Include patterns: ' . implode(', ', $includePatterns));
            }

            if (! empty($excludeMiddleware)) {
                $this->info('Exclude middleware: ' . implode(', ', $excludeMiddleware));
            }

            // Validate inputs
            $validationResult = $this->validateInputs($specPath);
            if (! $validationResult['success']) {
                $this->error($validationResult['message'] ?? 'Validation failed');

                return 1;
            }

            // Prepare validation options
            $options = [
                'include_patterns' => $includePatterns,
                'exclude_middleware' => $excludeMiddleware,
                'ignore_routes' => $ignoreRoutes,
            ];

            // Perform validation
            $result = $validator->validate($specPath, $options);

            // Display results based on format
            $this->displayResults($result, $reportFormat, $suggestions);

            // Save to file if requested
            if ($outputFile) {
                $this->saveReport($result, $outputFile, $reportFormat);
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
        if ($format === 'json') {
            $jsonOutput = json_encode($result->toArray(), JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return;
        }

        // Console format
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

        if (isset($stats['total_routes'])) {
            $this->line("Total Laravel routes: {$stats['total_routes']}");
        }

        if (isset($stats['total_endpoints'])) {
            $this->line("Total OpenAPI endpoints: {$stats['total_endpoints']}");
        }

        if (isset($stats['coverage_percentage'])) {
            $this->line("Coverage: {$stats['coverage_percentage']}%");
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
    private function saveReport(ValidationResult $result, string $filename, string $format): void
    {
        $content = match ($format) {
            'json' => json_encode($result->toArray(), JSON_PRETTY_PRINT),
            'console' => $this->generateTextReport($result),
            default => $this->generateTextReport($result),
        };

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
}
