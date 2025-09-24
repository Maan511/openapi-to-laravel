<?php

namespace Maan511\OpenapiToLaravel\Generator;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\FormRequestClass;

/**
 * Simple template engine for generating PHP code
 */
class TemplateEngine
{
    /** @var array<string, string> */
    private array $templates = [];

    public function __construct()
    {
        $this->loadDefaultTemplates();
    }

    /**
     * Render FormRequest class template
     */
    public function renderFormRequest(FormRequestClass $formRequest): string
    {
        $variables = [
            'namespace' => $formRequest->namespace,
            'className' => $formRequest->className,
            'sourceEndpoint' => $formRequest->getSourceEndpoint(),
            'description' => $formRequest->sourceSchema->description ?? '',
            'generatedAt' => $formRequest->generatedAt?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            'authorize' => $formRequest->authorizationMethod,
            'rules' => $this->formatArray($formRequest->validationRules),
            'hasCustomMessages' => $formRequest->hasCustomMessages(),
            'customMessages' => $this->formatArray($formRequest->customMessages),
            'hasCustomAttributes' => $formRequest->hasCustomAttributes(),
            'customAttributes' => $this->formatArray($formRequest->customAttributes),
        ];

        return $this->render('form_request', $variables);
    }

    /**
     * Render template with variables
     *
     * @param  array<string, mixed>  $variables
     */
    public function render(string $templateName, array $variables = []): string
    {
        if (! isset($this->templates[$templateName])) {
            throw new InvalidArgumentException("Template '{$templateName}' not found");
        }

        $template = $this->templates[$templateName];

        // Replace variables in template
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, (string) $value, $template);
        }

        // Handle conditional blocks
        $template = $this->processConditionals($template, $variables);

        // Clean up any remaining placeholders
        $template = preg_replace('/\{\{[^}]+\}\}/', '', $template);

        return $template;
    }

    /**
     * Add or update a template
     */
    public function addTemplate(string $name, string $template): void
    {
        $this->templates[$name] = $template;
    }

    /**
     * Load template from file
     */
    public function loadTemplate(string $name, string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Template file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("Failed to read template file: {$filePath}");
        }
        $this->templates[$name] = $content;
    }

    /**
     * Get available template names
     *
     * @return array<string>
     */
    public function getTemplateNames(): array
    {
        return array_keys($this->templates);
    }

    /**
     * Check if template exists
     */
    public function hasTemplate(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * Process conditional blocks in template
     *
     * @param  array<string, mixed>  $variables
     */
    private function processConditionals(string $template, array $variables): string
    {
        // Process {{#if variable}} ... {{/if}} blocks
        $pattern = '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s';

        return preg_replace_callback($pattern, function ($matches) use ($variables) {
            $variableName = $matches[1];
            $content = $matches[2];

            // Check if variable exists and is truthy
            if (isset($variables[$variableName]) && $variables[$variableName]) {
                return $content;
            }

            return '';
        }, $template);
    }

    /**
     * Load default templates
     */
    private function loadDefaultTemplates(): void
    {
        $this->templates['form_request'] = $this->getFormRequestTemplate();
        $this->templates['form_request_minimal'] = $this->getMinimalFormRequestTemplate();
    }

    /**
     * Get full FormRequest template
     */
    private function getFormRequestTemplate(): string
    {
        return <<<'PHP'
<?php

namespace {{namespace}};

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest for {{sourceEndpoint}}
{{#if description}}
 *
 * {{description}}
{{/if}}
 *
 * Generated at: {{generatedAt}}
 */
class {{className}} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        {{authorize}}
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return {{rules}};
    }
{{#if hasCustomMessages}}

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return {{customMessages}};
    }
{{/if}}
{{#if hasCustomAttributes}}

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return {{customAttributes}};
    }
{{/if}}
}
PHP;
    }

    /**
     * Get minimal FormRequest template (no comments or custom methods)
     */
    private function getMinimalFormRequestTemplate(): string
    {
        return <<<'PHP'
<?php

namespace {{namespace}};

use Illuminate\Foundation\Http\FormRequest;

class {{className}} extends FormRequest
{
    public function authorize(): bool
    {
        {{authorize}}
    }

    public function rules(): array
    {
        return {{rules}};
    }
}
PHP;
    }

    /**
     * Generate proper PHP array representation
     *
     * @param  array<string, mixed>  $data
     */
    public function formatPhpArray(array $data, int $indentLevel = 2): string
    {
        if (empty($data)) {
            return '[]';
        }

        $indent = str_repeat(' ', $indentLevel * 4);
        $itemIndent = str_repeat(' ', ($indentLevel + 1) * 4);

        $items = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Properly escape single quotes in validation rules
                $escapedValue = str_replace("'", "\\'", $value);
                $items[] = "{$itemIndent}'{$key}' => '{$escapedValue}',";
            } elseif (is_array($value)) {
                $formattedValue = $this->formatPhpArray($value, $indentLevel + 1);
                $items[] = "{$itemIndent}'{$key}' => {$formattedValue},";
            } else {
                $items[] = "{$itemIndent}'{$key}' => " . var_export($value, true) . ',';
            }
        }

        return "[\n" . implode("\n", $items) . "\n{$indent}]";
    }

    /**
     * Escape string for PHP code generation
     */
    public function escapePhpString(string $string): string
    {
        return addslashes($string);
    }

    /**
     * Format class name (ensure proper PascalCase)
     */
    public function formatClassName(string $name): string
    {
        // Remove non-alphanumeric characters and convert to PascalCase
        $name = preg_replace('/[^a-zA-Z0-9]/', ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);

        // Ensure it ends with "Request"
        if (! str_ends_with($name, 'Request')) {
            $name .= 'Request';
        }

        return $name;
    }

    /**
     * Format namespace (ensure proper format)
     */
    public function formatNamespace(string $namespace): string
    {
        // Remove leading/trailing backslashes
        $namespace = trim($namespace, '\\');

        // Ensure each part starts with uppercase
        $parts = explode('\\', $namespace);
        $parts = array_map('ucfirst', $parts);

        return implode('\\', $parts);
    }

    /**
     * Generate file header comment
     *
     * @param  array<string, mixed>  $options
     */
    public function generateFileHeader(array $options = []): string
    {
        $header = "<?php\n\n";

        if (isset($options['strict_types']) && $options['strict_types']) {
            $header = "<?php\n\ndeclare(strict_types=1);\n\n";
        }

        if (isset($options['comment'])) {
            $header .= "/**\n";
            $header .= " * {$options['comment']}\n";
            if (isset($options['generated_at'])) {
                $header .= " * \n";
                $header .= " * Generated at: {$options['generated_at']}\n";
            }
            $header .= " */\n\n";
        }

        return $header;
    }

    /**
     * Enhanced template validation with warnings
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateTemplate(string $template): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Check for PHP opening tag
        if (! str_starts_with(trim($template), '<?php')) {
            $result['warnings'][] = 'Template does not start with <?php tag';
        }

        // Check for unmatched conditional blocks
        $ifCount = preg_match_all('/\{\{#if\s+\w+\}\}/', $template);
        $endIfCount = preg_match_all('/\{\{\/if\}\}/', $template);

        if ($ifCount !== $endIfCount) {
            $result['errors'][] = 'Unmatched conditional blocks ({{#if}} and {{/if}})';
            $result['valid'] = false;
        }

        // Simple PHP syntax check by looking for obvious errors
        // Since php_check_syntax is deprecated/removed, we'll do basic checks
        $testTemplate = preg_replace('/\{\{[^}]+\}\}/', '"test"', $template);

        // Check for unmatched braces
        $openBraces = substr_count($testTemplate, '{');
        $closeBraces = substr_count($testTemplate, '}');
        if ($openBraces !== $closeBraces) {
            $result['errors'][] = 'Unmatched braces in template';
            $result['valid'] = false;
        }

        // Check for basic PHP syntax errors
        if (strpos($testTemplate, 'class') !== false && strpos($testTemplate, 'extends') === false) {
            $result['warnings'][] = 'Class definition without extends clause';
        }

        return $result;
    }

    /**
     * Get template preview with sample data
     *
     * @param  array<string, mixed>  $sampleData
     */
    public function previewTemplate(string $templateName, array $sampleData = []): string
    {
        $defaultSampleData = [
            'namespace' => 'App\\Http\\Requests',
            'className' => 'SampleRequest',
            'sourceEndpoint' => 'POST /api/sample',
            'description' => 'Sample FormRequest for testing',
            'generatedAt' => date('Y-m-d H:i:s'),
            'authorize' => 'return true;',
            'rules' => "[\n            'name' => 'required|string|max:255',\n            'email' => 'required|email',\n        ]",
            'hasCustomMessages' => false,
            'customMessages' => '[]',
            'hasCustomAttributes' => false,
            'customAttributes' => '[]',
        ];

        $data = array_merge($defaultSampleData, $sampleData);

        return $this->render($templateName, $data);
    }

    /**
     * Get template content by name (alias for backward compatibility)
     */
    public function getTemplate(string $templateName): string
    {
        if (! isset($this->templates[$templateName])) {
            throw new InvalidArgumentException("Unknown template: {$templateName}");
        }

        return $this->templates[$templateName];
    }

    /**
     * Set template content (alias for addTemplate for backward compatibility)
     */
    public function setTemplate(string $name, string $template): void
    {
        $this->addTemplate($name, $template);
    }

    /**
     * Render template with variables (public version for testing)
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderTemplate(string $template, array $variables): string
    {
        // Replace variables in template
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, (string) $value, $template);
        }

        return $template;
    }

    /**
     * Format validation rules array as PHP code
     *
     * @param  array<string, mixed>  $rules
     */
    public function formatValidationRules(array $rules): string
    {
        return $this->formatArray($rules);
    }

    /**
     * Format array as PHP code with proper indentation
     *
     * @param  array<string, mixed>  $array
     */
    public function formatArray(array $array, int $indentLevel = 2): string
    {
        if (empty($array)) {
            return "[\n        ]";
        }

        $indent = str_repeat(' ', $indentLevel * 4);
        $itemIndent = str_repeat(' ', ($indentLevel + 1) * 4);

        $items = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $formattedValue = $this->formatArray($value, $indentLevel + 1);
                $items[] = "{$itemIndent}'{$key}' => {$formattedValue},";
            } else {
                // Properly escape single quotes in validation rules
                $escapedValue = str_replace("'", "\\'", (string) $value);
                $items[] = "{$itemIndent}'{$key}' => '{$escapedValue}',";
            }
        }

        return "[\n" . implode("\n", $items) . "\n{$indent}]";
    }

    /**
     * Get available variables for a specific template type
     *
     * @return array<string>
     */
    public function getAvailableVariables(string $templateType): array
    {
        $variables = [
            'form_request' => [
                'namespace', 'className', 'sourceEndpoint', 'description',
                'generatedAt', 'authorize', 'rules',
                'hasCustomMessages', 'customMessages', 'hasCustomAttributes',
                'customAttributes',
            ],
            'form_request_minimal' => [
                'namespace', 'className', 'authorize', 'rules',
            ],
        ];

        if (! isset($variables[$templateType])) {
            throw new InvalidArgumentException("Unknown template type: {$templateType}");
        }

        return $variables[$templateType];
    }
}
