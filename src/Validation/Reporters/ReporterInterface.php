<?php

namespace Maan511\OpenapiToLaravel\Validation\Reporters;

use Maan511\OpenapiToLaravel\Models\ValidationResult;

/**
 * Interface for validation reporters
 */
interface ReporterInterface
{
    /**
     * Generate a report from validation results
     *
     * @param  array<string, mixed>  $options
     */
    public function generateReport(ValidationResult $result, array $options = []): string;

    /**
     * Get the file extension for this report format
     */
    public function getFileExtension(): string;

    /**
     * Get the MIME type for this report format
     */
    public function getMimeType(): string;

    /**
     * Check if this reporter supports the given format
     */
    public function supports(string $format): bool;
}
