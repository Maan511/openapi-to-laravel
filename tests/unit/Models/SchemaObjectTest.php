<?php

use Maan511\OpenapiToLaravel\Models\SchemaObject;
use Maan511\OpenapiToLaravel\Models\ValidationConstraints;

describe('SchemaObject', function (): void {
    describe('construction', function (): void {
        it('should create simple schema object', function (): void {
            $schema = new SchemaObject(
                type: 'string'
            );

            expect($schema->type)->toBe('string');
            expect($schema->properties)->toBeEmpty();
            expect($schema->required)->toBeEmpty();
            expect($schema->items)->toBeNull();
            expect($schema->format)->toBeNull();
            expect($schema->validation)->toBeNull();
        });

        it('should create object schema with properties', function (): void {
            $nameProperty = new SchemaObject(type: 'string');
            $ageProperty = new SchemaObject(type: 'integer');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty, 'age' => $ageProperty],
                required: ['name']
            );

            expect($schema->type)->toBe('object');
            expect($schema->properties)->toHaveKey('name');
            expect($schema->properties)->toHaveKey('age');
            expect($schema->required)->toBe(['name']);
            expect($schema->properties['name']->type)->toBe('string');
            expect($schema->properties['age']->type)->toBe('integer');
        });

        it('should create array schema with items', function (): void {
            $itemSchema = new SchemaObject(type: 'string');

            $schema = new SchemaObject(
                type: 'array',
                items: $itemSchema
            );

            expect($schema->type)->toBe('array');
            expect($schema->items)->toBe($itemSchema);
            expect($schema->items->type)->toBe('string');
        });

        it('should create schema with validation constraints', function (): void {
            $validation = new ValidationConstraints(
                minLength: 5,
                maxLength: 100,
                pattern: '^[a-zA-Z]+$'
            );

            $schema = new SchemaObject(
                type: 'string',
                validation: $validation
            );

            expect($schema->validation)->toBe($validation);
            expect($schema->validation->minLength)->toBe(5);
            expect($schema->validation->maxLength)->toBe(100);
            expect($schema->validation->pattern)->toBe('^[a-zA-Z]+$');
        });

        it('should create schema with format', function (): void {
            $schema = new SchemaObject(
                type: 'string',
                format: 'email'
            );

            expect($schema->type)->toBe('string');
            expect($schema->format)->toBe('email');
        });
    });

    describe('isObject', function (): void {
        it('should return true for object type', function (): void {
            $schema = new SchemaObject(type: 'object');

            expect($schema->isObject())->toBeTrue();
        });

        it('should return false for non-object types', function (): void {
            $stringSchema = new SchemaObject(type: 'string');
            $arraySchema = new SchemaObject(type: 'array');

            expect($stringSchema->isObject())->toBeFalse();
            expect($arraySchema->isObject())->toBeFalse();
        });
    });

    describe('isArray', function (): void {
        it('should return true for array type', function (): void {
            $schema = new SchemaObject(type: 'array');

            expect($schema->isArray())->toBeTrue();
        });

        it('should return false for non-array types', function (): void {
            $stringSchema = new SchemaObject(type: 'string');
            $objectSchema = new SchemaObject(type: 'object');

            expect($stringSchema->isArray())->toBeFalse();
            expect($objectSchema->isArray())->toBeFalse();
        });
    });

    describe('isPrimitive', function (): void {
        it('should return true for primitive types', function (): void {
            $stringSchema = new SchemaObject(type: 'string');
            $integerSchema = new SchemaObject(type: 'integer');
            $numberSchema = new SchemaObject(type: 'number');
            $booleanSchema = new SchemaObject(type: 'boolean');

            expect($stringSchema->isPrimitive())->toBeTrue();
            expect($integerSchema->isPrimitive())->toBeTrue();
            expect($numberSchema->isPrimitive())->toBeTrue();
            expect($booleanSchema->isPrimitive())->toBeTrue();
        });

        it('should return false for complex types', function (): void {
            $objectSchema = new SchemaObject(type: 'object');
            $arraySchema = new SchemaObject(type: 'array');

            expect($objectSchema->isPrimitive())->toBeFalse();
            expect($arraySchema->isPrimitive())->toBeFalse();
        });
    });

    describe('hasValidation', function (): void {
        it('should return true when validation constraints exist', function (): void {
            $validation = new ValidationConstraints(minLength: 5);
            $schema = new SchemaObject(
                type: 'string',
                validation: $validation
            );

            expect($schema->hasValidation())->toBeTrue();
        });

        it('should return false when no validation constraints exist', function (): void {
            $schema = new SchemaObject(type: 'string');

            expect($schema->hasValidation())->toBeFalse();
        });
    });

    describe('getProperty', function (): void {
        it('should return property schema by name', function (): void {
            $nameProperty = new SchemaObject(type: 'string');
            $ageProperty = new SchemaObject(type: 'integer');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty, 'age' => $ageProperty]
            );

            expect($schema->getProperty('name'))->toBe($nameProperty);
            expect($schema->getProperty('age'))->toBe($ageProperty);
        });

        it('should return null for non-existent property', function (): void {
            $schema = new SchemaObject(type: 'object');

            expect($schema->getProperty('nonexistent'))->toBeNull();
        });
    });

    describe('hasProperty', function (): void {
        it('should return true for existing properties', function (): void {
            $nameProperty = new SchemaObject(type: 'string');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty]
            );

            expect($schema->hasProperty('name'))->toBeTrue();
        });

        it('should return false for non-existing properties', function (): void {
            $schema = new SchemaObject(type: 'object');

            expect($schema->hasProperty('nonexistent'))->toBeFalse();
        });
    });

    describe('isRequired', function (): void {
        it('should return true for required properties', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                required: ['name', 'email']
            );

            expect($schema->isRequired('name'))->toBeTrue();
            expect($schema->isRequired('email'))->toBeTrue();
        });

        it('should return false for optional properties', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                required: ['name']
            );

            expect($schema->isRequired('age'))->toBeFalse();
        });
    });

    describe('getNestingLevel', function (): void {
        it('should return 0 for primitive schemas', function (): void {
            $schema = new SchemaObject(type: 'string');

            expect($schema->getNestingLevel())->toBe(0);
        });

        it('should return 1 for simple object schemas', function (): void {
            $nameProperty = new SchemaObject(type: 'string');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty]
            );

            expect($schema->getNestingLevel())->toBe(1);
        });

        it('should return correct level for nested object schemas', function (): void {
            $bioProperty = new SchemaObject(type: 'string');
            $profileProperty = new SchemaObject(
                type: 'object',
                properties: ['bio' => $bioProperty]
            );
            $userProperty = new SchemaObject(
                type: 'object',
                properties: ['profile' => $profileProperty]
            );

            $schema = new SchemaObject(
                type: 'object',
                properties: ['user' => $userProperty]
            );

            expect($schema->getNestingLevel())->toBe(3);
        });

        it('should handle array nesting', function (): void {
            $itemProperty = new SchemaObject(type: 'string');
            $arrayProperty = new SchemaObject(
                type: 'array',
                items: $itemProperty
            );

            $schema = new SchemaObject(
                type: 'object',
                properties: ['tags' => $arrayProperty]
            );

            expect($schema->getNestingLevel())->toBe(2);
        });
    });

    describe('toArray', function (): void {
        it('should convert simple schema to array', function (): void {
            $schema = new SchemaObject(
                type: 'string',
                format: 'email'
            );

            $array = $schema->toArray();

            expect($array)->toHaveKey('type');
            expect($array)->toHaveKey('format');
            expect($array['type'])->toBe('string');
            expect($array['format'])->toBe('email');
        });

        it('should convert complex schema to array', function (): void {
            $nameProperty = new SchemaObject(type: 'string');
            $validation = new ValidationConstraints(minLength: 2);

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty],
                required: ['name'],
                validation: $validation
            );

            $array = $schema->toArray();

            expect($array)->toHaveKey('type');
            expect($array)->toHaveKey('properties');
            expect($array)->toHaveKey('required');
            expect($array)->toHaveKey('validation');
            expect($array['properties'])->toHaveKey('name');
            expect($array['required'])->toBe(['name']);
        });
    });

    describe('fromArray', function (): void {
        it('should create schema from array data', function (): void {
            $data = [
                'type' => 'string',
                'format' => 'email',
            ];

            $schema = SchemaObject::fromArray($data);

            expect($schema->type)->toBe('string');
            expect($schema->format)->toBe('email');
        });

        it('should create complex schema from array data', function (): void {
            $data = [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
                'required' => ['name'],
            ];

            $schema = SchemaObject::fromArray($data);

            expect($schema->type)->toBe('object');
            expect($schema->properties)->toHaveKey('name');
            expect($schema->properties)->toHaveKey('age');
            expect($schema->required)->toBe(['name']);
            expect($schema->properties['name']->type)->toBe('string');
            expect($schema->properties['age']->type)->toBe('integer');
        });

        it('should handle array schema from array data', function (): void {
            $data = [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ];

            $schema = SchemaObject::fromArray($data);

            expect($schema->type)->toBe('array');
            expect($schema->items)->not->toBeNull();
            expect($schema->items->type)->toBe('string');
        });
    });

    describe('clone', function (): void {
        it('should create deep copy of schema', function (): void {
            $nameProperty = new SchemaObject(type: 'string');
            $validation = new ValidationConstraints(minLength: 2);

            $original = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty],
                validation: $validation
            );

            $cloned = clone $original;

            expect($cloned)->not->toBe($original);
            expect($cloned->type)->toBe($original->type);
            expect($cloned->properties)->not->toBe($original->properties);
            expect($cloned->validation)->not->toBe($original->validation);
            expect($cloned->validation->minLength)->toBe($original->validation->minLength);
        });
    });

    describe('OpenAPI 3.1 Union Type Support', function (): void {
        describe('union type parsing', function (): void {
            it('should parse union type with null as nullable', function (): void {
                $data = [
                    'type' => ['string', 'null'],
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string');
                expect($schema->isNullable())->toBeTrue();
                expect($schema->hasUnionType())->toBeTrue();
                expect($schema->getPrimaryType())->toBe('string');
            });

            it('should parse integer union type with null', function (): void {
                $data = [
                    'type' => ['integer', 'null'],
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('integer');
                expect($schema->isNullable())->toBeTrue();
                expect($schema->hasUnionType())->toBeTrue();
                expect($schema->getPrimaryType())->toBe('integer');
            });

            it('should parse array union type with null', function (): void {
                $data = [
                    'type' => ['array', 'null'],
                    'items' => ['type' => 'string'],
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('array');
                expect($schema->isNullable())->toBeTrue();
                expect($schema->hasUnionType())->toBeTrue();
                expect($schema->getPrimaryType())->toBe('array');
                expect($schema->items)->not->toBeNull();
                expect($schema->items->type)->toBe('string');
            });

            it('should handle union type with null first', function (): void {
                $data = [
                    'type' => ['null', 'string'],
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string');
                expect($schema->isNullable())->toBeTrue();
                expect($schema->getPrimaryType())->toBe('string');
            });

            it('should handle only null type gracefully', function (): void {
                $data = [
                    'type' => ['null'],
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string'); // defaults to string
                expect($schema->isNullable())->toBeTrue();
            });

            it('should handle complex union types by using first non-null', function (): void {
                $data = [
                    'type' => ['string', 'integer', 'null'],
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string'); // first non-null type
                expect($schema->isNullable())->toBeTrue();
            });
        });

        describe('backward compatibility with OpenAPI 3.0', function (): void {
            it('should handle OpenAPI 3.0 nullable property', function (): void {
                $data = [
                    'type' => 'string',
                    'nullable' => true,
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string');
                expect($schema->isNullable())->toBeTrue();
                expect($schema->getPrimaryType())->toBe('string');
            });

            it('should handle non-nullable OpenAPI 3.0 schema', function (): void {
                $data = [
                    'type' => 'string',
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string');
                expect($schema->isNullable())->toBeFalse();
                expect($schema->hasUnionType())->toBeFalse();
                expect($schema->getPrimaryType())->toBe('string');
            });

            it('should handle OpenAPI 3.0 nullable false explicitly', function (): void {
                $data = [
                    'type' => 'string',
                    'nullable' => false,
                ];

                $schema = SchemaObject::fromArray($data);

                expect($schema->type)->toBe('string');
                expect($schema->isNullable())->toBeFalse();
            });
        });

        describe('toArray with nullable', function (): void {
            it('should include nullable in array representation', function (): void {
                $data = [
                    'type' => ['string', 'null'],
                ];

                $schema = SchemaObject::fromArray($data);
                $array = $schema->toArray();

                expect($array)->toHaveKey('nullable');
                expect($array['nullable'])->toBe(true);
                expect($array['type'])->toBe('string');
            });

            it('should not include nullable when false', function (): void {
                $data = [
                    'type' => 'string',
                ];

                $schema = SchemaObject::fromArray($data);
                $array = $schema->toArray();

                expect($array)->not->toHaveKey('nullable');
            });
        });

        describe('cloning with nullable', function (): void {
            it('should preserve nullable property when cloning', function (): void {
                $data = [
                    'type' => ['string', 'null'],
                ];

                $original = SchemaObject::fromArray($data);
                $cloned = clone $original;

                expect($cloned->isNullable())->toBe($original->isNullable());
                expect($cloned->hasUnionType())->toBe($original->hasUnionType());
                expect($cloned->getPrimaryType())->toBe($original->getPrimaryType());
            });
        });
    });
});
