<?php

namespace Maan511\OpenapiToLaravel\Validation;

/**
 * Unified pattern matching utility for route/endpoint path filtering
 */
class PatternMatcher
{
    /**
     * Check if a path matches a pattern with unified normalization
     *
     * Supports patterns like:
     * - api slash-star or /api slash-star (prefix match)
     * - star-users-star (contains)
     * - star/users (suffix match)
     * - /api star/users (mid-path wildcard)
     */
    public static function matches(string $pattern, string $path): bool
    {
        $normalizedPattern = self::normalizePattern($pattern);
        $normalizedPath = self::normalizePath($path);

        return fnmatch($normalizedPattern, $normalizedPath);
    }

    /**
     * Filter an array of paths by pattern
     *
     * @param  array<string>  $paths
     * @return array<string>
     */
    public static function filter(array $paths, string $pattern): array
    {
        return array_filter($paths, fn (string $path): bool => self::matches($pattern, $path));
    }

    /**
     * Check if a path matches any of the provided patterns
     *
     * @param  array<string>  $patterns
     */
    public static function matchesAny(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a pattern for consistent matching
     *
     * Rules:
     * - Patterns starting with wildcard are kept as-is
     * - Patterns without leading slash get one added
     * - Patterns with leading slash are kept as-is
     * - Patterns are lowercased for case-insensitive matching
     */
    public static function normalizePattern(string $pattern): string
    {
        $pattern = trim($pattern);

        // If pattern starts with wildcard, keep it as-is
        if (str_starts_with($pattern, '*')) {
            return strtolower($pattern);
        }

        // Ensure leading slash for patterns not starting with wildcard
        if (! str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern;
        }

        return strtolower($pattern);
    }

    /**
     * Normalize a path for consistent matching
     *
     * Rules:
     * - Always ensure leading slash
     * - Remove trailing slash (except for root /)
     * - Lowercase for case-insensitive matching
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        // Ensure leading slash
        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Remove trailing slash (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return strtolower($path);
    }

    /**
     * Count how many paths match a pattern
     *
     * @param  array<string>  $paths
     */
    public static function countMatches(string $pattern, array $paths): int
    {
        return count(self::filter($paths, $pattern));
    }

    /**
     * Get suggestions when a pattern matches nothing
     *
     * @param  array<string>  $availablePaths  Sample of available paths
     * @return array<string>
     */
    public static function getSuggestions(string $pattern, array $availablePaths): array
    {
        $suggestions = [];
        self::normalizePattern($pattern);

        // If pattern doesn't start with /, suggest adding it
        if (! str_starts_with($pattern, '/') && ! str_starts_with($pattern, '*')) {
            $withSlash = '/' . $pattern;
            if (self::countMatches($withSlash, $availablePaths) > 0) {
                $suggestions[] = "Try adding a leading slash: '{$withSlash}'";
            }
        }

        // If pattern starts with /, suggest removing it
        if (str_starts_with($pattern, '/') && strlen($pattern) > 1) {
            $withoutSlash = ltrim($pattern, '/');
            if (self::countMatches($withoutSlash, $availablePaths) > 0) {
                $suggestions[] = "Try removing the leading slash: '{$withoutSlash}'";
            }
        }

        // Suggest wildcard patterns
        if (! str_contains($pattern, '*')) {
            $prefixPattern = $pattern . '*';
            if (self::countMatches($prefixPattern, $availablePaths) > 0) {
                $suggestions[] = "Try a prefix wildcard: '{$prefixPattern}'";
            }

            $containsPattern = '*' . trim($pattern, '/') . '*';
            if (self::countMatches($containsPattern, $availablePaths) > 0) {
                $suggestions[] = "Try a contains wildcard: '{$containsPattern}'";
            }
        }

        // Show some example paths if no suggestions found
        if ($suggestions === [] && $availablePaths !== []) {
            $examplePaths = array_slice($availablePaths, 0, 3);
            $suggestions[] = 'Available paths include: ' . implode(', ', $examplePaths);
        }

        return $suggestions;
    }

    /**
     * Validate that patterns are well-formed
     *
     * @param  array<string>  $patterns
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validatePatterns(array $patterns): array
    {
        $errors = [];

        foreach ($patterns as $pattern) {
            if (trim($pattern) === '') {
                $errors[] = 'Empty pattern provided';

                continue;
            }

            // Check for invalid characters (fnmatch special chars we might not want)
            if (preg_match('/[<>"|]/', $pattern)) {
                $errors[] = "Pattern '{$pattern}' contains invalid characters";
            }

            // Warn about patterns that might not work as expected
            if (str_ends_with($pattern, '/') && ! str_ends_with($pattern, '*/')) {
                $errors[] = "Pattern '{$pattern}' ends with / which might not match as expected. Consider removing it or adding *";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }
}
