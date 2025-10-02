<?php

namespace Maan511\OpenapiToLaravel\Models;

/**
 * Contains validation outcomes and detailed findings
 */
class ValidationResult
{
    /**
     * @param  array<RouteMismatch>  $mismatches
     * @param  array<string>  $warnings
     * @param  array<string, mixed>  $statistics
     * @param  array<\Maan511\OpenapiToLaravel\Models\LaravelRoute>|null  $allRoutes  All routes when no filter is applied
     * @param  array<\Maan511\OpenapiToLaravel\Models\EndpointDefinition>|null  $allEndpoints  All endpoints when no filter is applied
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $mismatches = [],
        public readonly array $warnings = [],
        public readonly array $statistics = [],
        public readonly ?array $allRoutes = null,
        public readonly ?array $allEndpoints = null
    ) {}

    /**
     * Create a successful validation result
     *
     * @param  array<string, mixed>  $statistics
     * @param  array<\Maan511\OpenapiToLaravel\Models\LaravelRoute>|null  $allRoutes
     * @param  array<\Maan511\OpenapiToLaravel\Models\EndpointDefinition>|null  $allEndpoints
     */
    public static function success(array $statistics = [], ?array $allRoutes = null, ?array $allEndpoints = null): self
    {
        return new self(
            isValid: true,
            statistics: $statistics,
            allRoutes: $allRoutes,
            allEndpoints: $allEndpoints
        );
    }

    /**
     * Create a failed validation result
     *
     * @param  array<RouteMismatch>  $mismatches
     * @param  array<string>  $warnings
     * @param  array<string, mixed>  $statistics
     * @param  array<\Maan511\OpenapiToLaravel\Models\LaravelRoute>|null  $allRoutes
     * @param  array<\Maan511\OpenapiToLaravel\Models\EndpointDefinition>|null  $allEndpoints
     */
    public static function failed(array $mismatches, array $warnings = [], array $statistics = [], ?array $allRoutes = null, ?array $allEndpoints = null): self
    {
        return new self(
            isValid: false,
            mismatches: $mismatches,
            warnings: $warnings,
            statistics: $statistics,
            allRoutes: $allRoutes,
            allEndpoints: $allEndpoints
        );
    }

    /**
     * Get total number of mismatches
     */
    public function getMismatchCount(): int
    {
        return count($this->mismatches);
    }

    /**
     * Get mismatches by type
     *
     * @return array<RouteMismatch>
     */
    public function getMismatchesByType(string $type): array
    {
        return array_filter(
            $this->mismatches,
            fn (RouteMismatch $mismatch): bool => $mismatch->type === $type
        );
    }

    /**
     * Get all mismatch types
     *
     * @return array<string>
     */
    public function getMismatchTypes(): array
    {
        return array_unique(array_map(
            fn (RouteMismatch $mismatch): string => $mismatch->type,
            $this->mismatches
        ));
    }

    /**
     * Check if validation has warnings
     */
    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /**
     * Get validation summary
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $mismatchCounts = [];
        foreach ($this->getMismatchTypes() as $type) {
            $mismatchCounts[$type] = count($this->getMismatchesByType($type));
        }

        return [
            'isValid' => $this->isValid,
            'totalMismatches' => $this->getMismatchCount(),
            'mismatchTypes' => $mismatchCounts,
            'warningCount' => count($this->warnings),
            'statistics' => $this->statistics,
        ];
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'mismatches' => array_map(fn (RouteMismatch $m): array => $m->toArray(), $this->mismatches),
            'warnings' => $this->warnings,
            'statistics' => $this->statistics,
            'summary' => $this->getSummary(),
        ];
    }

    /**
     * Merge with another validation result
     */
    public function merge(ValidationResult $other): self
    {
        return new self(
            isValid: $this->isValid && $other->isValid,
            mismatches: array_merge($this->mismatches, $other->mismatches),
            warnings: array_merge($this->warnings, $other->warnings),
            statistics: array_merge($this->statistics, $other->statistics),
            allRoutes: $this->allRoutes ?? $other->allRoutes,
            allEndpoints: $this->allEndpoints ?? $other->allEndpoints
        );
    }
}
