<?php

namespace Maan511\OpenapiToLaravel\Validation\Reporters;

use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;

/**
 * Console-formatted validation reporter
 */
class ConsoleReporter implements ReporterInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generateReport(ValidationResult $result, array $options = []): string
    {
        $includeSuggestions = $options['include_suggestions'] ?? false;
        $useColors = $options['use_colors'] ?? false;

        $output = [];
        $output[] = $this->generateHeader($useColors);
        $output[] = $this->generateSummary($result, $useColors);

        if (! $result->isValid) {
            $output[] = $this->generateMismatches($result, $includeSuggestions, $useColors);
        }

        if ($result->hasWarnings()) {
            $output[] = $this->generateWarnings($result, $useColors);
        }

        $output[] = $this->generateStatistics($result);

        return implode("\n\n", array_filter($output));
    }

    public function getFileExtension(): string
    {
        return 'txt';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }

    public function supports(string $format): bool
    {
        return in_array(strtolower($format), ['console', 'text', 'txt']);
    }

    /**
     * Generate report header
     */
    private function generateHeader(bool $useColors): string
    {
        $title = 'Route Validation Report';
        $timestamp = 'Generated: ' . date('Y-m-d H:i:s');

        $separator = str_repeat('=', max(strlen($title), strlen($timestamp)));

        if ($useColors) {
            $title = "\033[1;34m{$title}\033[0m"; // Bold blue
            $separator = "\033[36m{$separator}\033[0m"; // Cyan
        }

        return "{$separator}\n{$title}\n{$timestamp}\n{$separator}";
    }

    /**
     * Generate summary section
     */
    private function generateSummary(ValidationResult $result, bool $useColors): string
    {
        $status = $result->isValid ? 'PASSED' : 'FAILED';
        $mismatchCount = $result->getMismatchCount();

        if ($useColors) {
            $status = $result->isValid
                ? "\033[32m{$status}\033[0m"  // Green
                : "\033[31m{$status}\033[0m"; // Red
        }

        $lines = [
            'VALIDATION SUMMARY',
            str_repeat('-', 18),
            "Status: {$status}",
            "Total mismatches: {$mismatchCount}",
        ];

        if ($result->hasWarnings()) {
            $lines[] = 'Warnings: ' . count($result->warnings);
        }

        return implode("\n", $lines);
    }

    /**
     * Generate mismatches section
     */
    private function generateMismatches(ValidationResult $result, bool $includeSuggestions, bool $useColors): string
    {
        $lines = [
            'MISMATCHES',
            str_repeat('-', 10),
        ];

        $groupedMismatches = $this->groupMismatchesByType($result->mismatches);

        foreach ($groupedMismatches as $type => $mismatches) {
            $lines[] = '';
            $typeTitle = strtoupper(str_replace('_', ' ', $type)) . ' (' . count($mismatches) . ')';

            if ($useColors) {
                $typeTitle = "\033[1;33m{$typeTitle}\033[0m"; // Bold yellow
            }

            $lines[] = $typeTitle;
            $lines[] = str_repeat('-', strlen($typeTitle));

            foreach ($mismatches as $mismatch) {
                $lines[] = $this->formatMismatch($mismatch, $includeSuggestions, $useColors);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single mismatch
     */
    private function formatMismatch(RouteMismatch $mismatch, bool $includeSuggestions, bool $useColors): string
    {
        $icon = match ($mismatch->severity) {
            'error' => '✗',
            'warning' => '⚠',
            'info' => 'ℹ',
            default => '•',
        };

        if ($useColors) {
            $icon = match ($mismatch->severity) {
                'error' => "\033[31m{$icon}\033[0m",   // Red
                'warning' => "\033[33m{$icon}\033[0m", // Yellow
                'info' => "\033[36m{$icon}\033[0m",    // Cyan
                default => $icon,
            };
        }

        $lines = [
            "{$icon} {$mismatch->message}",
            "   Path: {$mismatch->path}",
            "   Method: {$mismatch->method}",
        ];

        if ($mismatch->details !== []) {
            $lines[] = '   Details: ' . $this->formatDetails($mismatch->details);
        }

        if ($includeSuggestions && $mismatch->suggestions !== []) {
            $lines[] = '   Suggestions:';
            foreach ($mismatch->suggestions as $suggestion) {
                $lines[] = "     • {$suggestion}";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Generate warnings section
     */
    private function generateWarnings(ValidationResult $result, bool $useColors): string
    {
        $lines = [
            'WARNINGS',
            str_repeat('-', 8),
        ];

        foreach ($result->warnings as $warning) {
            $icon = $useColors ? "\033[33m⚠\033[0m" : '⚠';
            $lines[] = "{$icon} {$warning}";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate statistics section
     */
    private function generateStatistics(ValidationResult $result): string
    {
        $lines = [
            'STATISTICS',
            str_repeat('-', 10),
        ];

        $stats = $result->statistics;

        foreach ($stats as $key => $value) {
            if ($key === 'mismatch_breakdown' && is_array($value)) {
                $lines[] = 'Mismatch breakdown:';
                foreach ($value as $type => $count) {
                    $lines[] = "  {$type}: {$count}";
                }
            } else {
                $displayKey = ucfirst(str_replace('_', ' ', $key));
                $displayValue = is_array($value) ? json_encode($value) : $value;
                $lines[] = "{$displayKey}: {$displayValue}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Group mismatches by type
     *
     * @param  array<RouteMismatch>  $mismatches
     * @return array<string, array<RouteMismatch>>
     */
    private function groupMismatchesByType(array $mismatches): array
    {
        $grouped = [];
        foreach ($mismatches as $mismatch) {
            $grouped[$mismatch->type][] = $mismatch;
        }

        // Sort by severity (errors first)
        uasort($grouped, function ($a, $b): int {
            $severityA = max(array_map(fn ($m) => $m->getSeverityLevel(), $a));
            $severityB = max(array_map(fn ($m) => $m->getSeverityLevel(), $b));

            return $severityB <=> $severityA;
        });

        return $grouped;
    }

    /**
     * Format mismatch details
     *
     * @param  array<string, mixed>  $details
     */
    private function formatDetails(array $details): string
    {
        $formatted = [];
        foreach ($details as $key => $value) {
            $displayValue = is_array($value) ? implode(', ', $value) : $value;
            $formatted[] = "{$key}: {$displayValue}";
        }

        return implode(', ', $formatted);
    }
}
