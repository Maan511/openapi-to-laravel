<?php

use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\SchemaObject;

describe('EndpointDefinition', function (): void {
    describe('construction', function (): void {
        it('should create endpoint with required properties', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser'
            );

            expect($endpoint->path)->toBe('/users');
            expect($endpoint->method)->toBe('POST');
            expect($endpoint->operationId)->toBe('createUser');
            expect($endpoint->requestSchema)->toBeNull();
            expect($endpoint->summary)->toBe('');
            expect($endpoint->description)->toBe('');
            expect($endpoint->tags)->toBe([]);
            expect($endpoint->parameters)->toBe([]);
        });

        it('should create endpoint with all properties', function (): void {
            $schema = new SchemaObject(type: 'object');

            $endpoint = new EndpointDefinition(
                path: '/users/{id}',
                method: 'PUT',
                operationId: 'updateUser',
                requestSchema: $schema,
                summary: 'Update a user',
                description: 'Updates an existing user by ID',
                tags: ['users', 'management'],
                parameters: [
                    ['name' => 'id', 'in' => 'path', 'required' => true],
                    ['name' => 'filter', 'in' => 'query', 'required' => false],
                ]
            );

            expect($endpoint->path)->toBe('/users/{id}');
            expect($endpoint->method)->toBe('PUT');
            expect($endpoint->operationId)->toBe('updateUser');
            expect($endpoint->requestSchema)->toBe($schema);
            expect($endpoint->summary)->toBe('Update a user');
            expect($endpoint->description)->toBe('Updates an existing user by ID');
            expect($endpoint->tags)->toBe(['users', 'management']);
            expect($endpoint->parameters)->toHaveCount(2);
        });

        it('should throw exception for empty path', function (): void {
            expect(fn (): \Maan511\OpenapiToLaravel\Models\EndpointDefinition => new EndpointDefinition(
                path: '',
                method: 'GET',
                operationId: 'test'
            ))->toThrow(InvalidArgumentException::class, 'Path cannot be empty');
        });

        it('should throw exception for path not starting with slash', function (): void {
            expect(fn (): \Maan511\OpenapiToLaravel\Models\EndpointDefinition => new EndpointDefinition(
                path: 'users',
                method: 'GET',
                operationId: 'test'
            ))->toThrow(InvalidArgumentException::class, 'Path must start with /');
        });

        it('should throw exception for invalid HTTP method', function (): void {
            expect(fn (): \Maan511\OpenapiToLaravel\Models\EndpointDefinition => new EndpointDefinition(
                path: '/users',
                method: 'INVALID',
                operationId: 'test'
            ))->toThrow(InvalidArgumentException::class);
        });

        it('should throw exception for empty operation ID', function (): void {
            expect(fn (): \Maan511\OpenapiToLaravel\Models\EndpointDefinition => new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: ''
            ))->toThrow(InvalidArgumentException::class, 'Operation ID cannot be empty');
        });

        it('should throw exception for invalid operation ID format', function (): void {
            expect(fn (): \Maan511\OpenapiToLaravel\Models\EndpointDefinition => new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: '123invalid'
            ))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('fromOperation', function (): void {
        it('should create endpoint from operation array', function (): void {
            $schema = new SchemaObject(type: 'object');
            $operation = [
                'operationId' => 'createUser',
                'summary' => 'Create user',
                'description' => 'Creates a new user',
                'tags' => ['users'],
                'parameters' => [['name' => 'test', 'in' => 'query']],
            ];

            $endpoint = EndpointDefinition::fromOperation(
                '/users',
                'post',
                $operation,
                $schema
            );

            expect($endpoint->path)->toBe('/users');
            expect($endpoint->method)->toBe('POST');
            expect($endpoint->operationId)->toBe('createUser');
            expect($endpoint->requestSchema)->toBe($schema);
            expect($endpoint->summary)->toBe('Create user');
            expect($endpoint->description)->toBe('Creates a new user');
            expect($endpoint->tags)->toBe(['users']);
            expect($endpoint->parameters)->toHaveCount(1);
        });

        it('should generate operation ID when missing', function (): void {
            $operation = [];

            $endpoint = EndpointDefinition::fromOperation(
                '/users/{id}',
                'get',
                $operation
            );

            expect($endpoint->operationId)->toBe('getUsersId');
        });

        it('should generate operation ID handling hyphens in path segments', function (): void {
            $operation = [];

            $endpoint = EndpointDefinition::fromOperation(
                '/close-days',
                'get',
                $operation
            );

            expect($endpoint->operationId)->toBe('getCloseDays');
        });

        it('should generate operation ID handling underscores and hyphens in path segments', function (): void {
            $operation = [];

            $endpoint = EndpointDefinition::fromOperation(
                '/user-profiles/detailed_info/{id}',
                'post',
                $operation
            );

            expect($endpoint->operationId)->toBe('postUserProfilesDetailedInfoId');
        });

        it('should handle malformed operation data gracefully', function (): void {
            $operation = [
                'summary' => 123, // Invalid type
                'tags' => 'invalid', // Should be array
                'parameters' => 'invalid', // Should be array
            ];

            $endpoint = EndpointDefinition::fromOperation(
                '/test',
                'get',
                $operation
            );

            expect($endpoint->summary)->toBe('');
            expect($endpoint->tags)->toBe([]);
            expect($endpoint->parameters)->toBe([]);
        });
    });

    describe('getId', function (): void {
        it('should return unique endpoint identifier', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users/{id}',
                method: 'PUT',
                operationId: 'updateUser'
            );

            expect($endpoint->getId())->toBe('PUT_/users/{id}');
        });
    });

    describe('getDisplayName', function (): void {
        it('should return display name', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser'
            );

            expect($endpoint->getDisplayName())->toBe('POST /users');
        });
    });

    describe('hasRequestBody', function (): void {
        it('should return true when request schema exists', function (): void {
            $schema = new SchemaObject(type: 'object');
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                requestSchema: $schema
            );

            expect($endpoint->hasRequestBody())->toBe(true);
        });

        it('should return false when no request schema', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers'
            );

            expect($endpoint->hasRequestBody())->toBe(false);
        });
    });

    describe('hasParameters', function (): void {
        it('should return true when parameters exist', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers',
                parameters: [['name' => 'page', 'in' => 'query']]
            );

            expect($endpoint->hasParameters())->toBe(true);
        });

        it('should return false when no parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers'
            );

            expect($endpoint->hasParameters())->toBe(false);
        });
    });

    describe('getParameterNames', function (): void {
        it('should return parameter names', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers',
                parameters: [
                    ['name' => 'page', 'in' => 'query'],
                    ['name' => 'limit', 'in' => 'query'],
                ]
            );

            expect($endpoint->getParameterNames())->toBe(['page', 'limit']);
        });

        it('should return empty array when no parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers'
            );

            expect($endpoint->getParameterNames())->toBe([]);
        });
    });

    describe('getRequiredParameterNames', function (): void {
        it('should return only required parameter names', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers',
                parameters: [
                    ['name' => 'page', 'in' => 'query', 'required' => false],
                    ['name' => 'api_key', 'in' => 'header', 'required' => true],
                    ['name' => 'filter', 'in' => 'query', 'required' => true],
                ]
            );

            expect($endpoint->getRequiredParameterNames())->toBe(['api_key', 'filter']);
        });

        it('should return empty array when no required parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers',
                parameters: [
                    ['name' => 'page', 'in' => 'query', 'required' => false],
                ]
            );

            expect($endpoint->getRequiredParameterNames())->toBe([]);
        });
    });

    describe('isReadOperation', function (): void {
        it('should return true for read operations', function (): void {
            $methods = ['GET', 'HEAD', 'OPTIONS'];

            foreach ($methods as $method) {
                $endpoint = new EndpointDefinition(
                    path: '/users',
                    method: $method,
                    operationId: 'test'
                );

                expect($endpoint->isReadOperation())->toBe(true);
            }
        });

        it('should return false for write operations', function (): void {
            $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];

            foreach ($methods as $method) {
                $endpoint = new EndpointDefinition(
                    path: '/users',
                    method: $method,
                    operationId: 'test'
                );

                expect($endpoint->isReadOperation())->toBe(false);
            }
        });
    });

    describe('isWriteOperation', function (): void {
        it('should return true for write operations', function (): void {
            $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];

            foreach ($methods as $method) {
                $endpoint = new EndpointDefinition(
                    path: '/users',
                    method: $method,
                    operationId: 'test'
                );

                expect($endpoint->isWriteOperation())->toBe(true);
            }
        });

        it('should return false for read operations', function (): void {
            $methods = ['GET', 'HEAD', 'OPTIONS'];

            foreach ($methods as $method) {
                $endpoint = new EndpointDefinition(
                    path: '/users',
                    method: $method,
                    operationId: 'test'
                );

                expect($endpoint->isWriteOperation())->toBe(false);
            }
        });
    });

    describe('generateFormRequestClassName', function (): void {
        it('should generate class name from operation ID', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser'
            );

            expect($endpoint->generateFormRequestClassName())->toBe('CreateUserRequest');
        });

        it('should append Request suffix when missing', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser'
            );

            expect($endpoint->generateFormRequestClassName())->toBe('CreateUserRequest');
        });

        it('should convert snake_case to PascalCase', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'create_user_profile'
            );

            expect($endpoint->generateFormRequestClassName())->toBe('CreateUserProfileRequest');
        });

        it('should handle camelCase operation IDs', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUserProfile'
            );

            expect($endpoint->generateFormRequestClassName())->toBe('CreateUserProfileRequest');
        });

        it('should generate from path when operation ID is empty', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users/profiles',
                method: 'POST',
                operationId: 'postUsersProfiles'
            );

            expect($endpoint->generateFormRequestClassName())->toBe('PostUsersProfilesRequest');
        });
    });

    describe('getPathParameters', function (): void {
        it('should extract path parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users/{id}/posts/{postId}',
                method: 'GET',
                operationId: 'getUserPosts'
            );

            expect($endpoint->getPathParameters())->toBe(['id', 'postId']);
        });

        it('should return empty array when no path parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers'
            );

            expect($endpoint->getPathParameters())->toBe([]);
        });
    });

    describe('hasPathParameters', function (): void {
        it('should return true when path has parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users/{id}',
                method: 'GET',
                operationId: 'getUser'
            );

            expect($endpoint->hasPathParameters())->toBe(true);
        });

        it('should return false when path has no parameters', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'GET',
                operationId: 'getUsers'
            );

            expect($endpoint->hasPathParameters())->toBe(false);
        });
    });

    describe('getTagsString', function (): void {
        it('should return comma-separated tags', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                tags: ['users', 'management', 'api']
            );

            expect($endpoint->getTagsString())->toBe('users, management, api');
        });

        it('should return empty string when no tags', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser'
            );

            expect($endpoint->getTagsString())->toBe('');
        });
    });

    describe('hasTag', function (): void {
        it('should return true when tag exists', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                tags: ['users', 'management']
            );

            expect($endpoint->hasTag('users'))->toBe(true);
            expect($endpoint->hasTag('management'))->toBe(true);
        });

        it('should return false when tag does not exist', function (): void {
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                tags: ['users', 'management']
            );

            expect($endpoint->hasTag('admin'))->toBe(false);
        });
    });

    describe('toArray', function (): void {
        it('should convert endpoint to array', function (): void {
            $schema = new SchemaObject(type: 'object');
            $endpoint = new EndpointDefinition(
                path: '/users',
                method: 'POST',
                operationId: 'createUser',
                requestSchema: $schema,
                summary: 'Create user',
                description: 'Creates a new user',
                tags: ['users'],
                parameters: [['name' => 'test']]
            );

            $array = $endpoint->toArray();

            expect($array)->toHaveKey('path');
            expect($array)->toHaveKey('method');
            expect($array)->toHaveKey('operationId');
            expect($array)->toHaveKey('summary');
            expect($array)->toHaveKey('description');
            expect($array)->toHaveKey('tags');
            expect($array)->toHaveKey('parameters');
            expect($array)->toHaveKey('requestSchema');
            expect($array['path'])->toBe('/users');
            expect($array['method'])->toBe('POST');
            expect($array['operationId'])->toBe('createUser');
        });
    });
});
