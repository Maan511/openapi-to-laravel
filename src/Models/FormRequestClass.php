<?php

namespace Maan511\OpenapiToLaravel\Models;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Represents a generated Laravel FormRequest class
 */
class FormRequestClass
{
    public function __construct(
        public readonly string $className,
        public readonly string $namespace,
        public readonly string $filePath,
        /** @var array<string, string> */
        public readonly array $validationRules,
        public readonly string $authorizationMethod = 'return true;',
        public readonly ?SchemaObject $sourceSchema = null,
        public readonly ?EndpointDefinition $endpoint = null,
        /** @var array<string, string> */
        public readonly array $customMessages = [],
        /** @var array<string, string> */
        public readonly array $customAttributes = [],
        public readonly ?DateTimeInterface $generatedAt = null,
        /** @var array<string, mixed> */
        public readonly array $options = []
    ) {
        $this->validateClassName();
        $this->validateNamespace();
        $this->validateFilePath();
        $this->validateValidationRules();
    }

    /**
     * Get validation rules as ValidationRule objects for testing
     *
     * @return array<ValidationRule>
     */
    public function getValidationRuleObjects(): array
    {
        // Check if ValidationRule objects were passed in options
        if (isset($this->options['validationRuleObjects']) && is_array($this->options['validationRuleObjects'])) {
            /** @var array<ValidationRule> */
            return $this->options['validationRuleObjects'];
        }

        // Convert string rules back to ValidationRule objects
        /** @var array<ValidationRule> $ruleObjects */
        $ruleObjects = [];
        foreach ($this->validationRules as $field => $ruleString) {
            $rules = explode('|', $ruleString);

            // Determine if required
            $isRequired = in_array('required', $rules);

            // Determine type
            $type = 'string'; // Default
            foreach ($rules as $rule) {
                if (in_array($rule, ['string', 'integer', 'numeric', 'boolean', 'array'])) {
                    $type = $rule === 'numeric' ? 'number' : $rule;
                    break;
                }
            }

            $ruleObjects[] = new \Maan511\OpenapiToLaravel\Models\ValidationRule(
                property: $field,
                type: $type,
                rules: $rules,
                isRequired: $isRequired
            );
        }

        return $ruleObjects;
    }

    /**
     * Create instance for endpoint and schema
     */
    /**
     * Create instance for endpoint and schema
     *
     * @param  array<string, string>  $validationRules
     * @param  array<string, string>  $customMessages
     * @param  array<string, string>  $customAttributes
     * @param  array<string, mixed>  $options
     */
    public static function create(
        string $className,
        string $namespace,
        string $filePath,
        array $validationRules,
        SchemaObject $sourceSchema,
        ?EndpointDefinition $endpoint = null,
        array $customMessages = [],
        array $customAttributes = [],
        array $options = []
    ): self {
        // Handle both 'authorizationMethod' and 'authorize_return' options for backwards compatibility
        $authorizationMethod = self::validateString($options['authorizationMethod'] ?? null)
            ?? self::validateString($options['authorize_return'] ?? null)
            ?? 'return true;';

        // Handle custom messages and attributes from options
        $optionsCustomMessages = isset($options['customMessages']) && is_array($options['customMessages'])
            ? $options['customMessages']
            : [];
        $optionsCustomAttributes = isset($options['customAttributes']) && is_array($options['customAttributes'])
            ? $options['customAttributes']
            : [];

        /** @var array<string, string> $finalCustomMessages */
        $finalCustomMessages = array_merge($customMessages, $optionsCustomMessages);
        /** @var array<string, string> $finalCustomAttributes */
        $finalCustomAttributes = array_merge($customAttributes, $optionsCustomAttributes);

        return new self(
            $className,
            $namespace,
            $filePath,
            $validationRules,
            $authorizationMethod,
            $sourceSchema,
            $endpoint,
            $finalCustomMessages,
            $finalCustomAttributes,
            new DateTime,
            $options
        );
    }

    /**
     * Get fully qualified class name
     */
    public function getFullyQualifiedClassName(): string
    {
        return $this->namespace . '\\' . $this->className;
    }

    /**
     * Get source endpoint identifier
     */
    public function getSourceEndpoint(): string
    {
        return $this->endpoint?->getDisplayName() ?? 'Unknown';
    }

    /**
     * Check if has custom authorization
     */
    public function hasCustomAuthorization(): bool
    {
        return $this->authorizationMethod !== 'return true;';
    }

