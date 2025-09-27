<?php

use Maan511\OpenapiToLaravel\Models\ValidationConstraints;
use Maan511\OpenapiToLaravel\Models\ValidationRule;

describe('ValidationRule', function (): void {
    describe('construction', function (): void {
        it('should create validation rule with all features', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 3,
                maxLength: 50
            );

            $rule = new ValidationRule(
                property: 'user.profile.email',
                type: 'string',
                rules: ['required', 'email'],
                isRequired: true,
                constraints: $constraints
            );

            expect($rule->property)->toBe('user.profile.email');
            expect($rule->type)->toBe('string');
            expect($rule->rules)->toBe(['required', 'email']);
            expect($rule->isRequired)->toBeTrue();
            expect($rule->constraints)->toBe($constraints);
            expect($rule->constraints->minLength)->toBe(3);
            expect($rule->constraints->maxLength)->toBe(50);
            expect($rule->isNested())->toBeTrue();
        });
    });

    describe('isNested', function (): void {
        it('should return true for nested property paths', function (): void {
            $rule = new ValidationRule(
                property: 'user.profile.name',
                type: 'string',
                rules: []
            );

            expect($rule->isNested())->toBeTrue();
        });

        it('should return false for simple property names', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: []
            );

            expect($rule->isNested())->toBeFalse();
        });

        it('should return false for array notation', function (): void {
            $rule = new ValidationRule(
                property: 'tags.*',
                type: 'string',
                rules: []
            );

            expect($rule->isNested())->toBeFalse();
        });
    });

    describe('hasConstraints', function (): void {
        it('should return true when constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5
            );

            $rule = new ValidationRule(
                property: 'name',
                type: 'string',
                rules: [],
                constraints: $constraints
            );

            expect($rule->hasConstraints())->toBeTrue();
        });

        it('should return false when constraints are null', function (): void {
            $rule = new ValidationRule(
                property: 'name',
                type: 'string',
                rules: []
            );

            expect($rule->hasConstraints())->toBeFalse();
        });

        it('should return false when constraints are empty', function (): void {
            $constraints = new ValidationConstraints;

            $rule = new ValidationRule(
                property: 'name',
                type: 'string',
                rules: [],
                constraints: $constraints
            );

            expect($rule->hasConstraints())->toBeFalse();
        });
    });

    describe('addRule', function (): void {
        it('should add new rule to existing rules array', function (): void {
            $rule = new ValidationRule(
                property: 'age',
                type: 'integer',
                rules: ['integer']
            );

            $rule->addRule('min:18');

            expect($rule->rules)->toContain('min:18');
            expect($rule->rules)->toHaveCount(2);
        });

        it('should not add duplicate rules', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            $rule->addRule('required');

            expect($rule->rules)->toHaveCount(2);
            expect(array_count_values($rule->rules)['required'])->toBe(1);
        });

        it('should handle rule with parameters', function (): void {
            $rule = new ValidationRule(
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

    describe('removeRule', function (): void {
        it('should remove existing rule from rules array', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email', 'max:255']
            );

            $rule->removeRule('max:255');

            expect($rule->rules)->not->toContain('max:255');
            expect($rule->rules)->toHaveCount(2);
        });

        it('should handle removing non-existent rule gracefully', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            $rule->removeRule('min:5');

            expect($rule->rules)->toHaveCount(2);
        });
    });

    describe('hasRule', function (): void {
        it('should return true for existing rule', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            expect($rule->hasRule('required'))->toBeTrue();
            expect($rule->hasRule('email'))->toBeTrue();
        });

        it('should return false for non-existing rule', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email']
            );

            expect($rule->hasRule('min:5'))->toBeFalse();
        });

        it('should handle rule with parameters correctly', function (): void {
            $rule = new ValidationRule(
                property: 'age',
                type: 'integer',
                rules: ['integer', 'min:18', 'max:100']
            );

            expect($rule->hasRule('min:18'))->toBeTrue();
            expect($rule->hasRule('min:21'))->toBeFalse();
        });
    });

    describe('toValidationArray', function (): void {
        it('should convert to Laravel validation array format', function (): void {
            $rule = new ValidationRule(
                property: 'email',
                type: 'string',
                rules: ['required', 'email', 'max:255']
            );

            $validationArray = $rule->toValidationArray();

            expect($validationArray)->toHaveKey('email');
            expect($validationArray['email'])->toBe(['required', 'email', 'max:255']);
        });

        it('should handle nested properties correctly', function (): void {
            $rule = new ValidationRule(
                property: 'user.profile.name',
                type: 'string',
                rules: ['required', 'string']
            );

            $validationArray = $rule->toValidationArray();

            expect($validationArray)->toHaveKey('user.profile.name');
        });

        it('should handle array properties correctly', function (): void {
            $rule = new ValidationRule(
                property: 'tags.*',
                type: 'string',
                rules: ['string', 'max:50']
            );

            $validationArray = $rule->toValidationArray();

            expect($validationArray)->toHaveKey('tags.*');
        });
    });

    describe('property path methods', function (): void {
        it('should handle property path operations correctly', function (): void {
            $testCases = [
                ['property' => 'email', 'expectedPath' => 'email', 'expectedBase' => 'email'],
                ['property' => 'user.profile.name', 'expectedPath' => 'user.profile.name', 'expectedBase' => 'user'],
                ['property' => 'items.*.name', 'expectedPath' => 'items.*.name', 'expectedBase' => 'items'],
            ];

            foreach ($testCases as $case) {
                $rule = new ValidationRule(
                    property: $case['property'],
                    type: 'string',
                    rules: []
                );

                expect($rule->getPropertyPath())->toBe($case['expectedPath']);
                expect($rule->getBaseProperty())->toBe($case['expectedBase']);
            }
        });
    });

    describe('array detection methods', function (): void {
        it('should correctly identify array types and array elements', function (): void {
            $testCases = [
                ['property' => 'tags', 'type' => 'array', 'expectedIsArray' => true, 'expectedIsArrayElement' => false],
                ['property' => 'name', 'type' => 'string', 'expectedIsArray' => false, 'expectedIsArrayElement' => false],
                ['property' => 'tags.*', 'type' => 'string', 'expectedIsArray' => true, 'expectedIsArrayElement' => true],
                ['property' => 'items.*', 'type' => 'string', 'expectedIsArray' => true, 'expectedIsArrayElement' => true],
                ['property' => 'users.*.profile.name', 'type' => 'string', 'expectedIsArray' => true, 'expectedIsArrayElement' => true],
                ['property' => 'email', 'type' => 'string', 'expectedIsArray' => false, 'expectedIsArrayElement' => false],
            ];

            foreach ($testCases as $case) {
                $rule = new ValidationRule(
                    property: $case['property'],
                    type: $case['type'],
                    rules: []
                );

                expect($rule->isArray())->toBe($case['expectedIsArray']);
                expect($rule->isArrayElement())->toBe($case['expectedIsArrayElement']);
            }
        });
    });

    describe('clone', function (): void {
        it('should create deep copy of validation rule', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5
            );

            $original = new ValidationRule(
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

        it('should modify clone without affecting original', function (): void {
            $original = new ValidationRule(
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

    describe('toArray', function (): void {
        it('should convert rule to array format', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5,
                maxLength: 100
            );

            $rule = new ValidationRule(
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

    describe('static factory methods', function (): void {
        it('should create validation rules with correct types and rules', function (): void {
            $testCases = [
                ['method' => 'required', 'property' => 'email', 'type' => 'string', 'expectedType' => 'string', 'expectedRule' => 'required', 'isRequired' => true],
                ['method' => 'optional', 'property' => 'bio', 'type' => 'string', 'expectedType' => 'string', 'expectedRule' => null, 'isRequired' => false],
                ['method' => 'array', 'property' => 'tags', 'type' => null, 'expectedType' => 'array', 'expectedRule' => 'array', 'isRequired' => null],
                ['method' => 'string', 'property' => 'name', 'type' => null, 'expectedType' => 'string', 'expectedRule' => 'string', 'isRequired' => null],
                ['method' => 'integer', 'property' => 'age', 'type' => null, 'expectedType' => 'integer', 'expectedRule' => 'integer', 'isRequired' => null],
                ['method' => 'number', 'property' => 'price', 'type' => null, 'expectedType' => 'number', 'expectedRule' => 'numeric', 'isRequired' => null],
                ['method' => 'boolean', 'property' => 'active', 'type' => null, 'expectedType' => 'boolean', 'expectedRule' => 'boolean', 'isRequired' => null],
            ];

            foreach ($testCases as $case) {
                $rule = $case['type']
                    ? ValidationRule::{$case['method']}($case['property'], $case['type'])
                    : ValidationRule::{$case['method']}($case['property']);

                expect($rule->property)->toBe($case['property']);
                expect($rule->type)->toBe($case['expectedType']);

                if ($case['expectedRule']) {
                    expect($rule->rules)->toContain($case['expectedRule']);
                }

                if ($case['isRequired'] !== null) {
                    expect($rule->isRequired)->toBe($case['isRequired']);
                }
            }
        });
    });
});
