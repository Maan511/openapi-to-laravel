<?php

use Maan511\OpenapiToLaravel\Models\LaravelRoute;

describe('LaravelRoute Parameter Normalization', function (): void {
    it('normalizes parameter names to generic placeholders', function (): void {
        $route = new LaravelRoute(
            uri: 'api/pospayments/{id}/articles/{articleid}',
            methods: ['PUT'],
            name: 'pospayments.articles.update',
            action: 'App\Http\Controllers\PosPaymentController@updateArticle',
            middleware: ['api'],
            pathParameters: ['id', 'articleid']
        );

        expect($route->getSignature())->toBe('PUT:/api/pospayments/{id}/articles/{articleid}')
            ->and($route->getNormalizedSignature())->toBe('PUT:/api/pospayments/{param1}/articles/{param2}');
    });

    it('normalizes routes with different parameter names to same signature', function (): void {
        $route1 = new LaravelRoute(
            uri: 'api/pospayments/{id}/articles/{articleid}',
            methods: ['PUT'],
            name: 'pospayments.articles.update',
            action: 'App\Http\Controllers\PosPaymentController@updateArticle',
            middleware: ['api'],
            pathParameters: ['id', 'articleid']
        );

        $route2 = new LaravelRoute(
            uri: 'api/pospayments/{paymentId}/articles/{paymentArticleId}',
            methods: ['PUT'],
            name: 'pospayments.articles.update2',
            action: 'App\Http\Controllers\PosPaymentController@updateArticle2',
            middleware: ['api'],
            pathParameters: ['paymentId', 'paymentArticleId']
        );

        expect($route1->getNormalizedSignature())->toBe($route2->getNormalizedSignature());
    });

    it('handles routes without parameters', function (): void {
        $route = new LaravelRoute(
            uri: 'api/users',
            methods: ['GET'],
            name: 'users.index',
            action: 'App\Http\Controllers\UserController@index',
            middleware: ['api']
        );

        expect($route->getSignature())->toBe('GET:/api/users')
            ->and($route->getNormalizedSignature())->toBe('GET:/api/users');
    });
});
