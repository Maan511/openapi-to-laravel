<?php

use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;
use Maan511\OpenapiToLaravel\Parser\ServerPathExtractor;

describe('ServerPathExtractor', function (): void {
    it('should extract base paths from servers', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com/api/v1'],
                ['url' => 'https://staging.example.com/api'],
                ['url' => 'http://localhost:8000'],
            ]
        );

        $extractor = new ServerPathExtractor;
        $basePaths = $extractor->extractBasePaths($specification);

        expect($basePaths)->toBe(['/api/v1', '/api']);
    });

    it('should return empty array when no servers have paths', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com'],
                ['url' => 'http://localhost:8000/'],
            ]
        );

        $extractor = new ServerPathExtractor;
        $basePaths = $extractor->extractBasePaths($specification);

        expect($basePaths)->toBe([]);
    });

    it('should get default base path from first server', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com/api'],
                ['url' => 'https://staging.example.com/v2'],
            ]
        );

        $extractor = new ServerPathExtractor;
        $defaultPath = $extractor->getDefaultBasePath($specification);

        expect($defaultPath)->toBe('/api');
    });

    it('should return empty string when no servers', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: []
        );

        $extractor = new ServerPathExtractor;
        $defaultPath = $extractor->getDefaultBasePath($specification);

        expect($defaultPath)->toBe('');
    });

    it('should resolve base path with user override', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com/api'],
                ['url' => 'https://staging.example.com/v2'],
            ]
        );

        $extractor = new ServerPathExtractor;
        $resolvedPath = $extractor->resolveBasePath($specification, '/v2');

        expect($resolvedPath)->toBe('/v2');
    });

    it('should throw exception when user specifies invalid base path', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com/api'],
            ]
        );

        $extractor = new ServerPathExtractor;

        expect(fn () => $extractor->resolveBasePath($specification, '/invalid'))
            ->toThrow(InvalidArgumentException::class, 'Specified base path \'/invalid\' not found in servers');
    });

    it('should throw exception when multiple base paths available and none specified', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com/api'],
                ['url' => 'https://staging.example.com/v2'],
            ]
        );

        $extractor = new ServerPathExtractor;

        expect(fn () => $extractor->resolveBasePath($specification))
            ->toThrow(InvalidArgumentException::class, 'Multiple server base paths found');
    });

    it('should normalize base paths correctly', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'https://api.example.com/api/'],
                ['url' => 'https://staging.example.com/v2//'],
                ['url' => 'http://localhost:8000/'],
                ['url' => 'http://localhost:3000'],
            ]
        );

        $extractor = new ServerPathExtractor;
        $basePaths = $extractor->extractBasePaths($specification);

        expect($basePaths)->toBe(['/api', '/v2']);
    });

    it('should handle malformed server URLs gracefully', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [
                ['url' => 'not-a-valid-url'],
                ['url' => 'https://api.example.com/api'],
                [], // Missing url
                ['url' => 123], // Non-string url
            ]
        );

        $extractor = new ServerPathExtractor;
        $basePaths = $extractor->extractBasePaths($specification);

        expect($basePaths)->toBe(['/api']);
    });

    it('should allow user to specify custom base path even when not in servers', function (): void {
        $specification = new OpenApiSpecification(
            filePath: 'test.json',
            version: '3.0.0',
            info: ['title' => 'Test API', 'version' => '1.0.0'],
            paths: [],
            servers: [] // No servers
        );

        $extractor = new ServerPathExtractor;
        $resolvedPath = $extractor->resolveBasePath($specification, '/custom');

        expect($resolvedPath)->toBe('/custom');
    });
});
