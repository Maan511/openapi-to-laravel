<?php

use Maan511\OpenapiToLaravel\Models\ValidationConstraints;

describe('ValidationConstraints', function (): void {
    describe('construction', function (): void {
        it('should create constraints with string validation', function (): void {
            $constraints = new ValidationConstraints(
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

        it('should create constraints with numeric validation', function (): void {
            $constraints = new ValidationConstraints(
                minimum: 0,
                maximum: 1000,
                multipleOf: 5
            );

            expect($constraints->minimum)->toBe(0);
            expect($constraints->maximum)->toBe(1000);
            expect($constraints->multipleOf)->toBe(5);
        });

        it('should create constraints with array validation', function (): void {
            $constraints = new ValidationConstraints(
                minItems: 1,
                maxItems: 10,
                uniqueItems: true
            );

            expect($constraints->minItems)->toBe(1);
            expect($constraints->maxItems)->toBe(10);
            expect($constraints->uniqueItems)->toBeTrue();
        });

        it('should create empty constraints by default', function (): void {
            $constraints = new ValidationConstraints;

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

    describe('hasStringConstraints', function (): void {
        it('should return true when string constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5
            );

            expect($constraints->hasStringConstraints())->toBeTrue();
        });

        it('should return true when pattern constraint exists', function (): void {
            $constraints = new ValidationConstraints(
                pattern: '^[a-z]+$'
            );

            expect($constraints->hasStringConstraints())->toBeTrue();
        });

        it('should return false when no string constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minimum: 0,
                maximum: 100
            );

            expect($constraints->hasStringConstraints())->toBeFalse();
        });
    });

    describe('hasNumericConstraints', function (): void {
        it('should return true when numeric constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minimum: 0
            );

            expect($constraints->hasNumericConstraints())->toBeTrue();
        });

        it('should return true when multipleOf constraint exists', function (): void {
            $constraints = new ValidationConstraints(
                multipleOf: 5
            );

            expect($constraints->hasNumericConstraints())->toBeTrue();
        });

        it('should return false when no numeric constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5,
                maxLength: 100
            );

            expect($constraints->hasNumericConstraints())->toBeFalse();
        });
    });

    describe('hasArrayConstraints', function (): void {
        it('should return true when array constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minItems: 1
            );

            expect($constraints->hasArrayConstraints())->toBeTrue();
        });

        it('should return true when uniqueItems constraint exists', function (): void {
            $constraints = new ValidationConstraints(
                uniqueItems: true
            );

            expect($constraints->hasArrayConstraints())->toBeTrue();
        });

        it('should return false when no array constraints exist', function (): void {
            $constraints = new ValidationConstraints(
                minimum: 0,
                pattern: '^[a-z]+$'
            );

            expect($constraints->hasArrayConstraints())->toBeFalse();
        });
    });

    describe('hasEnumConstraint', function (): void {
        it('should return true when enum constraint exists', function (): void {
            $constraints = new ValidationConstraints(
                enum: ['option1', 'option2']
            );

            expect($constraints->hasEnumConstraint())->toBeTrue();
        });

        it('should return false when enum constraint is null', function (): void {
            $constraints = new ValidationConstraints;

            expect($constraints->hasEnumConstraint())->toBeFalse();
        });

        it('should return false when enum constraint is empty array', function (): void {
            $constraints = new ValidationConstraints(
                enum: []
            );

            expect($constraints->hasEnumConstraint())->toBeFalse();
        });
    });

    describe('hasPatternConstraint', function (): void {
        it('should return true when pattern constraint exists', function (): void {
            $constraints = new ValidationConstraints(
                pattern: '^[a-zA-Z]+$'
            );

            expect($constraints->hasPatternConstraint())->toBeTrue();
        });

        it('should return false when pattern constraint is null', function (): void {
            $constraints = new ValidationConstraints;

            expect($constraints->hasPatternConstraint())->toBeFalse();
        });

        it('should return false when pattern constraint is empty string', function (): void {
            $constraints = new ValidationConstraints(
                pattern: ''
            );

            expect($constraints->hasPatternConstraint())->toBeFalse();
        });
    });

    describe('isEmpty', function (): void {
        it('should return true for empty constraints', function (): void {
            $constraints = new ValidationConstraints;

            expect($constraints->isEmpty())->toBeTrue();
        });

        it('should return false when any constraint exists', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5
            );

            expect($constraints->isEmpty())->toBeFalse();
        });
    });

    describe('merge', function (): void {
        it('should merge two constraint objects', function (): void {
            $constraints1 = new ValidationConstraints(
                minLength: 5,
                minimum: 0
            );

            $constraints2 = new ValidationConstraints(
                maxLength: 100,
                maximum: 1000
            );

            $merged = $constraints1->merge($constraints2);

            expect($merged->minLength)->toBe(5);
            expect($merged->maxLength)->toBe(100);
            expect($merged->minimum)->toBe(0);
            expect($merged->maximum)->toBe(1000);
        });

        it('should prioritize second constraint when conflicts exist', function (): void {
            $constraints1 = new ValidationConstraints(
                minLength: 5,
                maxLength: 50
            );

            $constraints2 = new ValidationConstraints(
                maxLength: 100
            );

            $merged = $constraints1->merge($constraints2);

            expect($merged->minLength)->toBe(5);
            expect($merged->maxLength)->toBe(100); // Second value takes precedence
        });
    });

    describe('toArray', function (): void {
        it('should convert constraints to array format', function (): void {
            $constraints = new ValidationConstraints(
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

        it('should exclude null values from array', function (): void {
            $constraints = new ValidationConstraints(
                minLength: 5
            );

            $array = $constraints->toArray();

            expect($array)->toHaveKey('minLength');
            expect($array)->not->toHaveKey('maxLength');
            expect($array)->not->toHaveKey('pattern');
        });
    });

    describe('fromArray', function (): void {
        it('should create constraints from array data', function (): void {
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
                'uniqueItems' => true,
            ];

            $constraints = ValidationConstraints::fromArray($data);

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

        it('should handle partial array data', function (): void {
            $data = [
                'minLength' => 5,
                'maximum' => 100,
            ];

            $constraints = ValidationConstraints::fromArray($data);

            expect($constraints->minLength)->toBe(5);
            expect($constraints->maximum)->toBe(100);
            expect($constraints->maxLength)->toBeNull();
            expect($constraints->minimum)->toBeNull();
        });

        it('should handle empty array data', function (): void {
            $constraints = ValidationConstraints::fromArray([]);

            expect($constraints->isEmpty())->toBeTrue();
        });
    });

    describe('validatePattern', function (): void {
        it('should validate correct regex patterns', function (): void {
            $constraints = new ValidationConstraints(
                pattern: '^[a-zA-Z]+$'
            );

            $result = $constraints->validatePattern();

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect invalid regex patterns', function (): void {
            $constraints = new ValidationConstraints(
                pattern: '[invalid pattern'
            );

            $result = $constraints->validatePattern();

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });

        it('should return valid for empty pattern', function (): void {
            $constraints = new ValidationConstraints;

            $result = $constraints->validatePattern();

            expect($result['valid'])->toBeTrue();
        });
    });

    describe('getComplexityScore', function (): void {
        it('should return higher score for more complex constraints', function (): void {
            $simpleConstraints = new ValidationConstraints(
                minLength: 5
            );

            $complexConstraints = new ValidationConstraints(
                minLength: 5,
                maxLength: 100,
                pattern: '^[a-zA-Z]+$',
                enum: ['option1', 'option2', 'option3']
            );

            expect($complexConstraints->getComplexityScore())->toBeGreaterThan($simpleConstraints->getComplexityScore());
        });

        it('should return 0 for empty constraints', function (): void {
            $constraints = new ValidationConstraints;

            expect($constraints->getComplexityScore())->toBe(0);
        });
    });
});
