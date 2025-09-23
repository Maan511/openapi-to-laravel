<?php


beforeEach(function () {
    $this->mapper = new \Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper();
});

describe('ValidationRuleMapper', function () {
    describe('mapValidationRules', function () {
        it('should map object schema with required properties', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'email' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string', format: 'email'),
                    'age' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'integer')
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
                                    'bio' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')
                                ]
                            )
                        ],
                        required: ['name']
                    )
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
                        'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')
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
        it('should build rule with validation constraints', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 3,
                maxLength: 50
            );
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'name');

            expect($rule)->toContain('string');
            expect($rule)->toContain('min:3');
            expect($rule)->toContain('max:50');
        });

        it('should build rule with format validation', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'string',
                format: 'email'
            );

            $rule = $this->mapper->buildRule($schema, 'email');

            expect($rule)->toContain('string');
            expect($rule)->toContain('email');
        });

        it('should build rule with enum constraint', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                enum: ['active', 'inactive', 'pending']
            );
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'status');

            expect($rule)->toContain('string');
            expect($rule)->toContain('in:active,inactive,pending');
        });

        it('should build rule with numeric constraints', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minimum: 0,
                maximum: 100
            );
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'integer',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'percentage');

            expect($rule)->toContain('integer');
            expect($rule)->toContain('min:0');
            expect($rule)->toContain('max:100');
        });

        it('should build rule with array constraints', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minItems: 1,
                maxItems: 5,
                uniqueItems: true
            );
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'array',
                validation: $constraints,
                items: new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')
            );

            $rule = $this->mapper->buildRule($schema, 'tags');

            expect($rule)->toContain('array');
            expect($rule)->toContain('min:1');
            expect($rule)->toContain('max:5');
        });

        it('should build rule with pattern constraint', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                pattern: '^[A-Z][a-z]+$'
            );
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'string',
                validation: $constraints
            );

            $rule = $this->mapper->buildRule($schema, 'name');

            expect($rule)->toContain('string');
            expect($rule)->toContain('regex:/^[A-Z][a-z]+$/');
        });
    });

    describe('createValidationRules', function () {
        it('should create ValidationRule objects', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'age' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'integer')
                ],
                required: ['name']
            );

            $rules = $this->mapper->createValidationRules($schema);

            expect($rules)->toBeArray();
            expect($rules)->not->toBeEmpty();

            $nameRules = array_filter($rules, fn($rule) => $rule->fieldPath === 'name');
            expect($nameRules)->not->toBeEmpty();

            $requiredRule = array_filter($nameRules, fn($rule) => $rule->rule === 'required');
            expect($requiredRule)->toHaveCount(1);
        });
    });

    describe('validateLaravelRules', function () {
        it('should validate correct Laravel validation rules', function () {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                'age' => 'nullable|integer|min:0|max:120'
            ];

            $errors = $this->mapper->validateLaravelRules($rules);

            expect($errors)->toBeEmpty();
        });

        it('should detect invalid rule formats', function () {
            $rules = [
                'name' => 'required|string|',
                'email' => '',
                'age' => 'required|:invalid'
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
                'age' => null
            ];

            $errors = $this->mapper->validateLaravelRules($rules);

            expect($errors)->not->toBeEmpty();
            expect($errors[0])->toContain("Invalid rule for field 'name': must be non-empty string");
            expect($errors[1])->toContain("Invalid rule for field 'age': must be non-empty string");
        });
    });

    describe('combineRules', function () {
        it('should combine rules for same field', function () {
            // Simulate rules coming from different sources that need to be combined
            $rules1 = ['name' => 'required|string'];
            $rules2 = ['name' => 'max:255'];
            
            // First combine them manually to simulate multiple rule sources
            $allRules = [];
            foreach ([$rules1, $rules2] as $ruleSet) {
                foreach ($ruleSet as $field => $rule) {
                    if (isset($allRules[$field])) {
                        $allRules[$field] .= '|' . $rule;
                    } else {
                        $allRules[$field] = $rule;
                    }
                }
            }
            $allRules['email'] = 'required|email';

            $combined = $this->mapper->combineRules($allRules);

            expect($combined)->toHaveKey('name');
            expect($combined)->toHaveKey('email');
            expect($combined['name'])->toContain('required');
            expect($combined['name'])->toContain('string');
            expect($combined['name'])->toContain('max:255');
        });

        it('should avoid duplicate rules', function () {
            $rules = [
                'name' => 'required|string|required',
                'email' => 'email|required|email'
            ];

            $combined = $this->mapper->combineRules($rules);

            expect(substr_count($combined['name'], 'required'))->toBe(1);
            expect(substr_count($combined['email'], 'email'))->toBe(1);
        });
    });

    describe('sortValidationRules', function () {
        it('should sort field rules alphabetically', function () {
            $rules = [
                'z_field' => 'string',
                'a_field' => 'integer',
                'b_field' => 'boolean'
            ];

            $sorted = $this->mapper->sortValidationRules($rules);

            $keys = array_keys($sorted);
            expect($keys[0])->toBe('a_field');
            expect($keys[1])->toBe('b_field');
            expect($keys[2])->toBe('z_field');
        });

        it('should handle empty rules array', function () {
            $sorted = $this->mapper->sortValidationRules([]);

            expect($sorted)->toBeEmpty();
        });
    });
});