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

    it('detects method mismatches', function (): void {
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

        expect($result->isValid)->toBeFalse()
            ->and($result->getMismatchesByType(RouteMismatch::TYPE_METHOD_MISMATCH))->toHaveCount(1);
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
});
