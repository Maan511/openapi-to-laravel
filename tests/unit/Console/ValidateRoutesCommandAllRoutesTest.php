<?php

use Maan511\OpenapiToLaravel\Console\ValidateRoutesCommand;
use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMatch;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;

describe('ValidateRoutesCommand All Routes Display', function (): void {
    beforeEach(function (): void {
        $this->validateCommand = new ValidateRoutesCommand;
    });

    it('should display all routes and endpoints when no filter is applied', function (): void {
        // Create test routes and endpoints
        $successfulRoute = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        $missingDocRoute = new LaravelRoute(
            uri: 'api/posts',
            methods: ['POST'],
            name: 'posts.store',
            action: 'App\Http\Controllers\PostController@store',
            middleware: ['api']
        );

        $successfulEndpoint = new EndpointDefinition(
            path: '/api/users',
            method: 'GET',
            operationId: 'getUsers'
        );

        $missingImplEndpoint = new EndpointDefinition(
            path: '/api/comments',
            method: 'GET',
            operationId: 'getComments'
        );

        // Create matches with assigned mismatches
        $missingDocMismatch = RouteMismatch::missingDocumentation($missingDocRoute);
        $missingImplMismatch = RouteMismatch::missingImplementation($missingImplEndpoint);

        $missingDocMatch = RouteMatch::createMissingDocumentation($missingDocRoute);
        $missingDocMatch->mismatch = $missingDocMismatch;

        $missingImplMatch = RouteMatch::createMissingImplementation($missingImplEndpoint);
        $missingImplMatch->mismatch = $missingImplMismatch;

        // Create validation result with all routes/endpoints (no filter applied)
        $validationResult = new ValidationResult(
            isValid: false,
            mismatches: [
                $missingDocMismatch,
                $missingImplMismatch,
            ],
            warnings: [],
            statistics: [
                'total_routes' => 2,
                'total_endpoints' => 2,
                'covered_routes' => 1,
                'covered_endpoints' => 1,
            ],
            matches: [
                RouteMatch::createMatch($successfulRoute, $successfulEndpoint),
                $missingDocMatch,
                $missingImplMatch,
            ]
        );

        // Use reflection to access private method
        $reflection = new ReflectionClass($this->validateCommand);
        $buildTableDataMethod = $reflection->getMethod('buildTableData');

        $tableData = $buildTableDataMethod->invoke($this->validateCommand, $validationResult);

        // Should have 3 rows: 1 successful match + 2 mismatches
        expect($tableData)->toHaveCount(3);

        // Check that all items are present
        $signatures = array_map(fn (array $row): string => $row[0] . ':' . $row[1], $tableData);

        expect($signatures)->toContain('GET:/api/users')      // Successful match
            ->and($signatures)->toContain('POST:/api/posts')   // Missing doc
            ->and($signatures)->toContain('GET:/api/comments'); // Missing impl

        // Check status indicators (column 4 now, was 5)
        $statuses = array_column($tableData, 4);
        expect($statuses)->toContain('')                      // Successful match (empty status)
            ->and($statuses)->toContain('✗ Missing Doc')      // Missing documentation
            ->and($statuses)->toContain('✗ Missing Impl');    // Missing implementation
    });

    it('should fall back to mismatch-only display when filter is applied', function (): void {
        // Create test route and endpoint
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        // Create validation result with filtered matches
        $mismatch = RouteMismatch::missingDocumentation($route);
        $match = RouteMatch::createMissingDocumentation($route);
        $match->mismatch = $mismatch;

        $validationResult = new ValidationResult(
            isValid: false,
            mismatches: [$mismatch],
            warnings: [],
            statistics: ['total_routes' => 1],
            matches: [$match]
        );

        // Use reflection to access private method
        $reflection = new ReflectionClass($this->validateCommand);
        $buildTableDataMethod = $reflection->getMethod('buildTableData');

        $tableData = $buildTableDataMethod->invoke($this->validateCommand, $validationResult);

        // Should only have 1 row (only mismatches shown when filtered)
        expect($tableData)->toHaveCount(1);
        expect($tableData[0][0])->toBe('GET');
        expect($tableData[0][1])->toBe('/api/users');
        expect($tableData[0][4])->toBe('✗ Missing Doc'); // Status is now column 4
    });

    it('should show proper source indicators', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users',
            method: 'GET',
            operationId: 'getUsers'
        );

        $routeOnlyRoute = new LaravelRoute(
            uri: 'api/posts',
            methods: ['POST'],
            name: 'posts.store',
            action: 'App\Http\Controllers\PostController@store',
            middleware: ['api']
        );

        $endpointOnlyEndpoint = new EndpointDefinition(
            path: '/api/comments',
            method: 'GET',
            operationId: 'getComments'
        );

        $missingDocMismatch = RouteMismatch::missingDocumentation($routeOnlyRoute);
        $missingImplMismatch = RouteMismatch::missingImplementation($endpointOnlyEndpoint);

        $missingDocMatch = RouteMatch::createMissingDocumentation($routeOnlyRoute);
        $missingDocMatch->mismatch = $missingDocMismatch;

        $missingImplMatch = RouteMatch::createMissingImplementation($endpointOnlyEndpoint);
        $missingImplMatch->mismatch = $missingImplMismatch;

        $validationResult = new ValidationResult(
            isValid: false,
            mismatches: [
                $missingDocMismatch,
                $missingImplMismatch,
            ],
            warnings: [],
            statistics: [],
            matches: [
                RouteMatch::createMatch($route, $endpoint),
                $missingDocMatch,
                $missingImplMatch,
            ]
        );

        $reflection = new ReflectionClass($this->validateCommand);
        $buildTableDataMethod = $reflection->getMethod('buildTableData');

        $tableData = $buildTableDataMethod->invoke($this->validateCommand, $validationResult);

        expect($tableData)->toHaveCount(3);

        // Find each row by signature and check Laravel/OpenAPI columns
        // Columns are now: Method(0), Path(1), Laravel(2), OpenAPI(3), Status(4)
        foreach ($tableData as $row) {
            $signature = $row[0] . ':' . $row[1];
            match ($signature) {
                'GET:/api/users' => expect($row[2])->toBe('✓')      // Both route and endpoint
                    ->and($row[3])->toBe('✓')
                    ->and($row[4])->toBe(''),                       // Empty status for match
                'POST:/api/posts' => expect($row[2])->toBe('✓')     // Route only
                    ->and($row[3])->toBe('')
                    ->and($row[4])->toBe('✗ Missing Doc'),
                'GET:/api/comments' => expect($row[2])->toBe('')    // Endpoint only
                    ->and($row[3])->toBe('✓')
                    ->and($row[4])->toBe('✗ Missing Impl'),
                default => null, // For any unexpected signatures
            };
        }
    });
});
