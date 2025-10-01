<?php

namespace Maan511\OpenapiToLaravel\Validation\Reporters;

use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;

/**
 * HTML-formatted validation reporter
 */
class HtmlReporter implements ReporterInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generateReport(ValidationResult $result, array $options = []): string
    {
        $title = $options['title'] ?? 'Route Validation Report';
        $includeCss = $options['include_css'] ?? true;
        $includeSuggestions = $options['include_suggestions'] ?? true;

        return $this->generateHtml($result, $title, $includeCss, $includeSuggestions);
    }

    public function getFileExtension(): string
    {
        return 'html';
    }

    public function getMimeType(): string
    {
        return 'text/html';
    }

    public function supports(string $format): bool
    {
        return strtolower($format) === 'html';
    }

    /**
     * Generate complete HTML report
     */
    private function generateHtml(ValidationResult $result, string $title, bool $includeCss, bool $includeSuggestions): string
    {
        $css = $includeCss ? $this->generateCss() : '';
        $body = $this->generateBody($result, $includeSuggestions);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    {$css}
</head>
<body>
    {$body}
</body>
</html>
HTML;
    }

    /**
     * Generate CSS styles
     */
    private function generateCss(): string
    {
        return <<<'CSS'
<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: #333;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        background-color: #f5f5f5;
    }

    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .header h1 {
        margin: 0 0 10px 0;
        font-size: 2.5em;
        font-weight: 300;
    }

    .header .timestamp {
        opacity: 0.9;
        font-size: 1.1em;
    }

    .summary {
        background: white;
        padding: 25px;
        border-radius: 8px;
        margin-bottom: 25px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .summary h2 {
        margin-top: 0;
        color: #2c3e50;
        border-bottom: 2px solid #ecf0f1;
        padding-bottom: 10px;
    }

    .status {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .status.passed {
        background-color: #2ecc71;
        color: white;
    }

    .status.failed {
        background-color: #e74c3c;
        color: white;
    }

    .section {
        background: white;
        margin-bottom: 25px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .section-header {
        background-color: #34495e;
        color: white;
        padding: 15px 25px;
        font-weight: bold;
        font-size: 1.2em;
    }

    .section-content {
        padding: 25px;
    }

    .mismatch {
        border-left: 4px solid #e74c3c;
        background-color: #fdf2f2;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 0 8px 8px 0;
    }

    .mismatch.warning {
        border-left-color: #f39c12;
        background-color: #fef9e7;
    }

    .mismatch.info {
        border-left-color: #3498db;
        background-color: #ebf3fd;
    }

    .mismatch-header {
        font-weight: bold;
        font-size: 1.1em;
        margin-bottom: 10px;
        color: #2c3e50;
    }

    .mismatch-details {
        background-color: rgba(0, 0, 0, 0.05);
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
    }

    .mismatch-details dt {
        font-weight: bold;
        margin-top: 10px;
        color: #7f8c8d;
    }

    .mismatch-details dd {
        margin: 5px 0 10px 15px;
    }

    .suggestions {
        background-color: rgba(52, 152, 219, 0.1);
        border: 1px solid #3498db;
        border-radius: 5px;
        padding: 15px;
        margin-top: 15px;
    }

    .suggestions h4 {
        margin: 0 0 10px 0;
        color: #2980b9;
    }

    .suggestions ul {
        margin: 0;
        padding-left: 20px;
    }

    .suggestions li {
        margin-bottom: 5px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .stat-card {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #e9ecef;
    }

    .stat-value {
        font-size: 2em;
        font-weight: bold;
        color: #495057;
        display: block;
    }

    .stat-label {
        color: #6c757d;
        text-transform: uppercase;
        font-size: 0.9em;
        letter-spacing: 1px;
        margin-top: 5px;
    }

    .mismatch-type {
        display: inline-block;
        background-color: #6c757d;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8em;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }

    .icon {
        font-size: 1.2em;
        margin-right: 8px;
    }
</style>
CSS;
    }

    /**
     * Generate HTML body content
     */
    private function generateBody(ValidationResult $result, bool $includeSuggestions): string
    {
        $header = $this->generateHeader();
        $summary = $this->generateSummarySection($result);
        $mismatches = $this->generateMismatchesSection($result, $includeSuggestions);
        $warnings = $this->generateWarningsSection($result);
        $statistics = $this->generateStatisticsSection($result);

        return implode("\n", array_filter([$header, $summary, $mismatches, $warnings, $statistics]));
    }

    /**
     * Generate header section
     */
    private function generateHeader(): string
    {
        $timestamp = date('F j, Y \a\t g:i A');

        return <<<HTML
<div class="header">
    <h1>Route Validation Report</h1>
    <div class="timestamp">Generated on {$timestamp}</div>
</div>
HTML;
    }

    /**
     * Generate summary section
     */
    private function generateSummarySection(ValidationResult $result): string
    {
        $statusClass = $result->isValid ? 'passed' : 'failed';
        $statusText = $result->isValid ? 'Passed' : 'Failed';
        $mismatchCount = $result->getMismatchCount();
        $warningCount = count($result->warnings);

        return <<<HTML
<div class="summary">
    <h2>Validation Summary</h2>
    <p><span class="status {$statusClass}">{$statusText}</span></p>
    <p><strong>Total mismatches:</strong> {$mismatchCount}</p>
    {$this->conditionalContent($warningCount > 0, "<p><strong>Warnings:</strong> {$warningCount}</p>")}
</div>
HTML;
    }

    /**
     * Generate mismatches section
     */
    private function generateMismatchesSection(ValidationResult $result, bool $includeSuggestions): string
    {
        if ($result->isValid) {
            return '';
        }

        // Sort mismatches alphabetically by path then method
        $sortedMismatches = $result->mismatches;
        usort($sortedMismatches, function (RouteMismatch $a, RouteMismatch $b): int {
            $pathCompare = strcmp($a->path, $b->path);
            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            return strcmp($a->method, $b->method);
        });

        $content = '';
        foreach ($sortedMismatches as $mismatch) {
            $content .= $this->formatMismatchHtml($mismatch, $includeSuggestions);
        }

        return <<<HTML
<div class="section">
    <div class="section-header">
        <span class="icon">⚠</span>Mismatches
    </div>
    <div class="section-content">
        {$content}
    </div>
</div>
HTML;
    }

    /**
     * Format a single mismatch as HTML
     */
    private function formatMismatchHtml(RouteMismatch $mismatch, bool $includeSuggestions): string
    {
        $severityClass = $mismatch->severity;
        $typeLabel = ucwords(str_replace('_', ' ', $mismatch->type));

        $details = '';
        if ($mismatch->details !== []) {
            $details = '<dl>';
            foreach ($mismatch->details as $key => $value) {
                $displayValue = is_array($value) ? implode(', ', $value) : htmlspecialchars((string) $value);
                $details .= '<dt>' . ucfirst(str_replace('_', ' ', $key)) . ':</dt>';
                $details .= "<dd>{$displayValue}</dd>";
            }
            $details .= '</dl>';
        }

        $suggestions = '';
        if ($includeSuggestions && $mismatch->suggestions !== []) {
            $suggestions .= '<div class="suggestions">';
            $suggestions .= '<h4>💡 Suggestions</h4>';
            $suggestions .= '<ul>';
            foreach ($mismatch->suggestions as $suggestion) {
                $suggestions .= '<li>' . htmlspecialchars($suggestion) . '</li>';
            }
            $suggestions .= '</ul>';
            $suggestions .= '</div>';
        }

        return <<<HTML
<div class="mismatch {$severityClass}">
    <div class="mismatch-type">{$typeLabel}</div>
    <div class="mismatch-header">{$mismatch->message}</div>
    <div class="mismatch-details">
        <strong>Path:</strong> {$mismatch->path}<br>
        <strong>Method:</strong> {$mismatch->method}
        {$this->conditionalContent($details !== '' && $details !== '0', $details)}
    </div>
    {$suggestions}
</div>
HTML;
    }

    /**
     * Generate warnings section
     */
    private function generateWarningsSection(ValidationResult $result): string
    {
        if (! $result->hasWarnings()) {
            return '';
        }

        $warnings = '';
        foreach ($result->warnings as $warning) {
            $warnings .= '<p><span class="icon">⚠</span>' . htmlspecialchars($warning) . '</p>';
        }

        return <<<HTML
<div class="section">
    <div class="section-header">
        <span class="icon">⚠</span>Warnings
    </div>
    <div class="section-content">
        {$warnings}
    </div>
</div>
HTML;
    }

    /**
     * Generate statistics section
     */
    private function generateStatisticsSection(ValidationResult $result): string
    {
        $stats = $result->statistics;
        $cards = '';

        foreach ($stats as $key => $value) {
            if ($key === 'mismatch_breakdown') {
                continue; // Handle separately
            }

            $label = ucwords(str_replace('_', ' ', $key));
            $displayValue = is_array($value) ? count($value) : $value;

            $cards .= <<<HTML
<div class="stat-card">
    <span class="stat-value">{$displayValue}</span>
    <div class="stat-label">{$label}</div>
</div>
HTML;
        }

        return <<<HTML
<div class="section">
    <div class="section-header">
        <span class="icon">📊</span>Statistics
    </div>
    <div class="section-content">
        <div class="stats-grid">
            {$cards}
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Conditional content helper
     */
    private function conditionalContent(bool $condition, string $content): string
    {
        return $condition ? $content : '';
    }
}
