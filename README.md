# OpenAPI to Laravel FormRequest Generator

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)](https://www.php.net/releases/8.3/en.php)
[![Laravel](https://img.shields.io/badge/laravel-%5E11.0-red)](https://laravel.com)
[![Tests](https://img.shields.io/badge/tests-passing-green)](https://github.com/maan511/openapi-to-laravel)

A powerful PHP tool that automatically generates Laravel FormRequest classes from OpenAPI 3.x specifications, enabling true API-first development workflows.

## Features

- **OpenAPI 3.x Compliance**: Full support for OpenAPI 3.0 and 3.1 specifications
- **Comprehensive Validation**: Maps OpenAPI constraints to Laravel validation rules
- **Smart Class Generation**: Generates properly formatted FormRequest classes with validation rules
- **Reference Resolution**: Handles `$ref` objects and circular reference detection
- **Flexible Output**: Customizable namespaces, output directories, and templates
- **CLI Integration**: Laravel Artisan command for easy integration
- **Performance Optimized**: Handles large specifications with 100+ endpoints efficiently
- **Test-Driven**: Comprehensive test coverage with Pest framework

## Installation

### Requirements

- PHP 8.3 or higher
- Laravel 11.0 or higher
- Composer

### Install via Composer

```bash
composer require maan511/openapi-to-laravel --dev
```

### Automatic Registration

The package will automatically register the command with Laravel using auto-discovery. No manual registration is required.

## Quick Start

### 1. Generate FormRequests from OpenAPI Specification

```bash
php artisan openapi-to-laravel:make-requests path/to/your/openapi.json
```

### 2. With Custom Options

```bash
php artisan openapi-to-laravel:make-requests api-spec.yaml \
    --output=app/Http/Requests/Api \
    --namespace="App\\Http\\Requests\\Api" \
    --force \
    --verbose
```

### 3. Dry Run Mode

Preview what will be generated without creating files:

```bash
php artisan openapi-to-laravel:make-requests openapi.json --dry-run
```

## Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `spec` | Path to OpenAPI specification file (JSON/YAML) | Required |
| `--output` | Output directory for generated FormRequest classes | `./app/Http/Requests` |
| `--namespace` | PHP namespace for generated classes | `App\\Http\\Requests` |
| `--force` | Overwrite existing FormRequest files | `false` |
| `--dry-run` | Show what would be generated without creating files | `false` |
| `--verbose` | Enable verbose output with detailed information | `false` |

## Supported OpenAPI Features

### Schema Types
- ✅ `object` - Generated as FormRequest with property validation
- ✅ `array` - Generates array validation with item rules
- ✅ `string` - Maps to Laravel string validation
- ✅ `integer` - Maps to Laravel integer validation
- ✅ `number` - Maps to Laravel numeric validation
- ✅ `boolean` - Maps to Laravel boolean validation

### String Formats
- ✅ `email` - Laravel email validation
- ✅ `date` - Laravel date validation
- ✅ `date-time` - Laravel date_format validation
- ✅ `uri` - Laravel url validation
- ✅ `uuid` - Laravel uuid validation

### Validation Constraints

| OpenAPI Constraint | Laravel Rule | Example |
|-------------------|--------------|---------|
| `required` | `required` | `'name' => 'required\|string'` |
| `minLength` | `min` | `'name' => 'string\|min:3'` |
| `maxLength` | `max` | `'name' => 'string\|max:255'` |
| `pattern` | `regex` | `'code' => 'string\|regex:/^[A-Z]{3}$/'` |
| `minimum` | `min` | `'age' => 'integer\|min:0'` |
| `maximum` | `max` | `'age' => 'integer\|max:120'` |
| `minItems` | `min` | `'tags' => 'array\|min:1'` |
| `maxItems` | `max` | `'tags' => 'array\|max:10'` |
| `uniqueItems` | `distinct` | `'ids' => 'array\|distinct'` |
| `enum` | `in` | `'status' => 'string\|in:active,inactive'` |

### Advanced Features
- ✅ **Reference Resolution**: `$ref` objects are automatically resolved
- ✅ **Nested Objects**: Deep nesting support with dot notation
- ✅ **Array Items**: Array item validation with `.*` notation
- ✅ **Circular Reference Detection**: Prevents infinite loops
- ✅ **Content Type Detection**: Supports JSON, form-data, and URL-encoded

## Example Usage

### OpenAPI Specification

```yaml
openapi: 3.0.0
info:
  title: User API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  minLength: 2
                  maxLength: 100
                email:
                  type: string
                  format: email
                age:
                  type: integer
                  minimum: 0
                  maximum: 120
                tags:
                  type: array
                  items:
                    type: string
                  minItems: 1
                  maxItems: 5
              required:
                - name
                - email
```

### Generated FormRequest

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest for POST /users
 *
 * Generated at: 2024-01-15 10:30:00
 */
class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|string|email',
            'age' => 'nullable|integer|min:0|max:120',
            'tags' => 'nullable|array|min:1|max:5',
            'tags.*' => 'string',
        ];
    }
}
```

## Advanced Configuration

### Custom Templates

You can customize the generated FormRequest templates by extending the `TemplateEngine` class:

```php
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;

$templateEngine = new TemplateEngine();
$templateEngine->addTemplate('custom_request', $yourCustomTemplate);
```

### Programmatic Usage

```php
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\SchemaExtractor;
use Maan511\OpenapiToLaravel\Parser\ReferenceResolver;
use Maan511\OpenapiToLaravel\Generator\FormRequestGenerator;
use Maan511\OpenapiToLaravel\Generator\ValidationRuleMapper;
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;

// Initialize services
$referenceResolver = new ReferenceResolver();
$schemaExtractor = new SchemaExtractor($referenceResolver);
$parser = new OpenApiParser($schemaExtractor, $referenceResolver);
$ruleMapper = new ValidationRuleMapper();
$templateEngine = new TemplateEngine();
$generator = new FormRequestGenerator($ruleMapper, $templateEngine);

// Parse OpenAPI specification
$specification = $parser->parseFromFile('path/to/openapi.json');

// Get endpoints with request bodies
$endpoints = $parser->getEndpointsWithRequestBodies($specification);

// Generate FormRequest classes
$formRequests = $generator->generateFromEndpoints(
    $endpoints,
    'App\\Http\\Requests',
    './app/Http/Requests'
);

// Write to files
$results = $generator->generateAndWriteMultiple($formRequests, $force = false);
```

## Testing

### Run All Tests

```bash
composer test
```

### Run Specific Test Suites

```bash
# Contract tests
./vendor/bin/pest tests/contract

# Integration tests
./vendor/bin/pest tests/integration

# Unit tests
./vendor/bin/pest tests/unit

# Performance tests
./vendor/bin/pest tests/Performance
```


## Performance

The tool is optimized for large OpenAPI specifications:

- ✅ **100+ endpoints**: Processes in under 5 seconds
- ✅ **Deep nesting**: Handles 10+ levels efficiently
- ✅ **Complex validation**: Supports multiple constraints per field
- ✅ **Memory efficient**: Minimal memory footprint even for large specs

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/maan511/openapi-to-laravel.git
cd openapi-to-laravel

# Install dependencies
composer install

# Run tests
composer test

# Run code formatting
./vendor/bin/pint
```

## Security

If you discover any security related issues, please email Maan511@users.noreply.github.com instead of using the issue tracker.

## License

The MIT License (MIT).

## Support

- 📖 [Documentation](https://github.com/maan511/openapi-to-laravel/wiki)
- 🐛 [Issue Tracker](https://github.com/maan511/openapi-to-laravel/issues)
- 💬 [Discussions](https://github.com/maan511/openapi-to-laravel/discussions)

## Related Projects

- [Laravel OpenAPI](https://github.com/goldspecdigital/oooas) - Generate OpenAPI specs from Laravel
- [Swagger UI](https://swagger.io/tools/swagger-ui/) - Interactive API documentation
- [OpenAPI Generator](https://openapi-generator.tech/) - Multi-language code generation

---

**Built with ❤️ for the Laravel community**