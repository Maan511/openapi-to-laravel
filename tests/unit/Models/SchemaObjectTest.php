<?php

use Maan511\OpenapiToLaravel\Models\SchemaObject;
use Maan511\OpenapiToLaravel\Models\ValidationConstraints;

describe('SchemaObject', function () {
    describe('construction', function () {
        it('should create simple schema object', function () {
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

        it('should create object schema with properties', function () {
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

        it('should create array schema with items', function () {
            $itemSchema = new SchemaObject(type: 'string');

            $schema = new SchemaObject(
                type: 'array',
                items: $itemSchema
            );

            expect($schema->type)->toBe('array');
            expect($schema->items)->toBe($itemSchema);
            expect($schema->items->type)->toBe('string');
        });

        it('should create schema with validation constraints', function () {
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

        it('should create schema with format', function () {
            $schema = new SchemaObject(
                type: 'string',
                format: 'email'
            );

            expect($schema->type)->toBe('string');
            expect($schema->format)->toBe('email');
        });
    });

    describe('isObject', function () {
        it('should return true for object type', function () {
            $schema = new SchemaObject(type: 'object');

            expect($schema->isObject())->toBeTrue();
        });

        it('should return false for non-object types', function () {
            $stringSchema = new SchemaObject(type: 'string');
            $arraySchema = new SchemaObject(type: 'array');

            expect($stringSchema->isObject())->toBeFalse();
            expect($arraySchema->isObject())->toBeFalse();
        });
    });

    describe('isArray', function () {
        it('should return true for array type', function () {
            $schema = new SchemaObject(type: 'array');

            expect($schema->isArray())->toBeTrue();
        });

        it('should return false for non-array types', function () {
            $stringSchema = new SchemaObject(type: 'string');
            $objectSchema = new SchemaObject(type: 'object');

            expect($stringSchema->isArray())->toBeFalse();
            expect($objectSchema->isArray())->toBeFalse();
        });
    });

    describe('isPrimitive', function () {
        it('should return true for primitive types', function () {
            $stringSchema = new SchemaObject(type: 'string');
            $integerSchema = new SchemaObject(type: 'integer');
            $numberSchema = new SchemaObject(type: 'number');
            $booleanSchema = new SchemaObject(type: 'boolean');

            expect($stringSchema->isPrimitive())->toBeTrue();
            expect($integerSchema->isPrimitive())->toBeTrue();
            expect($numberSchema->isPrimitive())->toBeTrue();
            expect($booleanSchema->isPrimitive())->toBeTrue();
        });

        it('should return false for complex types', function () {
            $objectSchema = new SchemaObject(type: 'object');
            $arraySchema = new SchemaObject(type: 'array');

            expect($objectSchema->isPrimitive())->toBeFalse();
            expect($arraySchema->isPrimitive())->toBeFalse();
        });
    });

    describe('hasValidation', function () {
        it('should return true when validation constraints exist', function () {
            $validation = new ValidationConstraints(minLength: 5);
            $schema = new SchemaObject(
                type: 'string',
                validation: $validation
            );

            expect($schema->hasValidation())->toBeTrue();
        });

        it('should return false when no validation constraints exist', function () {
            $schema = new SchemaObject(type: 'string');

            expect($schema->hasValidation())->toBeFalse();
        });
    });

    describe('getProperty', function () {
        it('should return property schema by name', function () {
            $nameProperty = new SchemaObject(type: 'string');
            $ageProperty = new SchemaObject(type: 'integer');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty, 'age' => $ageProperty]
            );

            expect($schema->getProperty('name'))->toBe($nameProperty);
            expect($schema->getProperty('age'))->toBe($ageProperty);
        });

        it('should return null for non-existent property', function () {
            $schema = new SchemaObject(type: 'object');

            expect($schema->getProperty('nonexistent'))->toBeNull();
        });
    });

    describe('hasProperty', function () {
        it('should return true for existing properties', function () {
            $nameProperty = new SchemaObject(type: 'string');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty]
            );

            expect($schema->hasProperty('name'))->toBeTrue();
        });

        it('should return false for non-existing properties', function () {
            $schema = new SchemaObject(type: 'object');

            expect($schema->hasProperty('nonexistent'))->toBeFalse();
        });
    });

    describe('isRequired', function () {
        it('should return true for required properties', function () {
            $schema = new SchemaObject(
                type: 'object',
                required: ['name', 'email']
            );

            expect($schema->isRequired('name'))->toBeTrue();
            expect($schema->isRequired('email'))->toBeTrue();
        });

        it('should return false for optional properties', function () {
            $schema = new SchemaObject(
                type: 'object',
                required: ['name']
            );

            expect($schema->isRequired('age'))->toBeFalse();
        });
    });

    describe('getNestingLevel', function () {
        it('should return 0 for primitive schemas', function () {
            $schema = new SchemaObject(type: 'string');

            expect($schema->getNestingLevel())->toBe(0);
        });

        it('should return 1 for simple object schemas', function () {
            $nameProperty = new SchemaObject(type: 'string');

            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => $nameProperty]
            );

            expect($schema->getNestingLevel())->toBe(1);
        });

        it('should return correct level for nested object schemas', function () {
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

        it('should handle array nesting', function () {
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

    describe('toArray', function () {
        it('should convert simple schema to array', function () {
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

        it('should convert complex schema to array', function () {
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

    describe('fromArray', function () {
        it('should create schema from array data', function () {
            $data = [
                'type' => 'string',
                'format' => 'email',
            ];

            $schema = SchemaObject::fromArray($data);

            expect($schema->type)->toBe('string');
            expect($schema->format)->toBe('email');
        });

        it('should create complex schema from array data', function () {
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

        it('should handle array schema from array data', function () {
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

    describe('clone', function () {
        it('should create deep copy of schema', function () {
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
});
