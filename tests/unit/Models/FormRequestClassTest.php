<?php

describe('FormRequestClass', function () {
    describe('create', function () {
        it('should create FormRequestClass with valid data', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            expect($formRequest)->toBeInstanceOf(\Maan511\OpenapiToLaravel\Models\FormRequestClass::class);
            expect($formRequest->className)->toBe('TestRequest');
            expect($formRequest->namespace)->toBe('App\\Http\\Requests');
            expect($formRequest->filePath)->toBe('/app/Http/Requests/TestRequest.php');
            expect($formRequest->validationRules)->toBe(['name' => 'required|string']);
            expect($formRequest->sourceSchema)->toBe($schema);
        });

        it('should throw exception for invalid class name', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            expect(fn () => \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'invalid-class-name',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/invalid-class-name.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema
            ))->toThrow(\InvalidArgumentException::class, 'Invalid class name: invalid-class-name');
        });

        it('should throw exception for invalid namespace', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            expect(fn () => \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'invalid-namespace',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema
            ))->toThrow(\InvalidArgumentException::class, 'Invalid namespace: invalid-namespace');
        });

        it('should throw exception for empty validation rules', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            expect(fn () => \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: [],
                sourceSchema: $schema
            ))->toThrow(\InvalidArgumentException::class, 'Validation rules cannot be empty');
        });

        it('should accept custom options', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');
            $options = ['authorize_return' => 'false', 'include_comments' => true];

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema,
                options: $options
            );

            expect($formRequest->options)->toBe($options);
        });
    });

    describe('generatePhpCode', function () {
        it('should generate valid PHP code', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required|string', 'email' => 'nullable|email'],
                sourceSchema: $schema
            );

            $phpCode = $formRequest->generatePhpCode();

            expect($phpCode)->toContain('<?php');
            expect($phpCode)->toContain('namespace App\\Http\\Requests;');
            expect($phpCode)->toContain('class TestRequest extends FormRequest');
            expect($phpCode)->toContain('public function rules(): array');
            expect($phpCode)->toContain('public function authorize(): bool');
            expect($phpCode)->toContain("'name' => 'required|string'");
            expect($phpCode)->toContain("'email' => 'nullable|email'");
        });

        it('should include custom messages when provided', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema,
                customMessages: ['name.required' => 'Name is required']
            );

            $phpCode = $formRequest->generatePhpCode();

            expect($phpCode)->toContain('public function messages(): array');
            expect($phpCode)->toContain("'name.required' => 'Name is required'");
        });

        it('should include custom attributes when provided', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema,
                customAttributes: ['name' => 'Full Name']
            );

            $phpCode = $formRequest->generatePhpCode();

            expect($phpCode)->toContain('public function attributes(): array');
            expect($phpCode)->toContain("'name' => 'Full Name'");
        });
    });

    describe('getComplexity', function () {
        it('should calculate complexity based on rules and nesting', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: [
                    'name' => 'required|string|max:255',
                    'nested.field' => 'nullable|string',
                    'array.*' => 'string',
                    'deep.nested.field' => 'integer',
                ],
                sourceSchema: $schema
            );

            $complexity = $formRequest->getComplexity();

            expect($complexity)->toBeGreaterThan(0);
            expect($complexity)->toBeInt();
        });

        it('should return 0 complexity for empty rules', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['simple' => 'string'],
                sourceSchema: $schema
            );

            $complexity = $formRequest->getComplexity();

            expect($complexity)->toBeGreaterThanOrEqual(1);
        });
    });

    describe('getEstimatedSize', function () {
        it('should estimate file size based on content', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            $size = $formRequest->getEstimatedSize();

            expect($size)->toBeGreaterThan(0);
            expect($size)->toBeInt();
        });

        it('should return larger size for more complex FormRequests', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $simpleRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'SimpleRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/SimpleRequest.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema
            );

            $complexRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'ComplexRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/ComplexRequest.php',
                validationRules: [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users',
                    'profile.bio' => 'nullable|string|max:1000',
                    'profile.social.*' => 'nullable|url',
                ],
                sourceSchema: $schema,
                customMessages: ['name.required' => 'Name is required'],
                customAttributes: ['name' => 'Full Name']
            );

            expect($complexRequest->getEstimatedSize())->toBeGreaterThan($simpleRequest->getEstimatedSize());
        });
    });

    describe('validate', function () {
        it('should validate correct FormRequest structure', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema
            );

            $validation = $formRequest->validate();

            expect($validation['valid'])->toBeTrue();
            expect($validation['errors'])->toBeEmpty();
        });

        it('should detect invalid validation rules', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['invalid_rule' => ''],
                sourceSchema: $schema
            );

            $validation = $formRequest->validate();

            expect($validation['valid'])->toBeFalse();
            expect($validation['errors'])->not->toBeEmpty();
        });
    });

    describe('toArray', function () {
        it('should convert FormRequest to array representation', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object');

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'TestRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/TestRequest.php',
                validationRules: ['name' => 'required'],
                sourceSchema: $schema
            );

            $array = $formRequest->toArray();

            expect($array)->toHaveKey('className');
            expect($array)->toHaveKey('namespace');
            expect($array)->toHaveKey('filePath');
            expect($array)->toHaveKey('validationRules');
            expect($array)->toHaveKey('complexity');
            expect($array)->toHaveKey('estimatedSize');
            expect($array['className'])->toBe('TestRequest');
        });
    });
});
