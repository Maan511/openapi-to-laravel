# OpenAPI to Laravel FormRequest Generator

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)](https://www.php.net/releases/8.3/en.php)
[![Laravel](https://img.shields.io/badge/laravel-%5E11.0-red)](https://laravel.com)
[![Tests](https://img.shields.io/badge/tests-passing-green)](https://github.com/maan511/openapi-to-laravel)

Stop writing Laravel FormRequest validation rules by hand. Generate them automatically from your OpenAPI specification and keep your API documentation and validation logic perfectly synchronized.

## The Problem

- **Time-consuming manual work**: Writing FormRequest classes for each endpoint takes 5-15 minutes per class
- **Documentation drift**: API documentation gets out of sync with validation rules over time
- **Error-prone process**: Manual validation rule creation leads to inconsistencies and bugs
- **Maintenance overhead**: Changing OpenAPI specs requires tedious updates across multiple FormRequest files

## The Solution

One command generates all your FormRequest classes with proper validation rules:

```bash
php artisan openapi-to-laravel:make-requests api-spec.yaml
```

**Result:** Transform 50+ endpoints into 50+ perfectly validated FormRequest classes in seconds, not hours.

## Key Benefits

- **Save hours of development time**: Generate comprehensive FormRequest classes instantly
- **Maintain perfect sync**: Your validation rules automatically match your API documentation
- **Eliminate human error**: Consistent, accurate validation rules generated from your source of truth

## Why Use This?

**Instead of manually writing FormRequests:** Writing validation rules for complex API endpoints is tedious and error-prone. A single endpoint with nested validation can take 15+ minutes to implement correctly.

**Instead of letting documentation drift:** Teams often update either the OpenAPI spec OR the Laravel validation rules, but not both. This leads to inconsistent API behavior.

**For API-first development:** Generate your OpenAPI specification first, then automatically create the corresponding Laravel validation layer. Your API contract becomes your single source of truth.

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

Generate FormRequest classes from your OpenAPI specification:

```bash
php artisan openapi-to-laravel:make-requests path/to/your/openapi.json
```

**Common options:**
- `--dry-run` - Preview without creating files
- `--output=path` - Custom output directory
- `--force` - Overwrite existing files

**Next Steps:** Use your generated FormRequest classes in your Laravel controllers for automatic validation.

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

### Advanced Features
- ✅ **Reference Resolution**: `$ref` objects are automatically resolved
- ✅ **Nested Objects**: Deep nesting support with dot notation
- ✅ **Array Items**: Array item validation with `.*` notation
- ✅ **Circular Reference Detection**: Prevents infinite loops
- ✅ **Content Type Detection**: Supports JSON, form-data, and URL-encoded

## See It In Action

Transform this OpenAPI specification into a complete FormRequest class in seconds:

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

**⏱️ Time Saved:** This FormRequest would take 10-15 minutes to write manually with careful validation rule mapping. Generated in seconds with perfect accuracy.

## Advanced Configuration

### Custom Templates

You can customize the generated FormRequest templates by extending the `TemplateEngine` class:

```php
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;

$templateEngine = new TemplateEngine();
$templateEngine->addTemplate('custom_request', $yourCustomTemplate);
```

## Testing

Run the test suite:

```bash
composer test
```

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.

### Development Setup

```bash
git clone https://github.com/maan511/openapi-to-laravel.git
cd openapi-to-laravel
composer install
composer test
```

## Security

If you discover any security related issues, please email Maan511@users.noreply.github.com instead of using the issue tracker.

## License

The MIT License (MIT).

## Support

- 📖 [Documentation](https://github.com/maan511/openapi-to-laravel/wiki)
- 🐛 [Issue Tracker](https://github.com/maan511/openapi-to-laravel/issues)
- 💬 [Discussions](https://github.com/maan511/openapi-to-laravel/discussions)

---

**Built with ❤️ for the Laravel community**