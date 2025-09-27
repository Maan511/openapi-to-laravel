<?php

use Maan511\OpenapiToLaravel\Generator\TemplateEngine;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;
use Maan511\OpenapiToLaravel\Models\SchemaObject;

beforeEach(function (): void {
    $this->templateEngine = new TemplateEngine;
});

describe('TemplateEngine', function (): void {
    describe('renderFormRequest', function (): void {
        it('should render basic FormRequest template', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

        it('should render FormRequest with custom authorization', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

        it('should render FormRequest with custom messages', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

        it('should render FormRequest with custom attributes', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

        it('should handle complex validation rules formatting', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: [
                    'name' => new SchemaObject(type: 'string'),
                    'nested' => new SchemaObject(type: 'object'),
                ]
            );

            $formRequest = FormRequestClass::create(
                className: 'ComplexRequest',
                namespace: 'App\\Http\\Requests',
                filePath: '/app/Http/Requests/ComplexRequest.php',
                validationRules: [
                    'name' => 'required|string|max:255',
                    'nested.field' => 'nullable|string',
                    'array_field.*' => 'string|max:50',
                ],
                sourceSchema: $schema
            );

            $rendered = $this->templateEngine->renderFormRequest($formRequest);

            expect($rendered)->toContain("'name' => 'required|string|max:255'");
            expect($rendered)->toContain("'nested.field' => 'nullable|string'");
            expect($rendered)->toContain("'array_field.*' => 'string|max:50'");
        });

        it('should properly escape strings in templates', function (): void {
            $schema = new SchemaObject(
                type: 'object',
                properties: ['name' => new SchemaObject(type: 'string')]
            );

            $formRequest = FormRequestClass::create(
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

    describe('getTemplate', function (): void {
        it('should return default FormRequest template', function (): void {
            $template = $this->templateEngine->getTemplate('form_request');

            expect($template)->toBeString();
            expect($template)->toContain('<?php');
            expect($template)->toContain('{{namespace}}');
            expect($template)->toContain('{{className}}');
            expect($template)->toContain('{{rules}}');
        });

        it('should throw exception for unknown template', function (): void {
            expect(fn () => $this->templateEngine->getTemplate('unknown_template'))
                ->toThrow(InvalidArgumentException::class, 'Unknown template: unknown_template');
        });
    });

    describe('hasTemplate', function (): void {
        it('should return true for existing templates', function (): void {
            expect($this->templateEngine->hasTemplate('form_request'))->toBeTrue();
        });

        it('should return false for non-existing templates', function (): void {
            expect($this->templateEngine->hasTemplate('non_existing'))->toBeFalse();
        });
    });

    describe('setTemplate', function (): void {
        it('should allow setting custom templates', function (): void {
            $customTemplate = '<?php /* custom template */ {{className}}';

            $this->templateEngine->setTemplate('custom', $customTemplate);

            expect($this->templateEngine->hasTemplate('custom'))->toBeTrue();
            expect($this->templateEngine->getTemplate('custom'))->toBe($customTemplate);
        });

        it('should override existing templates', function (): void {
            $originalTemplate = $this->templateEngine->getTemplate('form_request');
            $newTemplate = '<?php /* modified template */';

            $this->templateEngine->setTemplate('form_request', $newTemplate);

            expect($this->templateEngine->getTemplate('form_request'))->toBe($newTemplate);
            expect($this->templateEngine->getTemplate('form_request'))->not->toBe($originalTemplate);
        });
    });

    describe('renderTemplate', function (): void {
        it('should replace template variables', function (): void {
            $template = 'Hello {{name}}, your age is {{age}}';
            $variables = ['name' => 'John', 'age' => '30'];

            $result = $this->templateEngine->renderTemplate($template, $variables);

            expect($result)->toBe('Hello John, your age is 30');
        });

        it('should handle missing variables gracefully', function (): void {
            $template = 'Hello {{name}}, your age is {{age}}';
            $variables = ['name' => 'John'];

            $result = $this->templateEngine->renderTemplate($template, $variables);

            expect($result)->toBe('Hello John, your age is {{age}}');
        });

        it('should handle empty variables array', function (): void {
            $template = 'Hello {{name}}';
            $variables = [];

            $result = $this->templateEngine->renderTemplate($template, $variables);

            expect($result)->toBe('Hello {{name}}');
        });
    });

    describe('formatValidationRules', function (): void {
        it('should format simple rules array', function (): void {
            $rules = [
                'name' => 'required|string',
                'email' => 'required|email',
            ];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toContain("'name' => 'required|string'");
            expect($formatted)->toContain("'email' => 'required|email'");
            expect($formatted)->toContain("[\n");
            expect($formatted)->toContain("\n        ]");
        });

        it('should format rules with proper indentation', function (): void {
            $rules = [
                'nested.field' => 'nullable|string',
                'array.*' => 'string',
            ];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toContain("            'nested.field' => 'nullable|string'");
            expect($formatted)->toContain("            'array.*' => 'string'");
        });

        it('should handle empty rules array', function (): void {
            $rules = [];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toBe("[\n        ]");
        });

        it('should escape special characters in rule values', function (): void {
            $rules = [
                'pattern' => "regex:/^[A-Z][a-z']+$/",
            ];

            $formatted = $this->templateEngine->formatValidationRules($rules);

            expect($formatted)->toContain("'pattern' => 'regex:/^[A-Z][a-z\\']+$/'");
        });
    });

    describe('formatArray', function (): void {
        it('should format string arrays', function (): void {
            $array = ['item1', 'item2', 'item3'];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toContain("'item1'");
            expect($formatted)->toContain("'item2'");
            expect($formatted)->toContain("'item3'");
            expect($formatted)->toContain("[\n");
            expect($formatted)->toContain("\n        ]");
        });

        it('should format associative arrays', function (): void {
            $array = ['key1' => 'value1', 'key2' => 'value2'];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toContain("'key1' => 'value1'");
            expect($formatted)->toContain("'key2' => 'value2'");
        });

        it('should handle empty arrays', function (): void {
            $array = [];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toBe("[\n        ]");
        });

        it('should handle nested arrays', function (): void {
            $array = [
                'level1' => [
                    'level2' => 'value',
                ],
            ];

            $formatted = $this->templateEngine->formatArray($array);

            expect($formatted)->toContain("'level1'");
            expect($formatted)->toContain("'level2' => 'value'");
        });
    });

    describe('validateTemplate', function (): void {
        it('should validate correct template syntax', function (): void {
            $validTemplate = '<?php class {{className}} extends FormRequest { }';

            $result = $this->templateEngine->validateTemplate($validTemplate);

            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('should detect PHP syntax errors', function (): void {
            $invalidTemplate = '<?php class {{className}} extends FormRequest { unclosed_function() {';

            $result = $this->templateEngine->validateTemplate($invalidTemplate);

            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
        });

        it('should warn about missing PHP opening tag', function (): void {
            $templateWithoutPhpTag = 'class {{className}} extends FormRequest { }';

            $result = $this->templateEngine->validateTemplate($templateWithoutPhpTag);

            expect($result['warnings'])->toContain('Template does not start with <?php tag');
        });
    });

    describe('getAvailableVariables', function (): void {
        it('should return list of available template variables', function (): void {
            $variables = $this->templateEngine->getAvailableVariables('form_request');

            expect($variables)->toBeArray();
            expect($variables)->toContain('className');
            expect($variables)->toContain('namespace');
            expect($variables)->toContain('rules');
            expect($variables)->toContain('authorize');
        });

        it('should throw exception for unknown template type', function (): void {
            expect(fn () => $this->templateEngine->getAvailableVariables('unknown'))
                ->toThrow(InvalidArgumentException::class);
        });
    });
});
