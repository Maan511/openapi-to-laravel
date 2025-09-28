<?php

namespace Maan511\OpenapiToLaravel\Parser;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;

class ServerPathExtractor
{
    /**
     * Extract base paths from all servers in the specification
     *
     * @return array<string>
     */
    public function extractBasePaths(OpenApiSpecification $specification): array
    {
        $basePaths = [];

        foreach ($specification->servers as $server) {
            if (! isset($server['url']) || ! is_string($server['url'])) {
                continue;
            }

            $basePath = $this->extractBasePathFromUrl($server['url']);
            if ($basePath !== '' && ! in_array($basePath, $basePaths)) {
                $basePaths[] = $basePath;
            }
        }

        return $basePaths;
    }

    /**
     * Get the default base path (first server's path, or empty if none)
     */
    public function getDefaultBasePath(OpenApiSpecification $specification): string
    {
        $basePaths = $this->extractBasePaths($specification);

        return $basePaths[0] ?? '';
    }

    /**
     * Validate and resolve base path based on specification and user choice
     */
    public function resolveBasePath(OpenApiSpecification $specification, ?string $userBasePath = null): string
    {
        $availablePaths = $this->extractBasePaths($specification);

        // If user specified a base path, validate it
        if ($userBasePath !== null) {
            $userBasePath = $this->normalizeBasePath($userBasePath);

            if (! in_array($userBasePath, $availablePaths) && $availablePaths !== []) {
                throw new InvalidArgumentException(
                    "Specified base path '{$userBasePath}' not found in servers. Available paths: " .
                    implode(', ', $availablePaths)
                );
            }

            return $userBasePath;
        }

        // If multiple paths available, require user to choose
        if (count($availablePaths) > 1) {
            throw new InvalidArgumentException(
                'Multiple server base paths found: ' . implode(', ', $availablePaths) .
                '. Please specify which one to use with --base-path option.'
            );
        }

        // Return default (first path or empty)
        return $this->getDefaultBasePath($specification);
    }

    /**
     * Extract base path from a server URL
     */
    private function extractBasePathFromUrl(string $url): string
    {
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            return '';
        }

        // Ensure URL has a valid scheme to be considered a proper server URL
        if (! isset($parsedUrl['scheme']) || ! in_array($parsedUrl['scheme'], ['http', 'https'])) {
            return '';
        }

        $path = $parsedUrl['path'] ?? '';

        return $this->normalizeBasePath($path);
    }

    /**
     * Normalize base path (ensure it starts with / and doesn't end with /)
     */
    private function normalizeBasePath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $path === '/') {
            return '';
        }

        // Ensure starts with /
        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Remove trailing /
        return rtrim($path, '/');
    }
}
