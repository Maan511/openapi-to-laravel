<?php

beforeEach(function () {
    $this->mapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
});

describe('ValidationRuleMapper', function () {
    describe('mapValidationRules', function () {
        it('should map object schema with required properties', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'email' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string', format: 'email'),
                    'age' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'integer'),
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

        it('should map array schema with items', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'array',
                items: new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')
            );

            $rules = $this->mapper->mapValidationRules($schema, 'tags');

            expect($rules)->toHaveKey('tags');
            expect($rules)->toHaveKey('tags.*');
            expect($rules['tags'])->toContain('array');
            expect($rules['tags.*'])->toContain('string');
        });

        it('should handle nested object properties', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'user' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                        type: 'object',
                        properties: [
                            'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                            'profile' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                                type: 'object',
                                properties: [
                                    'bio' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
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

        it('should handle array of objects', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'array',
                items: new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                    type: 'object',
                    properties: [
                        'id' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'integer'),
                        'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
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

    describe('mapSchema', function () {
        it('should map simple string schema', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string');

            $rules = $this->mapper->mapSchema($schema, 'name');

            expect($rules)->toHaveKey('name');
            expect($rules['name'])->toContain('string');
        });

        it('should map integer schema', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'integer');

            $rules = $this->mapper->mapSchema($schema, 'count');

            expect($rules)->toHaveKey('count');
            expect($rules['count'])->toContain('integer');
        });

        it('should map number schema', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'number');

            $rules = $this->mapper->mapSchema($schema, 'price');

            expect($rules)->toHaveKey('price');
            expect($rules['price'])->toContain('numeric');
        });

        it('should map boolean schema', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'boolean');

            $rules = $this->mapper->mapSchema($schema, 'active');

            expect($rules)->toHaveKey('active');
            expect($rules['active'])->toContain('boolean');
        });
    });

    describe('buildRule', function () {
        it('should build rule with constraints for integration testing', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 3,
                maxLength: 50,
                pattern: '^[A-Z][a-z]+$'
            );
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
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

    describe('createValidationRules', function () {
        it('should create ValidationRule objects', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'age' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'integer'),
                ],
                required: ['name']
            );

            $rules = $this->mapper->createValidationRules($schema);

            expect($rules)->toBeArray();
            expect($rules)->not->toBeEmpty();

            $nameRules = array_filter($rules, fn ($rule) => $rule->fieldPath === 'name');
            expect($nameRules)->not->toBeEmpty();

            $requiredRule = array_filter($nameRules, fn ($rule) => $rule->rule === 'required');
            expect($requiredRule)->toHaveCount(1);
        });
    });

    describe('validateLaravelRules', function () {
        it('should validate correct Laravel validation rules', function () {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'age' => 'nullable|integer|min:0|max:120',
            ];

            $errors = $this->mapper->validateLaravelRules($rules);

            expect($errors)->toBeEmpty();
        });

        it('should detect invalid rule formats', function () {
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

        it('should detect non-string rules', function () {
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

});
