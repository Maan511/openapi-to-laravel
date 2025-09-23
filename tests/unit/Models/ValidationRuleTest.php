<?php

describe('ValidationRule', function () {
    describe('construction', function () {
        it('should create validation rule with basic properties', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            expect($rule->property)->toBe('email');
            expect($rule->type)->toBe('string');
            expect($rule->rules)->toBe(['required', 'email']);
            expect($rule->isRequired)->toBeFalse();
        });

        it('should detect required status from rules array', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'name',
                type: 'string',
                rules: ['required', 'min:3'],
                isRequired: true
            );

            expect($rule->isRequired)->toBeTrue();
        });

        it('should create rule with constraints', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 3,
                maxLength: 50
            );

            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'username',
                type: 'string',
                rules: ['required'],
                constraints: $constraints
            );

            expect($rule->constraints)->toBe($constraints);
            expect($rule->constraints->minLength)->toBe(3);
            expect($rule->constraints->maxLength)->toBe(50);
        });

        it('should create rule with nested property path', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'user.profile.age',
                type: 'integer',
                rules: ['integer', 'min:0']
            );

            expect($rule->property)->toBe('user.profile.age');
            expect($rule->isNested())->toBeTrue();
        });
    });

    describe('isNested', function () {
        it('should return true for nested property paths', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'user.profile.name',
                type: 'string',
                rules: []
            );

            expect($rule->isNested())->toBeTrue();
        });

        it('should return false for simple property names', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: []
            );

            expect($rule->isNested())->toBeFalse();
        });

        it('should return false for array notation', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'tags.*',
                type: 'string',
                rules: []
            );

            expect($rule->isNested())->toBeFalse();
        });
    });

    describe('hasConstraints', function () {
        it('should return true when constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5
            );

            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'name',
                type: 'string',
                rules: [],
                constraints: $constraints
            );

            expect($rule->hasConstraints())->toBeTrue();
        });

        it('should return false when constraints are null', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'name',
                type: 'string',
                rules: []
            );

            expect($rule->hasConstraints())->toBeFalse();
        });

        it('should return false when constraints are empty', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'name',
                type: 'string',
                rules: [],
                constraints: $constraints
            );

            expect($rule->hasConstraints())->toBeFalse();
        });
    });

    describe('addRule', function () {
        it('should add new rule to existing rules array', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'age',
                type: 'integer',
                rules: ['integer']
            );

            $rule->addRule('min:18');

            expect($rule->rules)->toContain('min:18');
            expect($rule->rules)->toHaveCount(2);
        });

        it('should not add duplicate rules', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            $rule->addRule('required');

            expect($rule->rules)->toHaveCount(2);
            expect(array_count_values($rule->rules)['required'])->toBe(1);
        });

        it('should handle rule with parameters', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'password',
                type: 'string',
                rules: []
            );

            $rule->addRule('min:8');
            $rule->addRule('max:255');

            expect($rule->rules)->toContain('min:8');
            expect($rule->rules)->toContain('max:255');
        });
    });

    describe('removeRule', function () {
        it('should remove existing rule from rules array', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email', 'max:255']
            );

            $rule->removeRule('max:255');

            expect($rule->rules)->not->toContain('max:255');
            expect($rule->rules)->toHaveCount(2);
        });

        it('should handle removing non-existent rule gracefully', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            $rule->removeRule('min:5');

            expect($rule->rules)->toHaveCount(2);
        });
    });

    describe('hasRule', function () {
        it('should return true for existing rule', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            expect($rule->hasRule('required'))->toBeTrue();
            expect($rule->hasRule('email'))->toBeTrue();
        });

        it('should return false for non-existing rule', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            expect($rule->hasRule('min:5'))->toBeFalse();
        });

        it('should handle rule with parameters correctly', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'age',
                type: 'integer',
                rules: ['integer', 'min:18', 'max:100']
            );

            expect($rule->hasRule('min:18'))->toBeTrue();
            expect($rule->hasRule('min:21'))->toBeFalse();
        });
    });

    describe('toValidationArray', function () {
        it('should convert to Laravel validation array format', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email', 'max:255']
            );

            $validationArray = $rule->toValidationArray();

            expect($validationArray)->toHaveKey('email');
            expect($validationArray['email'])->toBe(['required', 'email', 'max:255']);
        });

        it('should handle nested properties correctly', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'user.profile.name',
                type: 'string',
                rules: ['required', 'string']
            );

            $validationArray = $rule->toValidationArray();

            expect($validationArray)->toHaveKey('user.profile.name');
        });

        it('should handle array properties correctly', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'tags.*',
                type: 'string',
                rules: ['string', 'max:50']
            );

            $validationArray = $rule->toValidationArray();

            expect($validationArray)->toHaveKey('tags.*');
        });
    });

    describe('getPropertyPath', function () {
        it('should return property path for simple properties', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: []
            );

            expect($rule->getPropertyPath())->toBe('email');
        });

        it('should return full path for nested properties', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'user.profile.name',
                type: 'string',
                rules: []
            );

            expect($rule->getPropertyPath())->toBe('user.profile.name');
        });

        it('should handle array notation', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'items.*.name',
                type: 'string',
                rules: []
            );

            expect($rule->getPropertyPath())->toBe('items.*.name');
        });
    });

    describe('getBaseProperty', function () {
        it('should return base property for simple properties', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: []
            );

            expect($rule->getBaseProperty())->toBe('email');
        });

        it('should return first part for nested properties', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'user.profile.name',
                type: 'string',
                rules: []
            );

            expect($rule->getBaseProperty())->toBe('user');
        });

        it('should handle array notation', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'items.*.name',
                type: 'string',
                rules: []
            );

            expect($rule->getBaseProperty())->toBe('items');
        });
    });

    describe('isArray', function () {
        it('should return true for array type', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'tags',
                type: 'array',
                rules: ['array']
            );

            expect($rule->isArray())->toBeTrue();
        });

        it('should return false for non-array types', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'name',
                type: 'string',
                rules: ['string']
            );

            expect($rule->isArray())->toBeFalse();
        });

        it('should return true for array notation in property', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'tags.*',
                type: 'string',
                rules: ['string']
            );

            expect($rule->isArrayElement())->toBeTrue();
        });
    });

    describe('isArrayElement', function () {
        it('should return true for array element notation', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'items.*',
                type: 'string',
                rules: []
            );

            expect($rule->isArrayElement())->toBeTrue();
        });

        it('should return true for nested array element notation', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'users.*.profile.name',
                type: 'string',
                rules: []
            );

            expect($rule->isArrayElement())->toBeTrue();
        });

        it('should return false for regular properties', function () {
            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: []
            );

            expect($rule->isArrayElement())->toBeFalse();
        });
    });

    describe('clone', function () {
        it('should create deep copy of validation rule', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5
            );

            $original = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email'],
                constraints: $constraints
            );

            $clone = $original->clone();

            expect($clone->property)->toBe($original->property);
            expect($clone->type)->toBe($original->type);
            expect($clone->rules)->toBe($original->rules);
            expect($clone->constraints)->not->toBe($original->constraints); // Different instances
            expect($clone->constraints->minLength)->toBe($original->constraints->minLength);
        });

        it('should modify clone without affecting original', function () {
            $original = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'name',
                type: 'string',
                rules: ['required']
            );

            $clone = $original->clone();
            $clone->addRule('min:3');

            expect($original->rules)->toHaveCount(1);
            expect($clone->rules)->toHaveCount(2);
        });
    });

    describe('toArray', function () {
        it('should convert rule to array format', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 100
            );

            $rule = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: 'username',
                type: 'string',
                rules: ['required', 'string'],
                isRequired: true,
                constraints: $constraints
            );

            $array = $rule->toArray();

            expect($array)->toHaveKey('property');
            expect($array)->toHaveKey('type');
            expect($array)->toHaveKey('rules');
            expect($array)->toHaveKey('isRequired');
            expect($array)->toHaveKey('constraints');

            expect($array['property'])->toBe('username');
            expect($array['type'])->toBe('string');
            expect($array['rules'])->toBe(['required', 'string']);
            expect($array['isRequired'])->toBeTrue();
            expect($array['constraints'])->toBeArray();
        });
    });

    describe('static factory methods', function () {
        describe('required', function () {
            it('should create required validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::required('email', 'string');

                expect($rule->property)->toBe('email');
                expect($rule->type)->toBe('string');
                expect($rule->isRequired)->toBeTrue();
                expect($rule->rules)->toContain('required');
            });
        });

        describe('optional', function () {
            it('should create optional validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::optional('bio', 'string');

                expect($rule->property)->toBe('bio');
                expect($rule->type)->toBe('string');
                expect($rule->isRequired)->toBeFalse();
                expect($rule->rules)->not->toContain('required');
            });
        });

        describe('array', function () {
            it('should create array validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::array('tags');

                expect($rule->property)->toBe('tags');
                expect($rule->type)->toBe('array');
                expect($rule->rules)->toContain('array');
            });
        });

        describe('string', function () {
            it('should create string validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::string('name');

                expect($rule->property)->toBe('name');
                expect($rule->type)->toBe('string');
                expect($rule->rules)->toContain('string');
            });
        });

        describe('integer', function () {
            it('should create integer validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::integer('age');

                expect($rule->property)->toBe('age');
                expect($rule->type)->toBe('integer');
                expect($rule->rules)->toContain('integer');
            });
        });

        describe('number', function () {
            it('should create number validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::number('price');

                expect($rule->property)->toBe('price');
                expect($rule->type)->toBe('number');
                expect($rule->rules)->toContain('numeric');
            });
        });

        describe('boolean', function () {
            it('should create boolean validation rule', function () {
                $rule = \Maan511\OpenapiToLaravel\Models\ValidationRule::boolean('active');

                expect($rule->property)->toBe('active');
                expect($rule->type)->toBe('boolean');
                expect($rule->rules)->toContain('boolean');
            });
        });
    });
});