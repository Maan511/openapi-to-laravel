<?php

use Maan511\OpenapiToLaravel\Models\EndpointDefinition;

describe('EndpointDefinition Parameter Normalization', function (): void {
    it('normalizes parameter names to generic placeholders', function (): void {
        $endpoint = new EndpointDefinition(
            path: '/api/pospayments/{paymentId}/articles/{paymentArticleId}',
            method: 'PUT',
            operationId: 'updatePaymentArticle'
        );

        expect($endpoint->getNormalizedSignature())->toBe('PUT:/api/pospayments/{param1}/articles/{param2}');
    });

    it('normalizes endpoints with different parameter names to same signature', function (): void {
        $endpoint1 = new EndpointDefinition(
            path: '/api/pospayments/{id}/articles/{articleid}',
            method: 'PUT',
            operationId: 'updatePaymentArticle1'
        );

        $endpoint2 = new EndpointDefinition(
            path: '/api/pospayments/{paymentId}/articles/{paymentArticleId}',
            method: 'PUT',
            operationId: 'updatePaymentArticle2'
        );

        expect($endpoint1->getNormalizedSignature())->toBe($endpoint2->getNormalizedSignature());
    });

    it('handles endpoints without parameters', function (): void {
        $endpoint = new EndpointDefinition(
            path: '/api/users',
            method: 'GET',
            operationId: 'getUsers'
        );

        expect($endpoint->getNormalizedSignature())->toBe('GET:/api/users');
    });
});
