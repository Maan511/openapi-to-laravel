<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Maan511\OpenapiToLaravel\Console\ValidateRoutesCommand;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\HelperSet;

beforeEach(function (): void {
    $this->validateCommand = new ValidateRoutesCommand;

    // Create mock application and set up command
    $this->application = Mockery::mock(\Illuminate\Console\Application::class);
    $this->application->shouldReceive('getHelperSet')->andReturn(Mockery::mock(HelperSet::class));

    $this->validateCommand->setLaravel(Mockery::mock(Application::class));
    $this->validateCommand->setApplication($this->application);
});

afterEach(function (): void {
    Mockery::close();
});

describe('ValidateRoutesCommand', function (): void {
    describe('command signature', function (): void {
        it('should have correct signature', function (): void {
            $signature = $this->validateCommand->getName();
            expect($signature)->toBe('openapi-to-laravel:validate-routes');
        });

        it('should have correct description', function (): void {
            $description = $this->validateCommand->getDescription();
            expect($description)->toBe('Validate that Laravel routes match OpenAPI specification endpoints');
        });
    });

    describe('validateInputs', function (): void {
        it('should validate existing readable spec file', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test');
            unlink($tempFile);
            $tempFile .= '.json';
            file_put_contents($tempFile, json_encode([
                'openapi' => '3.0.0',
                'info' => ['title' => 'Test', 'version' => '1.0.0'],
                'paths' => [],
            ]));

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('validateInputs');

            $result = $method->invoke($this->validateCommand, $tempFile);

            expect($result['success'])->toBeTrue();

            unlink($tempFile);
        });

        it('should reject non-existent spec file', function (): void {
            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('validateInputs');

            $result = $method->invoke($this->validateCommand, '/non/existent/file.json');

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('not found');
        });

        it('should reject non-readable spec file', function (): void {
            $tempFile = tempnam(sys_get_temp_dir(), 'openapi_test');
            unlink($tempFile);
            $tempFile .= '.json';
            file_put_contents($tempFile, '{}');
            chmod($tempFile, 0000); // Make file non-readable

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('validateInputs');

            $result = $method->invoke($this->validateCommand, $tempFile);

            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('not readable');

            // Cleanup
            chmod($tempFile, 0644);
            unlink($tempFile);
        });
    });

    describe('displayResults', function (): void {
        it('should display JSON output correctly', function (): void {
            $testRoute = new LaravelRoute(
                uri: 'api/test',
                methods: ['GET'],
                name: 'test.route',
                action: 'TestController@index',
                middleware: ['api']
            );

            $validationResult = new ValidationResult(
                isValid: false,
                mismatches: [
                    RouteMismatch::missingDocumentation($testRoute),
                ],
                warnings: [],
                statistics: ['total_routes' => 1]
            );

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('displayResults');

            $outputMock = Mockery::mock(OutputStyle::class);
            $outputMock->shouldIgnoreMissing();

            $this->validateCommand->setOutput($outputMock);

            $result = $method->invoke($this->validateCommand, $validationResult, 'json', false);

            // Just verify the method completes without error for JSON format
            expect($result)->toBeNull();
        });

        it('should display console output with summary', function (): void {
            $validationResult = new ValidationResult(
                isValid: true,
                mismatches: [],
                warnings: [],
                statistics: ['total_routes' => 5, 'total_endpoints' => 5]
            );

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('displayResults');

            $outputMock = Mockery::mock(OutputStyle::class);
            $outputMock->shouldIgnoreMissing();

            $this->validateCommand->setOutput($outputMock);

            $result = $method->invoke($this->validateCommand, $validationResult, 'console', false);

            expect($result)->toBeNull(); // Method doesn't return anything
        });

        it('should display mismatches when validation fails', function (): void {
            $testRoute = new LaravelRoute(
                uri: 'api/test',
                methods: ['GET'],
                name: 'test.route',
                action: 'TestController@index',
                middleware: ['api']
            );

            $mismatch = RouteMismatch::missingDocumentation($testRoute, ['Add endpoint to OpenAPI spec']);

            $validationResult = new ValidationResult(
                isValid: false,
                mismatches: [$mismatch],
                warnings: [],
                statistics: []
            );

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('displayResults');

            $outputMock = Mockery::mock(OutputStyle::class);
            $outputMock->shouldIgnoreMissing();

            $this->validateCommand->setOutput($outputMock);

            $result = $method->invoke($this->validateCommand, $validationResult, 'console', true);

            expect($result)->toBeNull(); // Method doesn't return anything
        });

        it('should display warnings when present', function (): void {
            $validationResult = new ValidationResult(
                isValid: true,
                mismatches: [],
                warnings: ['Warning: Some routes may be outdated'],
                statistics: []
            );

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('displayResults');

            // Mock the formatter properly
            $formatterMock = Mockery::mock(OutputFormatterInterface::class);
            $formatterMock->shouldReceive('hasStyle')->with('warning')->andReturn(false);
            $formatterMock->shouldReceive('setStyle')->andReturn(null);

            $outputMock = Mockery::mock(OutputStyle::class);
            $outputMock->shouldIgnoreMissing();
            $outputMock->shouldReceive('getFormatter')->andReturn($formatterMock);

            $this->validateCommand->setOutput($outputMock);

            $result = $method->invoke($this->validateCommand, $validationResult, 'console', false);

            expect($result)->toBeNull(); // Method doesn't return anything
        });
    });

    describe('saveReport', function (): void {
        it('should save JSON report to file', function (): void {
            $validationResult = new ValidationResult(
                isValid: true,
                mismatches: [],
                warnings: [],
                statistics: ['total_routes' => 3]
            );

            $tempFile = tempnam(sys_get_temp_dir(), 'report_test') . '.json';

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('saveReport');

            $outputMock = Mockery::mock(OutputStyle::class);
            $outputMock->shouldIgnoreMissing();
            $this->validateCommand->setOutput($outputMock);

            $method->invoke($this->validateCommand, $validationResult, $tempFile, 'json', false);

            expect(file_exists($tempFile))->toBeTrue();
            $content = file_get_contents($tempFile);
            expect($content)->not->toBeFalse();

            // Debug: check if content is actually JSON
            $decoded = json_decode((string) $content, true);
            expect($decoded)->toBeArray()
                ->and($decoded)->toHaveKey('validation')
                ->and($decoded['validation']['status'])->toBe('passed');

            unlink($tempFile);
        });

        it('should save text report to file', function (): void {
            $testRoute = new LaravelRoute(
                uri: 'api/test',
                methods: ['GET'],
                name: 'test.route',
                action: 'TestController@index',
                middleware: ['api']
            );

            $validationResult = new ValidationResult(
                isValid: false,
                mismatches: [
                    RouteMismatch::missingDocumentation($testRoute),
                ],
                warnings: [],
                statistics: ['total_routes' => 1]
            );

            $tempFile = tempnam(sys_get_temp_dir(), 'report_test') . '.txt';

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('saveReport');

            $outputMock = Mockery::mock(OutputStyle::class);
            $outputMock->shouldIgnoreMissing();
            $this->validateCommand->setOutput($outputMock);

            $method->invoke($this->validateCommand, $validationResult, $tempFile, 'console', false);

            expect(file_exists($tempFile))->toBeTrue();
            $content = file_get_contents($tempFile);
            expect($content)->toContain('Route Validation Report');
            expect($content)->toContain('Status: FAILED');

            unlink($tempFile);
        });
    });

    describe('generateTextReport', function (): void {
        it('should generate comprehensive text report', function (): void {
            $testRoute = new LaravelRoute(
                uri: 'api/test',
                methods: ['GET'],
                name: 'test.route',
                action: 'TestController@index',
                middleware: ['api']
            );

            $mismatch = RouteMismatch::missingDocumentation($testRoute);
            $validationResult = new ValidationResult(
                isValid: false,
                mismatches: [$mismatch],
                warnings: ['Test warning'],
                statistics: [
                    'total_routes' => 5,
                    'total_endpoints' => 4,
                    'coverage_percentage' => 80.0,
                ]
            );

            $reflection = new ReflectionClass($this->validateCommand);
            $method = $reflection->getMethod('generateTextReport');

            $report = $method->invoke($this->validateCommand, $validationResult);

            expect($report)->toContain('Route Validation Report');
            expect($report)->toContain('Status: FAILED');
            expect($report)->toContain('Total mismatches: 1');
            expect($report)->toContain('GET:/api/test');
            expect($report)->toContain('total_routes: 5');
        });
    });

    describe('private method visibility', function (): void {
        it('should have all required private methods accessible via reflection', function (): void {
            $reflection = new ReflectionClass($this->validateCommand);

            $methods = [
                'validateInputs',
                'displayResults',
                'displayConsoleSummary',
                'displayMismatches',
                'displayWarnings',
                'displayStatistics',
                'saveReport',
                'generateTextReport',
            ];

            foreach ($methods as $methodName) {
                expect($reflection->hasMethod($methodName))->toBeTrue();
                $method = $reflection->getMethod($methodName);
                expect($method->isPrivate())->toBeTrue();
            }
        });
    });
});
