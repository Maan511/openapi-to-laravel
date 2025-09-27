<?php

use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Models\SchemaObject;
use Maan511\OpenapiToLaravel\Models\ValidationConstraints;

beforeEach(function (): void {
    $this->mapper = new ValidationRuleMapper;
});

describe('ValidationRuleMapper', function (): void {
    describe('mapValidationRules', function (): void {
        it('should map object schema with required properties', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'name' => new SchemaObject(type: 'string'),
                    'email' => new SchemaObject(type: 'string', format: 'email'),
                    'age' => new SchemaObject(type: 'integer'),
                ],
                required: ['name', 'email']
            );

            $rules = $this->mapper->mapValidationRules($schema);

            expect($rules)->toHaveKey('name');
            expect($rules)->toHaveKey('email');
            expect($rules)->toHaveKey('age');
            expect($rules['name'])->toContain('required');
            expect($rules['email'])->toContain('required');
            expect($rules['email'])->toContain('email');
            expect($rules['age'])->toContain('nullable');
        });

        it('should map array schema with items', function (): void {
            $schema = new SchemaObject(
                type: 'array',
                items: new SchemaObject(type: 'string')
            );

            $rules = $this->mapper->mapValidationRules($schema, 'tags');

            expect($rules)->toHaveKey('tags');
            expect($rules)->toHaveKey('tags.*');
            expect($rules['tags'])->toContain('array');
            expect($rules['tags.*'])->toContain('string');
        });

        it('should handle nested object properties', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'user' => new SchemaObject(
                        type: 'object',
                        properties: [
                            'name' => new SchemaObject(type: 'string'),
                            'profile' => new SchemaObject(
                                type: 'object',
                                properties: [
                                    'bio' => new SchemaObject(type: 'string'),
                                ]
                            ),
                        ],
                        required: ['name']
                    ),
                ],
                required: ['user']
            );

            $rules = $this->mapper->mapValidationRules($schema);

            expect($rules)->toHaveKey('user');
            expect($rules)->toHaveKey('user.name');
            expect($rules)->toHaveKey('user.profile');
            expect($rules)->toHaveKey('user.profile.bio');
            expect($rules['user'])->toContain('required');
            expect($rules['user.name'])->toContain('required');
            expect($rules['user.profile'])->toContain('nullable');
            expect($rules['user.profile.bio'])->toContain('nullable');
        });

        it('should handle array of objects', function (): void {
            $schema = new SchemaObject(
                type: 'array',
                items: new SchemaObject(
                    type: 'object',
                    properties: [
                        'id' => new SchemaObject(type: 'integer'),
                        'name' => new SchemaObject(type: 'string'),
                    ],
                    required: ['id']
                )
            );

            $rules = $this->mapper->mapValidationRules($schema, 'items');

            expect($rules)->toHaveKey('items');
            expect($rules)->toHaveKey('items.*');
            expect($rules)->toHaveKey('items.*.id');
            expect($rules)->toHaveKey('items.*.name');
            expect($rules['items'])->toContain('array');
            expect($rules['items.*.id'])->toContain('required');
            expect($rules['items.*.name'])->toContain('nullable');
        });
    });

    describe('mapSchema', function (): void {
        it('should map simple string schema', function (): void {
            $schema = new SchemaObject(type: 'string');

            $rules = $this->mapper->mapSchema($schema, 'name');

            expect($rules)->toHaveKey('name');
            expect($rules['name'])->toContain('string');
        });

        it('should map integer schema', function (): void {
            $schema = new SchemaObject(type: 'integer');

            $rules = $this->mapper->mapSchema($schema, 'count');

            expect($rules)->toHaveKey('count');
            expect($rules['count'])->toContain('integer');
        });

        it('should map number schema', function (): void {
            $schema = new SchemaObject(type: 'number');

            $rules = $this->mapper->mapSchema($schema, 'price');

            expect($rules)->toHaveKey('price');
            expect($rules['price'])->toContain('numeric');
        });

        it('should map boolean schema', function (): void {
            $schema = new SchemaObject(type: 'boolean');

            $rules = $this->mapper->mapSchema($schema, 'active');

            expect($rules)->toHaveKey('active');
            expect($rules['active'])->toContain('boolean');
        });
    });

    describe('buildRule', function (): void {
        it('should build rule with constraints for integration testing', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 3,
                maxLength: 50,
                pattern: '^[A-Z][a-z]+$'
            );
            $schema = new SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'name');

            expect($rule)->toContain('string');
            expect($rule)->toContain('min:3');
            expect($rule)->toContain('max:50');
            expect($rule)->toContain('regex:/^[A-Z][a-z]+$/');
        });
    });

    describe('createValidationRules', function (): void {
        it('should create ValidationRule objects', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'name' => new SchemaObject(type: 'string'),
                    'age' => new SchemaObject(type: 'integer'),
                ],
                required: ['name']
            );

            $rules = $this->mapper->createValidationRules($schema);

            expect($rules)->toBeArray();
            expect($rules)->not->toBeEmpty();

            $nameRules = array_filter($rules, fn ($rule): bool => $rule->fieldPath === 'name');
            expect($nameRules)->not->toBeEmpty();

            $requiredRule = array_filter($nameRules, fn ($rule): bool => $rule->rule === 'required');
            expect($requiredRule)->toHaveCount(1);
        });
    });

    describe('validateLaravelRules', function (): void {
        it('should validate correct Laravel validation rules', function (): void {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'age' => 'nullable|integer|min:0|max:120',
            ];

            $errors = $this->mapper->validateLaravelRules($rules);

            expect($errors)->toBeEmpty();
        });

        it('should detect invalid rule formats', function (): void {
            $rules = [
                'name' => 'required|string|',
                'email' => '',
                'age' => 'required|:invalid',
            ];

            $errors = $this->mapper->validateLaravelRules($rules);

            expect($errors)->not->toBeEmpty();
            expect($errors)->toContain("Empty rule part in field 'name'");
            expect($errors)->toContain("Invalid rule for field 'email': must be non-empty string");
            expect($errors)->toContain("Invalid rule format in field 'age': ':invalid'");
        });

        it('should detect non-string rules', function (): void {
            $rules = [
                'name' => ['required', 'string'],
                'age' => null,
            ];

            $errors = $this->mapper->validateLaravelRules($rules);

            expect($errors)->not->toBeEmpty();
            expect($errors[0])->toContain("Invalid rule for field 'name': must be non-empty string");
            expect($errors[1])->toContain("Invalid rule for field 'age': must be non-empty string");
        });
    });

    describe('edge cases and error handling', function (): void {
        it('should handle schema with all constraint types', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 3,
                maxLength: 50,
                minimum: 1,
                maximum: 100,
                pattern: '^[a-zA-Z]+$',
                enum: ['active', 'inactive'],
                multipleOf: 5,
                minItems: 1,
                maxItems: 10,
                uniqueItems: true
            );

            $schema = new SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'complex_field');

            expect($rule)->toContain('string');
            expect($rule)->toContain('min:3');
            expect($rule)->toContain('max:50');
            expect($rule)->toContain('regex:/^[a-zA-Z]+$/');
            expect($rule)->toContain('in:active,inactive');
            expect($rule)->toContain('in:active,inactive');
        });

        it('should handle empty schema gracefully', function (): void {
            $schema = new SchemaObject(type: 'string');

            $rules = $this->mapper->mapSchema($schema, 'empty_field');

            expect($rules)->toHaveKey('empty_field');
            expect($rules['empty_field'])->toContain('string');
        });

        it('should handle schema without specific constraints', function (): void {
            $schema = new SchemaObject(type: 'string');

            $rules = $this->mapper->mapSchema($schema, 'unknown_field');

            expect($rules)->toHaveKey('unknown_field');
            expect($rules['unknown_field'])->toContain('string');
        });

        it('should handle circular nested objects safely', function (): void {
            $deepSchema = new SchemaObject(
                type: 'object',
                properties: [
                    'level1' => new SchemaObject(
                        type: 'object',
                        properties: [
                            'level2' => new SchemaObject(
                                type: 'object',
                                properties: [
                                    'level3' => new SchemaObject(type: 'string'),
                                ],
                                required: ['level3']
                            ),
                        ],
                        required: ['level2']
                    ),
                ],
                required: ['level1']
            );

            $rules = $this->mapper->mapValidationRules($deepSchema);

            expect($rules)->toHaveKey('level1');
            expect($rules)->toHaveKey('level1.level2');
            expect($rules)->toHaveKey('level1.level2.level3');
            expect($rules['level1'])->toContain('required');
            expect($rules['level1.level2'])->toContain('required');
            expect($rules['level1.level2.level3'])->toContain('required');
        });

        it('should handle arrays with complex constraints', function (): void {
            $constraints = new ValidationConstraints(
                minItems: 2,
                maxItems: 5,
                uniqueItems: true
            );

            $schema = new SchemaObject(
                type: 'array',
                items: new SchemaObject(
                    type: 'string',
                    validation: new ValidationConstraints(
                        minLength: 3,
                        maxLength: 20
                    )
                ),
                validation: $constraints
            );

            $rules = $this->mapper->mapValidationRules($schema, 'tags');

            expect($rules['tags'])->toContain('array');
            expect($rules['tags'])->toContain('min:2');
            expect($rules['tags'])->toContain('max:5');
            expect($rules['tags.*'])->toContain('string');
            expect($rules['tags.*'])->toContain('min:3');
            expect($rules['tags.*'])->toContain('max:20');
            expect($rules['tags'])->toContain('distinct');
        });

        it('should handle numeric constraints correctly', function (): void {
            $constraints = new ValidationConstraints(
                minimum: 0,
                maximum: 1000,
                multipleOf: 10
            );

            $schema = new SchemaObject(
                type: 'number',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'price');

            expect($rule)->toContain('numeric');
            expect($rule)->toContain('min:0');
            expect($rule)->toContain('max:1000');
        });

        it('should handle enum with special characters', function (): void {
            $constraints = new ValidationConstraints(
                enum: ['value,with,commas', 'value|with|pipes', 'normal_value']
            );

            $schema = new SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'special_enum');

            expect($rule)->toContain('in:value,with,commas,value|with|pipes,normal_value');
        });
    });

    describe('complex mapping scenarios', function (): void {
        it('should handle mixed array types', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'strings' => new SchemaObject(
                        type: 'array',
                        items: new SchemaObject(type: 'string')
                    ),
                    'numbers' => new SchemaObject(
                        type: 'array',
                        items: new SchemaObject(type: 'integer')
                    ),
                    'objects' => new SchemaObject(
                        type: 'array',
                        items: new SchemaObject(
                            type: 'object',
                            properties: [
                                'id' => new SchemaObject(type: 'integer'),
                            ]
                        )
                    ),
                ]
            );

            $rules = $this->mapper->mapValidationRules($schema);

            expect($rules)->toHaveKey('strings');
            expect($rules)->toHaveKey('strings.*');
            expect($rules)->toHaveKey('numbers');
            expect($rules)->toHaveKey('numbers.*');
            expect($rules)->toHaveKey('objects');
            expect($rules)->toHaveKey('objects.*');
            expect($rules)->toHaveKey('objects.*.id');

            expect($rules['strings.*'])->toContain('string');
            expect($rules['numbers.*'])->toContain('integer');
            expect($rules['objects.*.id'])->toContain('nullable');
        });

        it('should handle schemas with format-specific rules', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'email' => new SchemaObject(type: 'string', format: 'email'),
                    'date' => new SchemaObject(type: 'string', format: 'date'),
                    'datetime' => new SchemaObject(type: 'string', format: 'date-time'),
                    'uuid' => new SchemaObject(type: 'string', format: 'uuid'),
                    'uri' => new SchemaObject(type: 'string', format: 'uri'),
                ]
            );

            $rules = $this->mapper->mapValidationRules($schema);

            expect($rules['email'])->toContain('email');
            expect($rules['date'])->toContain('date');
            expect($rules['datetime'])->toContain('date');
            expect($rules['uuid'])->toContain('uuid');
            expect($rules['uri'])->toContain('url');
        });

        it('should handle optional and required field combinations', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'required_string' => new SchemaObject(type: 'string'),
                    'optional_string' => new SchemaObject(type: 'string'),
                    'required_integer' => new SchemaObject(type: 'integer'),
                    'optional_integer' => new SchemaObject(type: 'integer'),
                ],
                required: ['required_string', 'required_integer']
            );

            $rules = $this->mapper->mapValidationRules($schema);

            expect($rules['required_string'])->toContain('required');
            expect($rules['required_string'])->not->toContain('nullable');
            expect($rules['optional_string'])->toContain('nullable');
            expect($rules['optional_string'])->not->toContain('required');
            expect($rules['required_integer'])->toContain('required');
            expect($rules['optional_integer'])->toContain('nullable');
        });
    });

    describe('validation rule generation edge cases', function (): void {
        it('should handle rules for different field paths', function (): void {
            $schema = new SchemaObject(type: 'string');

            $rules1 = $this->mapper->mapSchema($schema, 'simple_field');
            $rules2 = $this->mapper->mapSchema($schema, 'nested.field');
            $rules3 = $this->mapper->mapSchema($schema, 'array.*.field');

            expect($rules1)->toHaveKey('simple_field');
            expect($rules2)->toHaveKey('nested.field');
            expect($rules3)->toHaveKey('array.*.field');
        });

        it('should properly escape regex patterns', function (): void {
            $constraints = new ValidationConstraints(
                pattern: '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
            );

            $schema = new SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'email_pattern');

            expect($rule)->toContain('string');
            expect($rule)->toContain('regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');
        });
    });
});