    /**
     * Check if has custom messages
     */
    public function hasCustomMessages(): bool
    {
        return ! empty($this->customMessages);
    }

    /**
     * Check if has custom attributes
     */
    public function hasCustomAttributes(): bool
    {
        return ! empty($this->customAttributes);
    }

    /**
     * Get validation rules count
     */
    public function getValidationRulesCount(): int
    {
        return count($this->validationRules);
    }

    /**
     * Get validation rules as Laravel array format
     */
    public function getValidationRulesArray(): string
    {
        if (empty($this->validationRules)) {
            return '[]';
        }

        $rules = [];
        foreach ($this->validationRules as $field => $rule) {
            $rules[] = "            '{$field}' => '{$rule}',";
        }

        return "[\n" . implode("\n", $rules) . "\n        ]";
    }

    /**
     * Get custom messages as Laravel array format
     */
    public function getCustomMessagesArray(): string
    {
        if (empty($this->customMessages)) {
            return '[]';
        }

        $messages = [];
        foreach ($this->customMessages as $key => $message) {
            $escapedMessage = addslashes($message);
            $messages[] = "            '{$key}' => '{$escapedMessage}',";
        }

        return "[\n" . implode("\n", $messages) . "\n        ]";
    }

    /**
     * Get custom attributes as Laravel array format
     */
    public function getCustomAttributesArray(): string
    {
        if (empty($this->customAttributes)) {
            return '[]';
        }

        $attributes = [];
        foreach ($this->customAttributes as $key => $attribute) {
            $escapedAttribute = addslashes($attribute);
            $attributes[] = "            '{$key}' => '{$escapedAttribute}',";
        }

        return "[\n" . implode("\n", $attributes) . "\n        ]";
    }

    /**
     * Generate PHP class code
     */
    public function generatePhpCode(): string
    {
        $code = "<?php\n\n";
        $code .= "namespace {$this->namespace};\n\n";
        $code .= "use Illuminate\\Foundation\\Http\\FormRequest;\n\n";
        $code .= "/**\n";
        $code .= " * FormRequest for {$this->getSourceEndpoint()}\n";
        if ($this->sourceSchema && $this->sourceSchema->description) {
            $code .= " * \n";
            $code .= " * {$this->sourceSchema->description}\n";
        }
        if ($this->generatedAt) {
            $code .= " * \n";
            $code .= " * Generated at: {$this->generatedAt->format('Y-m-d H:i:s')}\n";
        }
        $code .= " */\n";
        $code .= "class {$this->className} extends FormRequest\n";
        $code .= "{\n";

        // Authorization method
        $code .= "    /**\n";
        $code .= "     * Determine if the user is authorized to make this request.\n";
        $code .= "     */\n";
        $code .= "    public function authorize(): bool\n";
        $code .= "    {\n";
        $code .= "        {$this->authorizationMethod}\n";
        $code .= "    }\n\n";

        // Rules method
        $code .= "    /**\n";
        $code .= "     * Get the validation rules that apply to the request.\n";
        $code .= "     */\n";
        $code .= "    public function rules(): array\n";
        $code .= "    {\n";
        $code .= "        return {$this->getValidationRulesArray()};\n";
        $code .= "    }\n";

        // Custom messages method (if any)
        if ($this->hasCustomMessages()) {
            $code .= "\n";
            $code .= "    /**\n";
            $code .= "     * Get custom validation messages.\n";
            $code .= "     */\n";
            $code .= "    public function messages(): array\n";
            $code .= "    {\n";
            $code .= "        return {$this->getCustomMessagesArray()};\n";
            $code .= "    }\n";
        }

        // Custom attributes method (if any)
        if ($this->hasCustomAttributes()) {
            $code .= "\n";
            $code .= "    /**\n";
            $code .= "     * Get custom attribute names for validation errors.\n";
            $code .= "     */\n";
            $code .= "    public function attributes(): array\n";
            $code .= "    {\n";
            $code .= "        return {$this->getCustomAttributesArray()};\n";
            $code .= "    }\n";
        }

        $code .= "}\n";

        return $code;
    }

    /**
     * Get file size estimate in bytes
     */
    public function getFileSizeEstimate(): int
    {
        return strlen($this->generatePhpCode());
    }

    /**
     * Check if file exists at the specified path
     */
    public function fileExists(): bool
    {
        return file_exists($this->filePath);
    }

