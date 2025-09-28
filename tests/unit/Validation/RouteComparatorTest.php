<?php

use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Validation\RouteComparator;

describe('RouteComparator', function (): void {
    beforeEach(function (): void {
        $this->comparator = new RouteComparator;
    });

    it('performs exact matches correctly', function (): void {
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

        expect($this->comparator->exactMatch($route, $endpoint))->toBeTrue();
    });

    it('rejects non-matching routes', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            pathParameters: ['id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/posts/{id}',
            method: 'GET',
            operationId: 'getPost'
        );

        expect($this->comparator->exactMatch($route, $endpoint))->toBeFalse();
    });

    it('calculates path similarity accurately', function (): void {
        $similarity1 = $this->comparator->calculatePathSimilarity('/api/users/{id}', '/api/users/{id}');
        $similarity2 = $this->comparator->calculatePathSimilarity('/api/users/{id}', '/api/users/{user_id}');
        $similarity3 = $this->comparator->calculatePathSimilarity('/api/users', '/api/posts');

        expect($similarity1)->toBe(1.0)
            ->and($similarity2)->toBeGreaterThan(0.8)
            ->and($similarity3)->toBeLessThanOrEqual(0.5);
    });

    it('calculates method similarity', function (): void {
        $similarity1 = $this->comparator->calculateMethodSimilarity('GET', 'GET');
        $similarity2 = $this->comparator->calculateMethodSimilarity('GET', 'POST');
        $similarity3 = $this->comparator->calculateMethodSimilarity('get', 'GET');

        expect($similarity1)->toBe(1.0)
            ->and($similarity2)->toBe(0.0)
            ->and($similarity3)->toBe(1.0);
    });

    it('detects similar parameters', function (): void {
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

        expect($this->comparator->haveSimilarParameters($route, $endpoint))->toBeTrue();
    });

    it('rejects different parameter counts', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}/posts',
            methods: ['GET'],
            name: 'users.posts.index',
            action: 'App\Http\Controllers\PostController@index',
            pathParameters: ['id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{id}/posts/{post_id}',
            method: 'GET',
            operationId: 'getUserPost'
        );

        expect($this->comparator->haveSimilarParameters($route, $endpoint))->toBeFalse();
    });

    it('finds similar endpoints', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            pathParameters: ['id']
        );

        $endpoints = [
            new EndpointDefinition(
                path: '/api/users/{user_id}',
                method: 'GET',
                operationId: 'getUser'
            ),
            new EndpointDefinition(
                path: '/api/posts/{id}',
                method: 'GET',
                operationId: 'getPost'
            ),
        ];

        $similarities = $this->comparator->findSimilarEndpoints($route, $endpoints);

        expect($similarities)->not->toBeEmpty()
            ->and($similarities[0]['similarity'])->toBeGreaterThan(0.8);
    });

    it('generates helpful matching suggestions', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users/{id}',
            methods: ['GET'],
            name: 'users.show',
            action: 'App\Http\Controllers\UserController@show',
            pathParameters: ['id']
        );

        $endpoint = new EndpointDefinition(
            path: '/api/users/{id}',
            method: 'POST',
            operationId: 'updateUser'
        );

        $suggestions = $this->comparator->generateMatchingSuggestions($route, $endpoint);

        expect($suggestions)->not->toBeEmpty()
            ->and($suggestions[0])->toContain('HTTP method');
    });

    it('creates consistent fingerprints', function (): void {
        $fingerprint1 = $this->comparator->createFingerprint('GET', '/api/users/{id}');
        $fingerprint2 = $this->comparator->createFingerprint('get', '/api/users/{id}/');
        $fingerprint3 = $this->comparator->createFingerprint('POST', '/api/users/{id}');

        expect($fingerprint1)->toBe($fingerprint2)
            ->and($fingerprint1)->not->toBe($fingerprint3);
    });

    it('performs efficient batch comparisons', function (): void {
        $routes = [
            new LaravelRoute(
                uri: 'api/users',
                methods: ['GET'],
                name: 'users.index',
                action: 'App\Http\Controllers\UserController@index'
            ),
            new LaravelRoute(
                uri: 'api/posts',
                methods: ['GET'],
                name: 'posts.index',
                action: 'App\Http\Controllers\PostController@index'
            ),
        ];

        $endpoints = [
            new EndpointDefinition(
                path: '/api/users',
                method: 'GET',
                operationId: 'getUsers'
            ),
            new EndpointDefinition(
                path: '/api/categories',
                method: 'GET',
                operationId: 'getCategories'
            ),
        ];

        $result = $this->comparator->batchCompare($routes, $endpoints);

        expect($result['matches'])->toHaveCount(1)
            ->and($result['unmatched_routes'])->toHaveCount(1)
            ->and($result['unmatched_endpoints'])->toHaveCount(1);
    });
});
