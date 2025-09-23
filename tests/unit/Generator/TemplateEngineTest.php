<?php

beforeEach(function () {
    $this->templateEngine = new \Maan511\OpenapiToLaravel\Generator\TemplateEngine();
});

describe('TemplateEngine', function () {
    describe('renderFormRequest', function () {
        it('should render basic FormRequest template', function () {
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

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain('<?php');
            expect($rendered)->toContain('namespace App\\Http\\Requests;');
            expect($rendered)->toContain('class TestRequest extends FormRequest');
            expect($rendered)->toContain('public function rules(): array');
            expect($rendered)->toContain('public function authorize(): bool');
            expect($rendered)->toContain("'name' => 'required|string'");
        });

        it('should render FormRequest with custom authorization', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'AuthorizedRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/AuthorizedRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema,
                options: ['authorize_return' => 'return $this->user()->can("create-resource");']
            );

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain('return $this->user()->can("create-resource");');
        });

        it('should render FormRequest with custom messages', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'MessageRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/MessageRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema,
                customMessages: ['name.required' => 'Name is required']
            );

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain('public function messages(): array');
            expect($rendered)->toContain("'name.required' => 'Name is required'");
        });

        it('should render FormRequest with custom attributes', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'AttributeRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/AttributeRequest.php',
                validationRules: ['name' => 'required|string'],
                sourceSchema: $schema,
                customAttributes: ['name' => 'Full Name']
            );

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain('public function attributes(): array');
            expect($rendered)->toContain("'name' => 'Full Name'");
        });

        it('should handle complex validation rules formatting', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: [
                    'name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string'),
                    'nested' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'object')
                ]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'ComplexRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/ComplexRequest.php',
                validationRules: [
                    'name' => 'required|string|max:255',
                    'nested.field' => 'nullable|string',
                    'array_field.*' => 'string|max:50'
                ],
                sourceSchema: $schema
            );

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain("'name' => 'required|string|max:255'");
            expect($rendered)->toContain("'nested.field' => 'nullable|string'");
            expect($rendered)->toContain("'array_field.*' => 'string|max:50'");
        });

        it('should properly escape strings in templates', function () {
            $schema = new \Maan511\OpenapiToLaravel\Models\SchemaObject(
                type: 'object',
                properties: ['name' => new \Maan511\OpenapiToLaravel\Models\SchemaObject(type: 'string')]
            );

            $formRequest = \Maan511\OpenapiToLaravel\Models\FormRequestClass::create(
                className: 'EscapeRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/EscapeRequest.php',
                validationRules: ['pattern' => "regex:/^[A-Z][a-z']+$/"],
                sourceSchema: $schema
            );

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain("'pattern' => 'regex:/^[A-Z][a-z\\']+$/'");
        });
    });

    describe('getTemplate', function () {
        it('should return default FormRequest template', function () {
            $template = $this->templateEngine->getTemplate('form_request');

            expect($template)->toBeString();
            expect($template)->toContain('<?php');
            expect($template)->toContain('{{namespace}}');
            expect($template)->toContain('{{className}}');
            expect($template)->toContain('{{rules}}');
        });

        it('should throw exception for unknown template', function () {
            expect(fn() => $this->templateEngine->getTemplate('unknown_template'))
                ->toThrow(\InvalidArgumentException::class, 'Unknown template: unknown_template');
        });
    });

    describe('hasTemplate', function () {
        it('should return true for existing templates', function () {
            expect($this->templateEngine->hasTemplate('form_request'))->toBeTrue();
        });

        it('should return false for non-existing templates', function () {
            expect($this->templateEngine->hasTemplate('non_existing'))->toBeFalse();
        });
    });

    describe('setTemplate', function () {
        it('should allow setting custom templates', function () {
            $customTemplate = '<?php /* custom template */ {{className}}';
            
            $this->templateEngine->setTemplate('custom', $customTemplate);
            
            expect($this->templateEngine->hasTemplate('custom'))->toBeTrue();
            expect($this->templateEngine->getTemplate('custom'))->toBe($customTemplate);
        });

        it('should override existing templates', function () {
            $originalTemplate = $this->templateEngine->getTemplate('form_request');
            $newTemplate = '<?php /* modified template */';
            
            $this->templateEngine->setTemplate('form_request', $newTemplate);
            
            expect($this->templateEngine->getTemplate('form_request'))->toBe($newTemplate);
            expect($this->templateEngine->getTemplate('form_request'))->not->toBe($originalTemplate);
        });
    });

    describe('renderTemplate', function () {
        it('should replace template variables', function () {
            $template = 'Hello {{name}}, your age is {{age}}';
            $variables = ['name' => 'John', 'age' => '30'];

            $result = $this->templateEngine->renderTemplate($template, $variables);

            expect($result)->toBe('Hello John, your age is 30');
        });

        it('should handle missing variables gracefully', function () {
            $template = 'Hello {{name}}, your age is {{age}}';
            $variables = ['name' => 'John'];

            $result = $this->templateEngine->renderTemplate($template, $variables);

            expect($result)->toBe('Hello John, your age is {{age}}');
        });

        it('should handle empty variables array', function () {
            $template = 'Hello {{name}}';
            $variables = [];

            $result = $this->templateEngine->renderTemplate($template, $variables);

            expect($result)->toBe('Hello {{name}}');
        });
    });

    describe('formatValidationRules', function () {
        it('should format simple rules array', function () {
            $rules = [
                'name' => 'required|string',
                'email' => 'required|email'
            ];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toContain("'name' => 'required|string'");
            expect($formatted)->toContain("'email' => 'required|email'");
            expect($formatted)->toContain("[\n");
            expect($formatted)->toContain("\n        ]");
        });

        it('should format rules with proper indentation', function () {
            $rules = [
                'nested.field' => 'nullable|string',
                'array.*' => 'string'
            ];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toContain("            'nested.field' => 'nullable|string'");
            expect($formatted)->toContain("            'array.*' => 'string'");
        });

        it('should handle empty rules array', function () {
            $rules = [];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toBe("[\n        ]");
        });

        it('should escape special characters in rule values', function () {
            $rules = [
                'pattern' => "regex:/^[A-Z][a-z']+$/"
            ];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toContain("'pattern' => 'regex:/^[A-Z][a-z\\']+$/'");
        });
    });

    describe('formatArray', function () {
        it('should format string arrays', function () {
            $array = ['item1', 'item2', 'item3'];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toContain("'item1'");
            expect($formatted)->toContain("'item2'");
            expect($formatted)->toContain("'item3'");
            expect($formatted)->toContain("[\n");
            expect($formatted)->toContain("\n        ]");
        });

        it('should format associative arrays', function () {
            $array = ['key1' => 'value1', 'key2' => 'value2'];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toContain("'key1' => 'value1'");
            expect($formatted)->toContain("'key2' => 'value2'");
        });

        it('should handle empty arrays', function () {
            $array = [];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toBe("[\n        ]");
        });

        it('should handle nested arrays', function () {
            $array = [
                'level1' => [
                    'level2' => 'value'
                ]
            ];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toContain("'level1'");
            expect($formatted)->toContain("'level2' => 'value'");
        });
    });

    describe('validateTemplate', function () {
        it('should validate correct template syntax', function () {
            $validTemplate = '<?php class {{className}} extends FormRequest { }';

            $result = $this->templateEngine->validateTemplate($validTemplate);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect PHP syntax errors', function () {
            $invalidTemplate = '<?php class {{className}} extends FormRequest { unclosed_function() {';

            $result = $this->templateEngine->validateTemplate($invalidTemplate);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });

        it('should warn about missing PHP opening tag', function () {
            $templateWithoutPhpTag = 'class {{className}} extends FormRequest { }';

            $result = $this->templateEngine->validateTemplate($templateWithoutPhpTag);

            expect($result['warnings'])->toContain('Template does not start with <?php tag');
        });
    });

    describe('getAvailableVariables', function () {
        it('should return list of available template variables', function () {
            $variables = $this->templateEngine->getAvailableVariables('form_request');

            expect($variables)->toBeArray();
            expect($variables)->toContain('className');
            expect($variables)->toContain('namespace');
            expect($variables)->toContain('rules');
            expect($variables)->toContain('authorize');
        });

        it('should throw exception for unknown template type', function () {
            expect(fn() => $this->templateEngine->getAvailableVariables('unknown'))
                ->toThrow(\InvalidArgumentException::class);
        });
    });
});