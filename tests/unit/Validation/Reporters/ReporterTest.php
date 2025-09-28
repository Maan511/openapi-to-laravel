<?php

use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Maan511\OpenapiToLaravel\Validation\Reporters\ConsoleReporter;
use Maan511\OpenapiToLaravel\Validation\Reporters\HtmlReporter;
use Maan511\OpenapiToLaravel\Validation\Reporters\JsonReporter;
use Maan511\OpenapiToLaravel\Validation\Reporters\ReporterFactory;

describe('Validation Reporters', function (): void {
    beforeEach(function (): void {
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        $this->mismatch = RouteMismatch::missingDocumentation($route);

        $this->result = ValidationResult::failed(
            [$this->mismatch],
            ['This is a warning'],
            ['total_routes' => 5, 'total_endpoints' => 3, 'coverage_percentage' => 60.0]
        );

        $this->successResult = ValidationResult::success(['total_routes' => 5, 'total_endpoints' => 5]);
    });

    describe('ConsoleReporter', function (): void {
        beforeEach(function (): void {
            $this->reporter = new ConsoleReporter;
        });

        it('supports console formats', function (): void {
            expect($this->reporter->supports('console'))->toBeTrue()
                ->and($this->reporter->supports('text'))->toBeTrue()
                ->and($this->reporter->supports('json'))->toBeFalse();
        });

        it('generates console report for failed validation', function (): void {
            $report = $this->reporter->generateReport($this->result);

            expect($report)->toContain('VALIDATION SUMMARY')
                ->and($report)->toContain('FAILED')
                ->and($report)->toContain('MISMATCHES')
                ->and($report)->toContain('WARNINGS')
                ->and($report)->toContain('STATISTICS');
        });

        it('generates console report for successful validation', function (): void {
            $report = $this->reporter->generateReport($this->successResult);

            expect($report)->toContain('PASSED')
                ->and($report)->not->toContain('MISMATCHES');
        });

        it('includes suggestions when requested', function (): void {
            $report = $this->reporter->generateReport($this->result, ['include_suggestions' => true]);

            expect($report)->toContain('Suggestions');
        });

        it('returns correct file extension', function (): void {
            expect($this->reporter->getFileExtension())->toBe('txt');
        });
    });

    describe('JsonReporter', function (): void {
        beforeEach(function (): void {
            $this->reporter = new JsonReporter;
        });

        it('supports json format', function (): void {
            expect($this->reporter->supports('json'))->toBeTrue()
                ->and($this->reporter->supports('html'))->toBeFalse();
        });

        it('generates valid JSON', function (): void {
            $report = $this->reporter->generateReport($this->result);
            $data = json_decode($report, true);

            expect($data)->not->toBeNull()
                ->and($data)->toHaveKey('validation')
                ->and($data)->toHaveKey('mismatches')
                ->and($data)->toHaveKey('warnings')
                ->and($data)->toHaveKey('statistics')
                ->and($data)->toHaveKey('metadata');
        });

        it('formats data correctly', function (): void {
            $report = $this->reporter->generateReport($this->result);
            $data = json_decode($report, true);

            expect($data['validation']['status'])->toBe('failed')
                ->and($data['mismatches'])->toHaveCount(1)
                ->and($data['warnings'])->toHaveCount(1)
                ->and($data['statistics']['total_routes'])->toBe(5);
        });

        it('supports pretty printing option', function (): void {
            $compact = $this->reporter->generateReport($this->result, ['pretty_print' => false]);
            $pretty = $this->reporter->generateReport($this->result, ['pretty_print' => true]);

            expect(strlen($pretty))->toBeGreaterThan(strlen($compact))
                ->and($pretty)->toContain("\n")
                ->and($compact)->not->toContain("\n");
        });

        it('returns correct file extension', function (): void {
            expect($this->reporter->getFileExtension())->toBe('json');
        });
    });

    describe('HtmlReporter', function (): void {
        beforeEach(function (): void {
            $this->reporter = new HtmlReporter;
        });

        it('supports html format', function (): void {
            expect($this->reporter->supports('html'))->toBeTrue()
                ->and($this->reporter->supports('json'))->toBeFalse();
        });

        it('generates valid HTML', function (): void {
            $report = $this->reporter->generateReport($this->result);

            expect($report)->toContain('<!DOCTYPE html>')
                ->and($report)->toContain('<html')
                ->and($report)->toContain('<head>')
                ->and($report)->toContain('<body>')
                ->and($report)->toContain('</html>');
        });

        it('includes CSS when requested', function (): void {
            $withCss = $this->reporter->generateReport($this->result, ['include_css' => true]);
            $withoutCss = $this->reporter->generateReport($this->result, ['include_css' => false]);

            expect($withCss)->toContain('<style>')
                ->and($withoutCss)->not->toContain('<style>');
        });

        it('includes suggestions when requested', function (): void {
            $report = $this->reporter->generateReport($this->result, ['include_suggestions' => true]);

            expect($report)->toContain('Suggestions');
        });

        it('handles custom title', function (): void {
            $report = $this->reporter->generateReport($this->result, ['title' => 'Custom Report']);

            expect($report)->toContain('<title>Custom Report</title>');
        });

        it('returns correct file extension', function (): void {
            expect($this->reporter->getFileExtension())->toBe('html');
        });
    });

    describe('ReporterFactory', function (): void {
        it('creates reporters for supported formats', function (): void {
            $consoleReporter = ReporterFactory::create('console');
            $jsonReporter = ReporterFactory::create('json');
            $htmlReporter = ReporterFactory::create('html');

            expect($consoleReporter)->toBeInstanceOf(ConsoleReporter::class)
                ->and($jsonReporter)->toBeInstanceOf(JsonReporter::class)
                ->and($htmlReporter)->toBeInstanceOf(HtmlReporter::class);
        });

        it('throws exception for unsupported format', function (): void {
            expect(fn (): \Maan511\OpenapiToLaravel\Validation\Reporters\ReporterInterface => ReporterFactory::create('xml'))
                ->toThrow(InvalidArgumentException::class, 'Unsupported report format: xml');
        });

        it('returns supported formats', function (): void {
            $formats = ReporterFactory::getSupportedFormats();

            expect($formats)->toContain('console')
                ->and($formats)->toContain('json')
                ->and($formats)->toContain('html');
        });

        it('checks format support', function (): void {
            expect(ReporterFactory::isSupported('json'))->toBeTrue()
                ->and(ReporterFactory::isSupported('xml'))->toBeFalse();
        });

        it('creates multiple reporters', function (): void {
            $reporters = ReporterFactory::createMultiple(['console', 'json']);

            expect($reporters)->toHaveCount(2)
                ->and($reporters['console'])->toBeInstanceOf(ConsoleReporter::class)
                ->and($reporters['json'])->toBeInstanceOf(JsonReporter::class);
        });

        it('returns correct file extensions', function (): void {
            expect(ReporterFactory::getFileExtension('console'))->toBe('txt')
                ->and(ReporterFactory::getFileExtension('json'))->toBe('json')
                ->and(ReporterFactory::getFileExtension('html'))->toBe('html');
        });

        it('returns correct MIME types', function (): void {
            expect(ReporterFactory::getMimeType('console'))->toBe('text/plain')
                ->and(ReporterFactory::getMimeType('json'))->toBe('application/json')
                ->and(ReporterFactory::getMimeType('html'))->toBe('text/html');
        });
    });
});
