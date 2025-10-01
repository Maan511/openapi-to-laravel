<?php

namespace Maan511\OpenapiToLaravel\Validation\Reporters;

use InvalidArgumentException;

/**
 * Factory for creating validation reporters
 */
class ReporterFactory
{
    /**
     * @var array<string, string>
     */
    private static array $reporterMap = [
        'console' => ConsoleReporter::class,
        'text' => ConsoleReporter::class,
        'txt' => ConsoleReporter::class,
        'json' => JsonReporter::class,
        'html' => HtmlReporter::class,
    ];

    /**
     * Create a reporter for the given format
     */
    public static function create(string $format): ReporterInterface
    {
        $format = strtolower($format);

        if (! isset(self::$reporterMap[$format])) {
            throw new InvalidArgumentException("Unsupported report format: {$format}");
        }

        $reporterClass = self::$reporterMap[$format];

        /** @var class-string<ReporterInterface> $reporterClass */
        return new $reporterClass;
    }

    /**
     * Get all supported formats
     *
     * @return array<string>
     */
    public static function getSupportedFormats(): array
    {
        return array_keys(self::$reporterMap);
    }

    /**
     * Check if a format is supported
     */
    public static function isSupported(string $format): bool
    {
        return isset(self::$reporterMap[strtolower($format)]);
    }

    /**
     * Register a new reporter
     */
    public static function register(string $format, string $reporterClass): void
    {
        if (! is_subclass_of($reporterClass, ReporterInterface::class)) {
            throw new InvalidArgumentException('Reporter class must implement ReporterInterface');
        }

        self::$reporterMap[strtolower($format)] = $reporterClass;
    }

    /**
     * Create multiple reporters for different formats
     *
     * @param  array<string>  $formats
     * @return array<string, ReporterInterface>
     */
    public static function createMultiple(array $formats): array
    {
        $reporters = [];

        foreach ($formats as $format) {
            $reporters[$format] = self::create($format);
        }

        return $reporters;
    }

    /**
     * Get the appropriate file extension for a format
     */
    public static function getFileExtension(string $format): string
    {
        $reporter = self::create($format);

        return $reporter->getFileExtension();
    }

    /**
     * Get the MIME type for a format
     */
    public static function getMimeType(string $format): string
    {
        $reporter = self::create($format);

        return $reporter->getMimeType();
    }
}
