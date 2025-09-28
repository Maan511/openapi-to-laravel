<?php

use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Maan511\OpenapiToLaravel\Validation\RouteComparator;

describe('Route Validation Feasibility', function (): void {
    it('can create Laravel route model', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            middleware: ['api', 'auth'],
            pathParameters: ['id']
        );

        expect($route->getSignature())->toBe('GET:/api/users/{id}')
            ->and($route->hasPathParameters())->toBeTrue()
            ->and($route->isApiRoute())->toBeTrue();
    });

    it('can create route mismatch objects', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show'
        );

        $mismatch = RouteMismatch::missingDocumentation($route);

        expect($mismatch->type)->toBe(RouteMismatch::TYPE_MISSING_DOCUMENTATION)
            ->and($mismatch->path)->toBe('/api/users/{id}')
            ->and($mismatch->method)->toBe('GET')
            ->and($mismatch->isError())->toBeTrue();
    });

    it('can create validation results', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index'
        );

        $mismatches = [RouteMismatch::missingDocumentation($route)];
        $result = ValidationResult::failed($mismatches, [], ['total_routes' => 1]);

        expect($result->isValid)->toBeFalse()
            ->and($result->getMismatchCount())->toBe(1)
            ->and($result->statistics['total_routes'])->toBe(1);
    });

    it('can compare routes and endpoints', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            pathParameters: ['id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{id}',
            method: 'GET',
            operationId: 'getUser'
        );

        $comparator = new RouteComparator;

        expect($comparator->exactMatch($route, $endpoint))->toBeTrue();
    });

    it('can calculate path similarity', function (): void {
        $comparator = new RouteComparator;

        $similarity1 = $comparator->calculatePathSimilarity('/api/users/{id}', '/api/users/{id}');
        $similarity2 = $comparator->calculatePathSimilarity('/api/users/{id}', '/api/users/{user_id}');
        $similarity3 = $comparator->calculatePathSimilarity('/api/users', '/api/posts');

        expect($similarity1)->toBe(1.0)
            ->and($similarity2)->toBeGreaterThan(0.8)
            ->and($similarity3)->toBeLessThanOrEqual(0.5);
    });

    it('can detect parameter variations', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{user_id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            pathParameters: ['user_id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{userId}',
            method: 'GET',
            operationId: 'getUser'
        );

        $comparator = new RouteComparator;

        expect($comparator->haveSimilarParameters($route, $endpoint))->toBeTrue();
    });

    it('can merge validation results', function (): void {
        $result1 = ValidationResult::success(['total_routes' => 5]);

        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['POST'],
            name: 'users.store',
            action: 'App\Http\Controllers\UserController@store'
        );

        $result2 = ValidationResult::failed([RouteMismatch::missingDocumentation($route)], [], ['total_endpoints' => 3]);

        $merged = $result1->merge($result2);

        expect($merged->isValid)->toBeFalse()
            ->and($merged->getMismatchCount())->toBe(1)
            ->and($merged->statistics['total_routes'])->toBe(5)
            ->and($merged->statistics['total_endpoints'])->toBe(3);
    });
});
