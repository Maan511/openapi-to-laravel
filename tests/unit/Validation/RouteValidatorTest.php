<?php

use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Validation\LaravelRouteCollector;
use Maan511\OpenapiToLaravel\Validation\RouteComparator;
use Maan511\OpenapiToLaravel\Validation\RouteValidator;

describe('RouteValidator', function (): void {
    beforeEach(function (): void {
        $this->routeCollector = Mockery::mock(LaravelRouteCollector::class);
        $this->routeComparator = new RouteComparator;
        $this->openApiParser = Mockery::mock(OpenApiParser::class);

        $this->validator = new RouteValidator(
            $this->routeCollector,
            $this->openApiParser
        );
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('detects missing documentation', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/posts',
            method: 'GET',
            operationId: 'getPosts'
        );

        $result = $this->validator->validateRoutes([$route], [$endpoint]);

        expect($result->isValid)->toBeFalse()
            ->and($result->getMismatchCount())->toBe(2)
            ->and($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_DOCUMENTATION))->toHaveCount(1)
            ->and($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_IMPLEMENTATION))->toHaveCount(1);
    });

    it('detects missing implementation', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users',
            method: 'POST',
            operationId: 'createUser'
        );

        $result = $this->validator->validateRoutes([$route], [$endpoint]);

        expect($result->isValid)->toBeFalse()
            ->and($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_IMPLEMENTATION))->toHaveCount(1);
    });

    it('detects missing documentation and implementation for different methods on same path', function (): void {
        $route1 = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            middleware: ['api'],
            pathParameters: ['id']
        );

        $route2 = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['PUT'],
            name: 'users.update',
            action: 'App\Http\Controllers\UserController@update',
            middleware: ['api'],
            pathParameters: ['id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{id}',
            method: 'POST',
            operationId: 'updateUser'
        );

        $result = $this->validator->validateRoutes([$route1, $route2], [$endpoint]);

        expect($result->isValid)->toBeFalse();
        // Should have 2 missing documentation (GET and PUT) and 1 missing implementation (POST)
        expect($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_DOCUMENTATION))->toHaveCount(2);
        expect($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_IMPLEMENTATION))->toHaveCount(1);
    });

    it('matches routes with different parameter names correctly', function (): void {
        // Routes with different parameter names but same structure should now match
        $route = new LaravelRoute(
            uri: 'api/pospayments/{id}/articles/{articleid}',
            methods: ['PUT'],
            name: 'pospayments.articles.update',
            action: 'App\Http\Controllers\PosPaymentController@updateArticle',
            middleware: ['api'],
            pathParameters: ['id', 'articleid']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/pospayments/{paymentId}/articles/{paymentArticleId}',
            method: 'PUT',
            operationId: 'updatePaymentArticle'
        );

        $result = $this->validator->validateRoutes([$route], [$endpoint]);

        expect($result->isValid)->toBeTrue()
            ->and($result->getMismatchCount())->toBe(0);
    });

    it('still detects actual parameter structure differences', function (): void {
        // Routes with different parameter counts should still be detected as mismatches
        $route = new LaravelRoute(
            uri: 'api/users/{user_id}/posts',
            methods: ['GET'],
            name: 'users.posts.index',
            action: 'App\Http\Controllers\PostController@index',
            middleware: ['api'],
            pathParameters: ['user_id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{userId}/posts/{postId}',
            method: 'GET',
            operationId: 'getUserPost'
        );

        $result = $this->validator->validateRoutes([$route], [$endpoint]);

        expect($result->isValid)->toBeFalse()
            ->and($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_DOCUMENTATION))->toHaveCount(1)
            ->and($result->getMismatchesByType(RouteMismatch::TYPE_MISSING_IMPLEMENTATION))->toHaveCount(1);
    });

    it('passes validation when routes and endpoints match', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            middleware: ['api'],
            pathParameters: ['id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{id}',
            method: 'GET',
            operationId: 'getUser'
        );

        $result = $this->validator->validateRoutes([$route], [$endpoint]);

        expect($result->isValid)->toBeTrue()
            ->and($result->getMismatchCount())->toBe(0);
    });

    it('generates proper statistics', function (): void {
        $routes = [
            new LaravelRoute(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: 'App\Http\Controllers\UserController@index',
                middleware: ['api']
            ),
            new LaravelRoute(
                uri: 'api/posts',
                methods: ['GET'],
                name: 'posts.index',
                action: 'App\Http\Controllers\PostController@index',
                middleware: ['api']
            ),
        ];

        $endpoints = [
            new EndpointDefinition(
                path: '/api/users',
                method: 'GET',
                operationId: 'getUsers'
            ),
        ];

        $result = $this->validator->validateRoutes($routes, $endpoints);

        expect($result->statistics['total_routes'])->toBe(2)
            ->and($result->statistics['covered_routes'])->toBe(1)
            ->and($result->statistics['route_coverage_percentage'])->toBe(50.0)
            ->and($result->statistics['total_endpoints'])->toBe(1)
            ->and($result->statistics['covered_endpoints'])->toBe(1)
            ->and($result->statistics['endpoint_coverage_percentage'])->toBe(100.0)
            ->and($result->statistics['total_mismatches'])->toBe(1)
            ->and($result->statistics['total_coverage_percentage'])->toBe(66.67);
    });

    it('filters routes based on options', function (): void {
        $apiRoute = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'api.users.index',
            action: 'App\Http\Controllers\Api\UserController@index',
            middleware: ['api']
        );

        $webRoute = new LaravelRoute(
            uri: 'users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['web']
        );

        $options = [
            'exclude_middleware' => ['web'],
        ];

        $result = $this->validator->validateRoutes([$apiRoute, $webRoute], [], $options);

        // Both routes are included in the total count, but web route is excluded from validation
        expect($result->statistics['total_routes'])->toBe(2);

        // The web route should be filtered out, so we should have 1 missing documentation (API route)
        $missingDocs = $result->getMismatchesByType(RouteMismatch::TYPE_MISSING_DOCUMENTATION);
        expect($missingDocs)->toHaveCount(1);
        expect($missingDocs[0]->message)->toContain('api/users');
    });

    it('applies include patterns to both routes and endpoints', function (): void {
        // Create routes - one matching pattern, one not
        $matchingRoute = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'api.users.index',
            action: 'App\Http\Controllers\Api\UserController@index',
            middleware: ['api']
        );

        $nonMatchingRoute = new LaravelRoute(
            uri: 'api/posts',
            methods: ['GET'],
            name: 'api.posts.index',
            action: 'App\Http\Controllers\Api\PostController@index',
            middleware: ['api']
        );

        // Create endpoints - one matching pattern, one not
        $matchingEndpoint = new EndpointDefinition(
            path: '/api/users',
            method: 'POST',
            operationId: 'createUser'
        );

        $nonMatchingEndpoint = new EndpointDefinition(
            path: '/api/posts',
            method: 'POST',
            operationId: 'createPost'
        );

        // Test with include pattern that should only match /api/users
        $options = [
            'include_patterns' => ['/api/users*'],
        ];

        $result = $this->validator->validateRoutes(
            [$matchingRoute, $nonMatchingRoute],
            [$matchingEndpoint, $nonMatchingEndpoint],
            $options
        );

        // Should only find mismatches for items matching the pattern
        $mismatches = $result->mismatches;

        // We should have:
        // 1. Missing documentation for matching route (GET /api/users)
        // 2. Missing implementation for matching endpoint (POST /api/users)
        // Non-matching items should be filtered out
        expect($mismatches)->toHaveCount(2);

        $missingDocs = $result->getMismatchesByType(RouteMismatch::TYPE_MISSING_DOCUMENTATION);
        $missingImpl = $result->getMismatchesByType(RouteMismatch::TYPE_MISSING_IMPLEMENTATION);

        expect($missingDocs)->toHaveCount(1);
        expect($missingImpl)->toHaveCount(1);

        // Verify that only /api/users related mismatches are present
        $missingDocsList = array_values($missingDocs);
        $missingImplList = array_values($missingImpl);
        expect($missingDocsList[0]->path)->toContain('/api/users');
        expect($missingImplList[0]->path)->toContain('/api/users');
    });

    it('calculates statistics correctly with filter_types option', function (): void {
        // Create 2 routes and 1 endpoint - will generate multiple mismatch types
        $route1 = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'api.users.index',
            action: 'App\Http\Controllers\Api\UserController@index',
            middleware: ['api']
        );

        $route2 = new LaravelRoute(
            uri: 'api/posts',
            methods: ['GET'],
            name: 'api.posts.index',
            action: 'App\Http\Controllers\Api\PostController@index',
            middleware: ['api']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/products',
            method: 'GET',
            operationId: 'getProducts'
        );

        // Without filter - should have all mismatches
        $resultNoFilter = $this->validator->validateRoutes(
            [$route1, $route2],
            [$endpoint],
            []
        );

        // Should have 2 missing documentation + 1 missing implementation = 3 total
        expect($resultNoFilter->getMismatchCount())->toBe(3)
            ->and($resultNoFilter->statistics['total_mismatches'])->toBe(3)
            ->and($resultNoFilter->statistics['total_routes'])->toBe(2)
            ->and($resultNoFilter->statistics['total_endpoints'])->toBe(1)
            ->and($resultNoFilter->statistics['covered_routes'])->toBe(0)
            ->and($resultNoFilter->statistics['covered_endpoints'])->toBe(0);

        // With filter for only missing-implementation
        $resultFiltered = $this->validator->validateRoutes(
            [$route1, $route2],
            [$endpoint],
            ['filter_types' => [RouteMismatch::TYPE_MISSING_IMPLEMENTATION]]
        );

        // Should have only 1 missing implementation (filtered)
        expect($resultFiltered->getMismatchCount())->toBe(1)
            ->and($resultFiltered->mismatches)->toHaveCount(1);

        // Get the first mismatch
        $firstMismatch = array_values($resultFiltered->mismatches)[0];
        expect($firstMismatch->type)->toBe(RouteMismatch::TYPE_MISSING_IMPLEMENTATION);

        // Statistics should reflect ONLY the filtered mismatches
        // When filtering by missing-implementation only, statistics are based on filtered mismatches
        expect($resultFiltered->statistics['total_mismatches'])->toBe(1)
            ->and($resultFiltered->statistics['total_routes'])->toBe(0) // No missing-documentation in filtered results
            ->and($resultFiltered->statistics['total_endpoints'])->toBe(1) // 1 missing-implementation
            // With only missing-implementation filtered:
            // - covered_routes = 0 (no routes in filtered results)
            // - covered_endpoints = 0 (all filtered endpoints are missing implementation)
            ->and($resultFiltered->statistics['covered_routes'])->toBe(0)
            ->and($resultFiltered->statistics['covered_endpoints'])->toBe(0);
    });
});
