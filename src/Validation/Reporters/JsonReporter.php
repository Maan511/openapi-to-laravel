<?php

namespace Maan511\OpenapiToLaravel\Validation\Reporters;

use Maan511\OpenapiToLaravel\Models\ValidationResult;
use RuntimeException;

/**
 * JSON-formatted validation reporter
 */
class JsonReporter implements ReporterInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generateReport(ValidationResult $result, array $options = []): string
    {
        $prettyPrint = $options['pretty_print'] ?? true;
        $includeMetadata = $options['include_metadata'] ?? true;

        $data = $this->prepareData($result, $includeMetadata);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);

        if ($json === false) {
            throw new RuntimeException('Failed to encode validation result as JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    public function getFileExtension(): string
    {
        return 'json';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function supports(string $format): bool
    {
        return strtolower($format) === 'json';
    }

    /**
     * Prepare data for JSON serialization
     *
     * @return array<string, mixed>
     */
    private function prepareData(ValidationResult $result, bool $includeMetadata): array
    {
        // Sort mismatches alphabetically by path then method
        $sortedMismatches = $result->mismatches;
        usort($sortedMismatches, function (\Maan511\OpenapiToLaravel\Models\RouteMismatch $a, \Maan511\OpenapiToLaravel\Models\RouteMismatch $b): int {
            $pathCompare = strcmp($a->path, $b->path);
            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            return strcmp($a->method, $b->method);
        });

        $data = [
            'validation' => [
                'status' => $result->isValid ? 'passed' : 'failed',
                'summary' => $result->getSummary(),
            ],
            'mismatches' => array_map(fn (\Maan511\OpenapiToLaravel\Models\RouteMismatch $m): array => $m->toArray(), $sortedMismatches),
            'warnings' => $result->warnings,
            'statistics' => $result->statistics,
        ];

        if ($includeMetadata) {
            $data['metadata'] = [
                'generated_at' => date('c'), // ISO 8601 format
                'generator' => 'OpenAPI to Laravel Route Validator',
                'version' => $this->getPackageVersion(),
            ];
        }

        return $data;
    }

    /**
     * Get package version (if available)
     */
    private function getPackageVersion(): string
    {
        // Try to get version from composer.json or return default
        $composerFile = __DIR__ . '/../../../composer.json';
        if (file_exists($composerFile)) {
            $composerContent = file_get_contents($composerFile);
            if ($composerContent !== false) {
                $composer = json_decode($composerContent, true);
                if (is_array($composer)) {
                    return $composer['version'] ?? 'dev';
                }
            }
        }

        return 'unknown';
    }
}