    /**
     * Get relative file path from project root
     */
    public function getRelativeFilePath(): string
    {
        // Assuming project root contains composer.json
        $currentDir = getcwd();
        if ($currentDir && str_starts_with($this->filePath, $currentDir)) {
            return './' . substr($this->filePath, strlen($currentDir) + 1);
        }

        return $this->filePath;
    }

    /**
     * Get complexity score based on validation rules
     */
    public function getComplexityScore(): int
    {
        $score = 0;

        foreach ($this->validationRules as $field => $rules) {
            // Count rule complexity
            $ruleCount = substr_count($rules, '|') + 1;
            $score += $ruleCount;

            // Add extra points for nested fields
            if (str_contains($field, '.')) {
                $score += substr_count($field, '.');
            }

            // Add extra points for array fields
            if (str_contains($field, '*')) {
                $score += 2;
            }
        }

        return $score;
    }

    /**
     * Get complexity (alias for getComplexityScore)
     */
    public function getComplexity(): int
    {
        return $this->getComplexityScore();
    }

    /**
     * Get estimated file size
     */
    public function getEstimatedSize(): int
    {
        return $this->getFileSizeEstimate();
    }

    /**
     * Validate FormRequest structure
     *
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        $errors = [];
        $warnings = [];

        // Validate class name
        if (! preg_match('/^[A-Z][a-zA-Z0-9]*Request$/', $this->className)) {
            $errors[] = "Invalid class name format: {$this->className}";
        }

        // Validate namespace
        if (! preg_match('/^[A-Z][a-zA-Z0-9_\\\\]*[a-zA-Z0-9]$/', $this->namespace)) {
            $errors[] = "Invalid namespace format: {$this->namespace}";
        }

        // Validate validation rules
        foreach ($this->validationRules as $field => $rules) {
            if (empty($rules)) {
                $errors[] = "Invalid validation rule for field '{$field}'";
            }
        }

        // Warning for empty validation rules
        if (empty($this->validationRules)) {
            $warnings[] = 'No validation rules defined';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate class name format
     */
    private function validateClassName(): void
    {
        if (empty($this->className)) {
            throw new InvalidArgumentException('Class name cannot be empty');
        }

        if (! preg_match('/^[A-Z][a-zA-Z0-9]*Request$/', $this->className)) {
            throw new InvalidArgumentException(
                "Invalid class name: {$this->className}. Must start with uppercase letter and end with 'Request'."
            );
        }
    }

    /**
     * Validate namespace format
     */
    private function validateNamespace(): void
    {
        if (empty($this->namespace)) {
            throw new InvalidArgumentException('Namespace cannot be empty');
        }

        if (! preg_match('/^[A-Z][a-zA-Z0-9_\\\\]*[a-zA-Z0-9]$/', $this->namespace)) {
            throw new InvalidArgumentException(
                "Invalid namespace: {$this->namespace}. Must be a valid PHP namespace."
            );
        }
    }

    /**
     * Validate file path format
     */
    private function validateFilePath(): void
    {
        if (empty($this->filePath)) {
            throw new InvalidArgumentException('File path cannot be empty');
        }

        if (! str_ends_with($this->filePath, '.php')) {
            throw new InvalidArgumentException(
                "Invalid file path: {$this->filePath}. Must end with .php"
            );
        }
    }

    /**
     * Validate validation rules format
     */
    private function validateValidationRules(): void
    {
        if (empty($this->validationRules)) {
            throw new InvalidArgumentException('Validation rules cannot be empty');
        }

        foreach ($this->validationRules as $field => $rules) {
            if (empty($field)) {
                throw new InvalidArgumentException('Field names cannot be empty');
            }
            // Allow empty rules - they will be caught by the validate() method
        }
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'className' => $this->className,
            'namespace' => $this->namespace,
            'filePath' => $this->filePath,
            'validationRules' => $this->validationRules,
            'authorizationMethod' => $this->authorizationMethod,
            'sourceEndpoint' => $this->getSourceEndpoint(),
            'generatedAt' => $this->generatedAt?->format(DateTimeInterface::ATOM),
            'complexity' => $this->getComplexityScore(),
            'rulesCount' => $this->getValidationRulesCount(),
            'hasCustomMessages' => $this->hasCustomMessages(),
            'hasCustomAttributes' => $this->hasCustomAttributes(),
            'fileSizeEstimate' => $this->getFileSizeEstimate(),
            'estimatedSize' => $this->getFileSizeEstimate(), // Alias for test compatibility
        ];
    }

    /**
     * Validate and cast to string or null
     */
    private static function validateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
