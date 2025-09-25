<?php

namespace Maan511\OpenapiToLaravel\Console;

use Exception;
use Illuminate\Console\Command;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;

/**
 * Console command for generating Laravel FormRequest classes from OpenAPI specifications
 */
class GenerateFormRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'openapi-to-laravel:make-requests
                            {spec : Path to OpenAPI specification file}
                            {--output=./app/Http/Requests : Output directory for generated FormRequest classes}
                            {--namespace=App\\Http\\Requests : PHP namespace for generated classes}
                            {--force : Overwrite existing FormRequest files}
                            {--dry-run : Show what would be generated without creating files}';

    /**
     * The console command description.
     */
    protected $description = 'Generate Laravel FormRequest classes from OpenAPI specification';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specPathValue = $this->argument('spec');
        $outputDirValue = $this->option('output');
        $namespaceValue = $this->option('namespace');

        $specPath = is_string($specPathValue) ? $specPathValue : '';
        $outputDir = is_string($outputDirValue) ? $outputDirValue : './app/Http/Requests';
        $namespace = is_string($namespaceValue) ? $namespaceValue : 'App\\Http\\Requests';
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $verbose = $this->getOutput()->isVerbose();

        try {
            // Initialize services
            $referenceResolver = new ReferenceResolver;
            $schemaExtractor = new SchemaExtractor($referenceResolver);
            $parser = new OpenApiParser($schemaExtractor);
            $ruleMapper = new ValidationRuleMapper;
            $generator = new FormRequestGenerator($ruleMapper);

            if ($verbose) {
                $this->info('Starting generation process...');
                $this->info("Spec file: {$specPath}");
                $this->info("Output directory: {$outputDir}");
                $this->info("Namespace: {$namespace}");
            }

            // Validate inputs
            $validationResult = $this->validateInputs($specPath, $outputDir, $namespace, $dryRun);
            if (! $validationResult['success']) {
                $this->error($validationResult['message'] ?? 'Validation failed');

                return 1;
            }

            // Parse OpenAPI specification
            if ($verbose) {
                $this->info('Parsing OpenAPI specification...');
            }

            $specification = $parser->parseFromFile($specPath);

            // Validate specification
            $specValidation = $parser->validateSpecification($specification);
            if (! $specValidation['valid']) {
                $this->error('Invalid OpenAPI specification:');
                foreach ($specValidation['errors'] as $error) {
                    $this->error("  - {$error}");
                }

                return 1;
            }

            // Show warnings if any
            foreach ($specValidation['warnings'] as $warning) {
                $this->warn($warning);
            }

            // Get endpoints with request bodies
            $endpoints = $parser->getEndpointsWithRequestBodies($specification);

            if (empty($endpoints)) {
                $this->warn('No endpoints with request bodies found. No FormRequests will be generated.');

                return 0;
            }

            if ($verbose) {
                $this->info('Found ' . count($endpoints) . ' endpoints with request bodies');
            }

            // Generate FormRequest classes
            if ($verbose) {
                $this->info('Generating FormRequest classes...');
            }

            $formRequests = $generator->generateFromEndpoints($endpoints, $namespace, $outputDir);

            // Validate generated classes
            $validation = $generator->validate($formRequests);
            if (! $validation['valid']) {
                $this->error('Validation failed:');
                foreach ($validation['errors'] as $error) {
                    $this->error("  - {$error}");
                }

                return 1;
            }

            // Show validation warnings
            foreach ($validation['warnings'] as $warning) {
                $this->warn($warning);
            }

            // Dry run mode
            if ($dryRun) {
                return $this->handleDryRun($formRequests, $generator);
            }

            // Generate files
            if ($verbose) {
                $this->info('Writing FormRequest files...');
            }

            $results = $generator->generateAndWriteMultiple($formRequests, $force);

            // Display results
            $this->displayResults($results, $verbose);

            // Show statistics
            if ($verbose) {
                $stats = $generator->getStats($formRequests);
                $this->displayStats($stats);
            }

            return $results['summary']['failed'] > 0 ? 1 : 0;

        } catch (Exception $e) {
            $this->error('Generation failed: ' . $e->getMessage());
            if ($verbose) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Validate command inputs
     */
    /**
     * @return array{success: bool, message?: string}
     */
    private function validateInputs(string $specPath, string $outputDir, string $namespace, bool $dryRun = false): array
    {
        // Check spec file exists
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

        // Validate namespace format
        if (! preg_match('/^[A-Z][a-zA-Z0-9_\\\\]*[a-zA-Z0-9]$/', $namespace)) {
            return [
                'success' => false,
                'message' => "Invalid namespace format: {$namespace}",
            ];
        }

        // Skip directory validation and creation in dry-run mode
        if ($dryRun) {
            return ['success' => true];
        }

        // Check if output directory is writable (create if needed)
        if (! is_dir($outputDir)) {
            if (! mkdir($outputDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => "Cannot create output directory: {$outputDir}",
                ];
            }
        }

        if (! is_writable($outputDir)) {
            return [
                'success' => false,
                'message' => "Output directory is not writable: {$outputDir}",
            ];
        }

        return ['success' => true];
    }

    /**
     * Handle dry run mode
     */
    /**
     * @param  array<FormRequestClass>  $formRequests
     */
    private function handleDryRun(array $formRequests, FormRequestGenerator $generator): int
    {
        $this->info('Dry run mode - showing what would be generated:');
        $this->line('');

        $dryRunResults = $generator->dryRun($formRequests);

        $headers = ['Class Name', 'File Path', 'Source Endpoint', 'Rules', 'Exists', 'Size (bytes)'];
        $rows = [];

        foreach ($dryRunResults as $result) {
            $rows[] = [
                $result['className'],
                $result['filePath'],
                $result['sourceEndpoint'],
                $result['rulesCount'],
                $result['fileExists'] ? 'Yes' : 'No',
                number_format($result['estimatedSize']),
            ];
        }

        $this->table($headers, $rows);

        $this->info("\nSummary:");
        $this->info('Total classes: ' . count($dryRunResults));
        $this->info('Existing files: ' . count(array_filter($dryRunResults, fn ($r) => $r['fileExists'])));
        $this->info('Total estimated size: ' . number_format(array_sum(array_column($dryRunResults, 'estimatedSize'))) . ' bytes');

        return 0;
    }

    /**
     * Display generation results
     */
    /**
     * @param  array{summary: array{total: int, success: int, skipped: int, failed: int}, results: array<array{success: bool, message: string, filePath: string, className: string}>}  $results
     */
    private function displayResults(array $results, bool $verbose): void
    {
        $summary = $results['summary'];

        $this->info("\nGeneration Summary:");
        $this->info("Total: {$summary['total']}");
        $this->info("Success: {$summary['success']}");
        $this->info("Skipped: {$summary['skipped']}");
        $this->info("Failed: {$summary['failed']}");

        if ($verbose && ! empty($results['results'])) {
            $this->line('');
            $this->info('Detailed Results:');

            foreach ($results['results'] as $result) {
                $status = $result['success'] ? '✓' : '✗';
                $this->line("{$status} {$result['className']}: {$result['message']}");
            }
        }

        if ($summary['failed'] > 0) {
            $this->line('');
            $this->error('Some files failed to generate. Check the detailed results above.');
        } elseif ($summary['success'] > 0) {
            $this->line('');
            $this->info("✓ Successfully generated {$summary['success']} FormRequest classes");
        }
    }

    /**
     * Display generation statistics
     *
     * @param  array{totalClasses: int, totalRules: int, averageComplexity: float, estimatedTotalSize: int, namespaces: array<string>, mostComplex: array{className: string, complexity: int}|null}  $stats
     */
    private function displayStats(array $stats): void
    {
        $this->line('');
        $this->info('Statistics:');
        $this->info("Total classes: {$stats['totalClasses']}");
        $this->info("Total validation rules: {$stats['totalRules']}");
        $this->info('Average complexity: ' . round($stats['averageComplexity'], 2));
        $this->info('Estimated total size: ' . number_format($stats['estimatedTotalSize']) . ' bytes');
        $this->info('Namespaces used: ' . count($stats['namespaces']));

        if (isset($stats['mostComplex'])) {
            $this->info("Most complex class: {$stats['mostComplex']['className']} (score: {$stats['mostComplex']['complexity']})");
        }
    }
}
