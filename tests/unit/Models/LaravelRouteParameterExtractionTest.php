<?php

use Maan511\OpenapiToLaravel\Models\LaravelRoute;

describe('LaravelRoute Path Parameter Extraction Bug Fix', function (): void {
    it('should extract parameters from all real-world route patterns', function (): void {
        $testCases = [
            // Routes from the user's output that were showing empty parameters
            ['api/sales-order-fulfillment-materials/{sales_order_fulfillment_material}', ['sales_order_fulfillment_material']],
            ['api/sales-order-fulfillments/{sales_order_fulfillment}', ['sales_order_fulfillment']],
            ['api/sales-orders/{salesOrder}/cancel', ['salesOrder']],
            ['api/sales-order-materials/{sales_order_material}', ['sales_order_material']],
            ['api/sales-orders/{sales_order}', ['sales_order']],

            // Additional edge cases
            ['api/users/{id?}', ['id']],  // Optional parameter
            ['api/users/{id??}', ['id']], // Double optional parameter
            ['api/posts/{postId}/comments/{commentId?}', ['postId', 'commentId']], // Mixed required/optional
            ['api/simple', []], // No parameters
            ['api/complex/{param1}/sub/{param2}/items/{param3?}', ['param1', 'param2', 'param3']], // Multiple parameters
        ];

        foreach ($testCases as [$uri, $expectedParams]) {
            // Test using fromLaravelRoute method (the real-world usage)
            $mockRoute = Mockery::mock('Illuminate\Routing\Route');
            $mockRoute->shouldReceive('uri')->andReturn($uri);
            $mockRoute->shouldReceive('methods')->andReturn(['GET']);
            $mockRoute->shouldReceive('getName')->andReturn('test.route');
            $mockRoute->shouldReceive('getActionName')->andReturn('TestController@test');
            $mockRoute->shouldReceive('gatherMiddleware')->andReturn(['api']);
            $mockRoute->shouldReceive('getDomain')->andReturn(null);

            $laravelRoute = LaravelRoute::fromLaravelRoute($mockRoute);

            expect($laravelRoute->pathParameters)
                ->toBe($expectedParams, "Failed for URI: $uri")
                ->and($laravelRoute->hasPathParameters())
                ->toBe($expectedParams !== [], "hasPathParameters() failed for URI: $uri");
        }
    });

    it('should handle edge cases correctly', function (): void {
        // Test parameter cleaning (removing optional markers)
        $reflection = new ReflectionClass(LaravelRoute::class);
        $method = $reflection->getMethod('extractPathParameters');

        $testCases = [
            ['api/users/{id}', ['id']],
            ['api/users/{id?}', ['id']],
            ['api/users/{id??}', ['id']],
            ['api/posts/{postId}/comments/{commentId?}/replies/{replyId??}', ['postId', 'commentId', 'replyId']],
        ];

        foreach ($testCases as [$uri, $expectedParams]) {
            $actualParams = $method->invoke(null, $uri);
            expect($actualParams)->toBe($expectedParams, "extractPathParameters failed for URI: $uri");
        }
    });
});
