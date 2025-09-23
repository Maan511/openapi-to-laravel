<?php

describe('ValidationConstraints', function () {
    describe('construction', function () {
        it('should create constraints with string validation', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 100,
                pattern: '^[a-zA-Z]+$',
                enum: ['option1', 'option2']
            );

            expect($constraints->minLength)->toBe(5);
            expect($constraints->maxLength)->toBe(100);
            expect($constraints->pattern)->toBe('^[a-zA-Z]+$');
            expect($constraints->enum)->toBe(['option1', 'option2']);
        });

        it('should create constraints with numeric validation', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minimum: 0,
                maximum: 1000,
                multipleOf: 5
            );

            expect($constraints->minimum)->toBe(0);
            expect($constraints->maximum)->toBe(1000);
            expect($constraints->multipleOf)->toBe(5);
        });

        it('should create constraints with array validation', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minItems: 1,
                maxItems: 10,
                uniqueItems: true
            );

            expect($constraints->minItems)->toBe(1);
            expect($constraints->maxItems)->toBe(10);
            expect($constraints->uniqueItems)->toBeTrue();
        });

        it('should create empty constraints by default', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            expect($constraints->minLength)->toBeNull();
            expect($constraints->maxLength)->toBeNull();
            expect($constraints->pattern)->toBeNull();
            expect($constraints->enum)->toBeNull();
            expect($constraints->minimum)->toBeNull();
            expect($constraints->maximum)->toBeNull();
            expect($constraints->multipleOf)->toBeNull();
            expect($constraints->minItems)->toBeNull();
            expect($constraints->maxItems)->toBeNull();
            expect($constraints->uniqueItems)->toBeNull();
        });
    });

    describe('hasStringConstraints', function () {
        it('should return true when string constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5
            );

            expect($constraints->hasStringConstraints())->toBeTrue();
        });

        it('should return true when pattern constraint exists', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                pattern: '^[a-z]+$'
            );

            expect($constraints->hasStringConstraints())->toBeTrue();
        });

        it('should return false when no string constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minimum: 0,
                maximum: 100
            );

            expect($constraints->hasStringConstraints())->toBeFalse();
        });
    });

    describe('hasNumericConstraints', function () {
        it('should return true when numeric constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minimum: 0
            );

            expect($constraints->hasNumericConstraints())->toBeTrue();
        });

        it('should return true when multipleOf constraint exists', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                multipleOf: 5
            );

            expect($constraints->hasNumericConstraints())->toBeTrue();
        });

        it('should return false when no numeric constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 100
            );

            expect($constraints->hasNumericConstraints())->toBeFalse();
        });
    });

    describe('hasArrayConstraints', function () {
        it('should return true when array constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minItems: 1
            );

            expect($constraints->hasArrayConstraints())->toBeTrue();
        });

        it('should return true when uniqueItems constraint exists', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                uniqueItems: true
            );

            expect($constraints->hasArrayConstraints())->toBeTrue();
        });

        it('should return false when no array constraints exist', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minimum: 0,
                pattern: '^[a-z]+$'
            );

            expect($constraints->hasArrayConstraints())->toBeFalse();
        });
    });

    describe('hasEnumConstraint', function () {
        it('should return true when enum constraint exists', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                enum: ['option1', 'option2']
            );

            expect($constraints->hasEnumConstraint())->toBeTrue();
        });

        it('should return false when enum constraint is null', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            expect($constraints->hasEnumConstraint())->toBeFalse();
        });

        it('should return false when enum constraint is empty array', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                enum: []
            );

            expect($constraints->hasEnumConstraint())->toBeFalse();
        });
    });

    describe('hasPatternConstraint', function () {
        it('should return true when pattern constraint exists', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                pattern: '^[a-zA-Z]+$'
            );

            expect($constraints->hasPatternConstraint())->toBeTrue();
        });

        it('should return false when pattern constraint is null', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            expect($constraints->hasPatternConstraint())->toBeFalse();
        });

        it('should return false when pattern constraint is empty string', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                pattern: ''
            );

            expect($constraints->hasPatternConstraint())->toBeFalse();
        });
    });

    describe('isEmpty', function () {
        it('should return true for empty constraints', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            expect($constraints->isEmpty())->toBeTrue();
        });

        it('should return false when any constraint exists', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5
            );

            expect($constraints->isEmpty())->toBeFalse();
        });
    });

    describe('merge', function () {
        it('should merge two constraint objects', function () {
            $constraints1 = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                minimum: 0
            );

            $constraints2 = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                maxLength: 100,
                maximum: 1000
            );

            $merged = $constraints1->merge($constraints2);

            expect($merged->minLength)->toBe(5);
            expect($merged->maxLength)->toBe(100);
            expect($merged->minimum)->toBe(0);
            expect($merged->maximum)->toBe(1000);
        });

        it('should prioritize second constraint when conflicts exist', function () {
            $constraints1 = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 50
            );

            $constraints2 = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                maxLength: 100
            );

            $merged = $constraints1->merge($constraints2);

            expect($merged->minLength)->toBe(5);
            expect($merged->maxLength)->toBe(100); // Second value takes precedence
        });
    });

    describe('toArray', function () {
        it('should convert constraints to array format', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 100,
                pattern: '^[a-zA-Z]+$',
                enum: ['option1', 'option2']
            );

            $array = $constraints->toArray();

            expect($array)->toHaveKey('minLength');
            expect($array)->toHaveKey('maxLength');
            expect($array)->toHaveKey('pattern');
            expect($array)->toHaveKey('enum');
            expect($array['minLength'])->toBe(5);
            expect($array['maxLength'])->toBe(100);
            expect($array['pattern'])->toBe('^[a-zA-Z]+$');
            expect($array['enum'])->toBe(['option1', 'option2']);
        });

        it('should exclude null values from array', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5
            );

            $array = $constraints->toArray();

            expect($array)->toHaveKey('minLength');
            expect($array)->not->toHaveKey('maxLength');
            expect($array)->not->toHaveKey('pattern');
        });
    });

    describe('fromArray', function () {
        it('should create constraints from array data', function () {
            $data = [
                'minLength' => 5,
                'maxLength' => 100,
                'pattern' => '^[a-zA-Z]+$',
                'enum' => ['option1', 'option2'],
                'minimum' => 0,
                'maximum' => 1000,
                'multipleOf' => 5,
                'minItems' => 1,
                'maxItems' => 10,
                'uniqueItems' => true
            ];

            $constraints = \Maan511\OpenapiToLaravel\Models\ValidationConstraints::fromArray($data);

            expect($constraints->minLength)->toBe(5);
            expect($constraints->maxLength)->toBe(100);
            expect($constraints->pattern)->toBe('^[a-zA-Z]+$');
            expect($constraints->enum)->toBe(['option1', 'option2']);
            expect($constraints->minimum)->toBe(0);
            expect($constraints->maximum)->toBe(1000);
            expect($constraints->multipleOf)->toBe(5);
            expect($constraints->minItems)->toBe(1);
            expect($constraints->maxItems)->toBe(10);
            expect($constraints->uniqueItems)->toBeTrue();
        });

        it('should handle partial array data', function () {
            $data = [
                'minLength' => 5,
                'maximum' => 100
            ];

            $constraints = \Maan511\OpenapiToLaravel\Models\ValidationConstraints::fromArray($data);

            expect($constraints->minLength)->toBe(5);
            expect($constraints->maximum)->toBe(100);
            expect($constraints->maxLength)->toBeNull();
            expect($constraints->minimum)->toBeNull();
        });

        it('should handle empty array data', function () {
            $constraints = \Maan511\OpenapiToLaravel\Models\ValidationConstraints::fromArray([]);

            expect($constraints->isEmpty())->toBeTrue();
        });
    });

    describe('validatePattern', function () {
        it('should validate correct regex patterns', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                pattern: '^[a-zA-Z]+$'
            );

            $result = $constraints->validatePattern();

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect invalid regex patterns', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                pattern: '[invalid pattern'
            );

            $result = $constraints->validatePattern();

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });

        it('should return valid for empty pattern', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            $result = $constraints->validatePattern();

            expect($result['valid'])->toBeTrue();
        });
    });

    describe('getComplexityScore', function () {
        it('should return higher score for more complex constraints', function () {
            $simpleConstraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5
            );

            $complexConstraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints(
                minLength: 5,
                maxLength: 100,
                pattern: '^[a-zA-Z]+$',
                enum: ['option1', 'option2', 'option3']
            );

            expect($complexConstraints->getComplexityScore())->toBeGreaterThan($simpleConstraints->getComplexityScore());
        });

        it('should return 0 for empty constraints', function () {
            $constraints = new \Maan511\OpenapiToLaravel\Models\ValidationConstraints();

            expect($constraints->getComplexityScore())->toBe(0);
        });
    });
});