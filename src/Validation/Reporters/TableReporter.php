<?php

namespace Maan511\OpenapiToLaravel\Validation\Reporters;

use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;

/**
 * Table-formatted validation reporter
 */
class TableReporter implements ReporterInterface
{
    private const int MAX_CELL_WIDTH = 30;

    private const int MIN_CELL_WIDTH = 8;

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateReport(ValidationResult $result, array $options = []): string
    {
        $showDetails = $options['show_details'] ?? false;
        $maxWidth = $options['max_width'] ?? 120;

        $rows = $this->buildTableRows($result);

        if ($rows === []) {
            return $this->generateEmptyReport($result);
        }

        $headers = $this->getTableHeaders($showDetails);
        $columnWidths = $this->calculateColumnWidths($headers, $rows, $maxWidth);

        $output = [];
        $output[] = $this->generateHeader();
        $output[] = $this->renderTable($headers, $rows, $columnWidths);
        $output[] = $this->generateSummary($result);

        return implode("\n\n", $output);
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
        return in_array(strtolower($format), ['table', 'tbl']);
    }

    /**
     * Build table rows from validation result
     *
     * @return array<array<string, string>>
     */
    private function buildTableRows(ValidationResult $result): array
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

            $rows[] = [
                'method' => $method,
                'path' => $path,
                'laravel_params' => $this->formatParameters($route['pathParameters'] ?? []),
                'openapi_params' => $this->formatParameters($endpoint['pathParameters'] ?? []),
                'status' => $status,
                'route_name' => $route['name'] ?? '-',
                'tags' => isset($endpoint['tags']) ? implode(', ', $endpoint['tags']) : '-',
            ];
        }

        return $rows;
    }

    /**
     * Get table headers
     *
     * @return array<string>
     */
    private function getTableHeaders(bool $showDetails): array
    {
        $headers = ['Method', 'Path', 'Laravel Params', 'OpenAPI Params', 'Status'];

        if ($showDetails) {
            $headers[] = 'Route Name';
            $headers[] = 'Tags';
        }

        return $headers;
    }

    /**
     * Build route map from validation result
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildRouteMap(ValidationResult $result): array
    {
        $routeMap = [];

        // Extract routes from mismatches that have route data
        foreach ($result->mismatches as $mismatch) {
            $signature = $mismatch->method . ':' . $mismatch->path;

            if ($mismatch->type === RouteMismatch::TYPE_MISSING_DOCUMENTATION) {
                $routeMap[$signature] = [
                    'name' => $mismatch->details['route_name'] ?? '',
                    'action' => $mismatch->details['action'] ?? '',
                    'middleware' => $mismatch->details['middleware'] ?? [],
                    'pathParameters' => $mismatch->details['path_parameters'] ?? [],
                ];
            } elseif (in_array($mismatch->type, [
                RouteMismatch::TYPE_PARAMETER_MISMATCH,
                RouteMismatch::TYPE_METHOD_MISMATCH,
            ])) {
                // For parameter/method mismatches, we have both route and endpoint data
                $routeMap[$signature] = [
                    'name' => $mismatch->details['route_name'] ?? '',
                    'action' => $mismatch->details['action'] ?? '',
                    'middleware' => $mismatch->details['middleware'] ?? [],
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

        // Extract endpoints from mismatches that have endpoint data
        foreach ($result->mismatches as $mismatch) {
            $signature = $mismatch->method . ':' . $mismatch->path;

            if ($mismatch->type === RouteMismatch::TYPE_MISSING_IMPLEMENTATION) {
                $endpointMap[$signature] = [
                    'operation_id' => $mismatch->details['operation_id'] ?? '',
                    'summary' => $mismatch->details['summary'] ?? '',
                    'tags' => $mismatch->details['tags'] ?? [],
                    'pathParameters' => $this->extractPathParameters($mismatch->path),
                ];
            } elseif (in_array($mismatch->type, [
                RouteMismatch::TYPE_PARAMETER_MISMATCH,
                RouteMismatch::TYPE_METHOD_MISMATCH,
            ])) {
                // For parameter/method mismatches, we have both route and endpoint data
                $endpointMap[$signature] = [
                    'operation_id' => $mismatch->details['operation_id'] ?? '',
                    'summary' => $mismatch->details['summary'] ?? '',
                    'tags' => $mismatch->details['tags'] ?? [],
                    'pathParameters' => $mismatch->details['endpoint_parameters'] ?? $this->extractPathParameters($mismatch->path),
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
     * Calculate optimal column widths
     *
     * @param  array<string>  $headers
     * @param  array<array<string, string>>  $rows
     * @return array<int>
     */
    private function calculateColumnWidths(array $headers, array $rows, int $maxWidth): array
    {
        $headerKeys = ['method', 'path', 'laravel_params', 'openapi_params', 'status', 'route_name', 'tags'];
        $widths = [];

        // Calculate minimum required width for each column
        foreach ($headers as $index => $header) {
            $key = $headerKeys[$index] ?? '';
            $width = max(strlen($header), self::MIN_CELL_WIDTH);

            foreach ($rows as $row) {
                if (isset($row[$key])) {
                    $width = max($width, min(strlen($row[$key]), self::MAX_CELL_WIDTH));
                }
            }

            $widths[] = $width;
        }

        // Adjust widths to fit within max width if needed
        $borderWidth = (count($widths) * 3) + 1; // borders and padding: │ text │ = 3 chars per column + 1 for final │
        $totalContentWidth = array_sum($widths);
        $totalWidth = $totalContentWidth + $borderWidth;

        if ($totalWidth > $maxWidth) {
            $availableContentWidth = $maxWidth - $borderWidth;
            $adjustableColumns = count($widths);

            if ($adjustableColumns > 0 && $availableContentWidth > ($adjustableColumns * self::MIN_CELL_WIDTH)) {
                // Proportionally reduce all columns
                $scaleFactor = $availableContentWidth / $totalContentWidth;
                $counter = count($widths);

                for ($i = 0; $i < $counter; $i++) {
                    $newWidth = max(self::MIN_CELL_WIDTH, intval($widths[$i] * $scaleFactor));
                    $widths[$i] = $newWidth;
                }

                // Fine-tune if we're still over
                $currentContentWidth = array_sum($widths);
                if ($currentContentWidth > $availableContentWidth) {
                    $excess = $currentContentWidth - $availableContentWidth;

                    // Remove excess by reducing longest columns
                    for ($j = 0; $j < $excess; $j++) {
                        $maxIndex = array_search(max($widths), $widths);
                        if ($maxIndex !== false && $widths[$maxIndex] > self::MIN_CELL_WIDTH) {
                            $widths[$maxIndex]--;
                        }
                    }
                }
            } else {
                // If we can't fit even minimum widths, just use minimum
                $counter = count($widths);
                // If we can't fit even minimum widths, just use minimum
                for ($i = 0; $i < $counter; $i++) {
                    $widths[$i] = self::MIN_CELL_WIDTH;
                }
            }
        }

        return $widths;
    }

    /**
     * Render the complete table
     *
     * @param  array<string>  $headers
     * @param  array<array<string, string>>  $rows
     * @param  array<int>  $columnWidths
     */
    private function renderTable(array $headers, array $rows, array $columnWidths): string
    {
        $output = [];

        // Top border
        $output[] = $this->renderBorder($columnWidths, '┌', '┬', '┐');

        // Headers
        $output[] = $this->renderRow($headers, $columnWidths);

        // Header separator
        $output[] = $this->renderBorder($columnWidths, '├', '┼', '┤');

        // Data rows
        $headerKeys = ['method', 'path', 'laravel_params', 'openapi_params', 'status', 'route_name', 'tags'];
        foreach ($rows as $row) {
            $rowData = [];
            foreach (array_keys($headers) as $index) {
                $key = $headerKeys[$index] ?? '';
                $rowData[] = $row[$key] ?? '';
            }
            $output[] = $this->renderRow($rowData, $columnWidths);
        }

        // Bottom border
        $output[] = $this->renderBorder($columnWidths, '└', '┴', '┘');

        return implode("\n", $output);
    }

    /**
     * Render a table border
     *
     * @param  array<int>  $columnWidths
     */
    private function renderBorder(array $columnWidths, string $left, string $middle, string $right): string
    {
        $parts = [];
        foreach ($columnWidths as $width) {
            $parts[] = str_repeat('─', $width + 2);
        }

        return $left . implode($middle, $parts) . $right;
    }

    /**
     * Render a table row
     *
     * @param  array<string>  $columns
     * @param  array<int>  $columnWidths
     */
    private function renderRow(array $columns, array $columnWidths): string
    {
        $cells = [];

        foreach ($columns as $index => $content) {
            $width = $columnWidths[$index] ?? self::MIN_CELL_WIDTH;
            $truncated = $this->truncateText($content, $width);
            $cells[] = ' ' . str_pad($truncated, $width) . ' ';
        }

        return '│' . implode('│', $cells) . '│';
    }

    /**
     * Truncate text to fit in cell
     */
    private function truncateText(string $text, int $maxWidth): string
    {
        if (strlen($text) <= $maxWidth) {
            return $text;
        }

        return substr($text, 0, $maxWidth - 3) . '...';
    }

    /**
     * Generate report header
     */
    private function generateHeader(): string
    {
        $title = 'Route Validation Table Report';
        $timestamp = 'Generated: ' . date('Y-m-d H:i:s');

        return "{$title}\n{$timestamp}";
    }

    /**
     * Generate summary section
     */
    private function generateSummary(ValidationResult $result): string
    {
        $stats = $result->statistics;
        $mismatchCount = $result->getMismatchCount();

        $summary = [];
        $summary[] = 'SUMMARY';
        $summary[] = str_repeat('-', 7);

        if (isset($stats['total_routes'], $stats['total_endpoints'])) {
            $totalItems = (int) $stats['total_routes'] + (int) $stats['total_endpoints'];
            $matchedItems = $totalItems - $mismatchCount;
            $coveragePercent = $totalItems > 0 ? round(($matchedItems / $totalItems) * 100, 1) : 100;

            $summary[] = "Total items: {$totalItems}";
            $summary[] = "Matched: {$matchedItems} ({$coveragePercent}%)";
            $summary[] = "Issues: {$mismatchCount}";
        }

        if (isset($stats['mismatch_breakdown']) && is_array($stats['mismatch_breakdown'])) {
            $summary[] = '';
            $summary[] = 'Issue breakdown:';
            foreach ($stats['mismatch_breakdown'] as $type => $count) {
                $displayType = match ($type) {
                    RouteMismatch::TYPE_MISSING_DOCUMENTATION => 'Missing documentation',
                    RouteMismatch::TYPE_MISSING_IMPLEMENTATION => 'Missing implementation',
                    RouteMismatch::TYPE_METHOD_MISMATCH => 'Method mismatches',
                    RouteMismatch::TYPE_PARAMETER_MISMATCH => 'Parameter mismatches',
                    default => $type,
                };
                $summary[] = "  {$displayType}: {$count}";
            }
        }

        return implode("\n", $summary);
    }

    /**
     * Generate report for empty results
     */
    private function generateEmptyReport(ValidationResult $result): string
    {
        $output = [];
        $output[] = $this->generateHeader();
        $output[] = 'No routes or endpoints found to validate.';
        $output[] = $this->generateSummary($result);

        return implode("\n\n", $output);
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
}
