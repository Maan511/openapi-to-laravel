# OpenAPI to Laravel

[![PHP Version](https://img.shields.io/badge/php-%5E8.3-blue)](https://www.php.net/releases/8.3/en.php)
[![Laravel](https://img.shields.io/badge/laravel-%5E11.0-red)](https://laravel.com)
[![Tests](https://img.shields.io/badge/tests-passing-green)](https://github.com/maan511/openapi-to-laravel)

Build Laravel APIs that stay perfectly synchronized with your OpenAPI specification. Automatically generate FormRequest validation and verify route compliance, ensuring your implementation always matches your API contract.

## The Problem

- **Time-consuming manual work**: Writing FormRequest classes for each endpoint takes 5-15 minutes per class
- **Documentation drift**: API documentation gets out of sync with validation rules and actual routes over time
- **Error-prone process**: Manual validation rule creation leads to inconsistencies and bugs
- **Route mismatches**: Laravel routes don't match OpenAPI specification, breaking API contracts
- **Maintenance overhead**: Changing OpenAPI specs requires tedious updates across multiple FormRequest files and route validation

## The Solution

Two powerful commands that keep your Laravel application and OpenAPI specification perfectly synchronized:

**1. Generate FormRequest classes:**
```bash
php artisan openapi-to-laravel:make-requests api-spec.yaml
```
Automatically generates complete Laravel FormRequest classes with comprehensive validation rules from your OpenAPI specification. Supports all OpenAPI 3.x schema types, formats, constraints, nested objects, arrays, and reference resolution.

**2. Validate route consistency:**
```bash
php artisan openapi-to-laravel:validate-routes api-spec.yaml
```
Validates that your Laravel routes match your OpenAPI specification endpoints. Detects missing documentation, missing implementations, method mismatches, parameter differences, and provides detailed coverage statistics.

**Result:** Transform 100+ endpoints into perfectly validated FormRequest classes AND ensure your routes match your documentation - all in seconds, not hours.

## Key Benefits

- **Save hours of development time**: Generate comprehensive FormRequest classes with complex validation rules instantly
- **Maintain perfect sync**: Your validation rules and routes automatically match your API documentation
- **Eliminate human error**: Consistent, accurate validation rules generated from your source of truth
- **Catch API drift early**: Validate that your Laravel routes match your OpenAPI specification before deployment
- **Comprehensive validation support**: All OpenAPI 3.x types, formats, and constraints mapped to Laravel validation
- **Multiple output formats**: Table, console, JSON, and HTML reports for route validation
- **CI/CD ready**: Strict mode and JSON output perfect for automated pipelines
- **Performance optimized**: Handles 100+ endpoints efficiently with caching and optimized parsing
- **Smart filtering**: Filter routes by pattern, middleware, or error type for targeted validation

## Why Use This?

**Instead of manually writing FormRequests:** Writing validation rules for complex API endpoints is tedious and error-prone. A single endpoint with nested validation can take 15+ minutes to implement correctly.

**Instead of letting documentation drift:** Teams often update either the OpenAPI spec OR the Laravel validation rules/routes, but not both. This leads to inconsistent API behavior and broken contracts.

**Instead of manual route checking:** Manually comparing your Laravel routes against your OpenAPI specification is time-consuming and error-prone, especially with large APIs.

**For API-first development:** Generate your OpenAPI specification first, then automatically create the corresponding Laravel validation layer AND verify your routes match your contract. Your API specification becomes your single source of truth.

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

**1. Generate FormRequest classes from your OpenAPI specification:**

```bash
php artisan openapi-to-laravel:make-requests path/to/your/openapi.json
```

**All options:**
- `--output=path` - Custom output directory (default: `./app/Http/Requests`)
- `--namespace=Namespace` - Custom namespace (default: `App\Http\Requests`)
- `--force` - Overwrite existing files
- `--dry-run` - Preview without creating files (shows detailed table with class names, paths, and sizes)
- `-v|--verbose` - Show detailed output including statistics and generation progress

**2. Validate that your Laravel routes match your OpenAPI specification:**

```bash
php artisan openapi-to-laravel:validate-routes path/to/your/openapi.json
```

**All options:**
- `--base-path=/api` - Override server base path
- `--include-pattern="api/*"` - Only validate specific routes (can be used multiple times)
- `--exclude-middleware=web` - Exclude routes with specific middleware (can be used multiple times)
- `--ignore-route="api.health"` - Ignore specific route names/patterns (can be used multiple times)
- `--report-format=table` - Choose output format: `console`, `json`, `html`, or `table` (default: `table`)
- `--output-file=report` - Save report to file (extension auto-added based on format)
- `--strict` - Fail command on any mismatches (perfect for CI/CD)
- `--suggestions` - Include actionable fix suggestions in output
- `--filter-type=missing-documentation` - Filter by specific mismatch types (can be used multiple times)

**Available filter types:**
- `missing-documentation` - Routes implemented but not in OpenAPI spec
- `missing-implementation` - OpenAPI endpoints not implemented in Laravel
- `method-mismatch` - Same path with different HTTP methods
- `parameter-mismatch` - Different parameter requirements
- `path-mismatch` - Path pattern differences
- `validation-error` - Schema validation errors

**Next Steps:** Use your generated FormRequest classes in your Laravel controllers for automatic validation and run route validation in your CI/CD pipeline.

## Supported OpenAPI Features

### Schema Types
- ✅ `object` - Generated as FormRequest with property validation (mapped to Laravel `array`)
- ✅ `array` - Generates array validation with item rules and constraints
- ✅ `string` - Maps to Laravel string validation with format support
- ✅ `integer` - Maps to Laravel integer validation
- ✅ `number` - Maps to Laravel numeric validation
- ✅ `boolean` - Maps to Laravel boolean validation

### String Formats
All OpenAPI 3.x string formats are fully supported:

- ✅ `email` → `email` validation
- ✅ `uri`, `url` → `url` validation
- ✅ `date` → `date_format:Y-m-d` validation
- ✅ `date-time` → `date` validation
- ✅ `time` → `date_format:H:i:s` validation
- ✅ `uuid` → `uuid` validation
- ✅ `ipv4` → `ipv4` validation
- ✅ `ipv6` → `ipv6` validation
- ✅ `hostname` → Custom regex validation
- ✅ `byte` → Base64 regex validation
- ✅ `binary` → `file` validation

### Validation Constraints
All OpenAPI validation keywords are mapped to Laravel rules:

**String Constraints:**
- `minLength` → `min:n`
- `maxLength` → `max:n`
- `pattern` → `regex:pattern`

**Numeric Constraints:**
- `minimum` → `min:n`
- `maximum` → `max:n`
- `multipleOf` → Custom validation

**Array Constraints:**
- `minItems` → `min:n`
- `maxItems` → `max:n`
- `uniqueItems` → `distinct`

**Object Constraints:**
- `required` → `required` validation
- `nullable` → `nullable` validation
- `minProperties` / `maxProperties` → Handled during validation

### Advanced Features
- ✅ **Reference Resolution**: `$ref` objects are automatically resolved from `#/components/schemas`
- ✅ **Nested Objects**: Deep nesting support with Laravel dot notation (`user.address.city`)
- ✅ **Array Items**: Array item validation with `.*` notation (`tags.*`)
- ✅ **Nested Arrays**: Support for arrays of objects (`items.*.properties`)
- ✅ **Circular Reference Detection**: Prevents infinite loops during schema resolution
- ✅ **Content Type Detection**: Supports `application/json`, `multipart/form-data`, and `application/x-www-form-urlencoded`
- ✅ **OpenAPI 3.0 & 3.1**: Compatible with both OpenAPI versions
- ✅ **Nullable Handling**: Supports both OpenAPI 3.0 `nullable: true` and 3.1 type unions
- ✅ **Required Fields**: Automatically maps to Laravel `required` vs `nullable` rules
- ✅ **Multiple Schemas per Endpoint**: Handles different content types for the same endpoint

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

### Advanced Usage Examples

**Preview generation with dry run:**
```bash
php artisan openapi-to-laravel:make-requests api-spec.yaml --dry-run -v
```

**Output:**
```
Starting generation process...
Spec file: api-spec.yaml
Output directory: ./app/Http/Requests
Namespace: App\Http\Requests
Parsing OpenAPI specification...
Found 15 endpoints with request bodies

Dry run mode - showing what would be generated:

+------------------+----------------------------------------+-------------------+-------+--------+--------------+
| Class Name       | File Path                              | Source Endpoint   | Rules | Exists | Size (bytes) |
+------------------+----------------------------------------+-------------------+-------+--------+--------------+
| CreateUserReq... | ./app/Http/Requests/CreateUserRequ...  | POST /users       | 5     | No     | 1,247        |
| UpdateUserReq... | ./app/Http/Requests/UpdateUserRequ...  | PUT /users/{id}   | 5     | No     | 1,312        |
| CreatePostReq... | ./app/Http/Requests/CreatePostRequ...  | POST /posts       | 8     | No     | 1,689        |
+------------------+----------------------------------------+-------------------+-------+--------+--------------+

Summary:
Total classes: 15
Existing files: 0
Total estimated size: 18,945 bytes
```

**Generate to custom location with verbose output:**
```bash
php artisan openapi-to-laravel:make-requests openapi.json \
  --output=app/Http/Requests/Api/V1 \
  --namespace="App\\Http\\Requests\\Api\\V1" \
  --force \
  -v
```

**Verbose output includes:**
- Specification validation warnings
- Detailed generation progress
- Statistics: total classes, rules, complexity scores, namespaces
- Most complex class identification
- Success/failure details for each file

## Route Validation in Action

Ensure your Laravel routes match your OpenAPI specification:

```bash
php artisan openapi-to-laravel:validate-routes api-spec.yaml
```

**Example output (table format - default):**

```
Route Validation Report
Generated: 2024-01-15 10:30:45

+--------+-------------------------------+-----------------+-----------------+---------+------------------+
| Method | Path                          | Laravel Params  | OpenAPI Params  | Source  | Status           |
+--------+-------------------------------+-----------------+-----------------+---------+------------------+
| GET    | /api/users                    | []              | []              | Both    | ✓ Match          |
| POST   | /api/users                    | []              | []              | Both    | ✓ Match          |
| GET    | /api/users/{id}               | [id]            | [id]            | Both    | ✓ Match          |
| PUT    | /api/users/{id}               | [id]            | [id]            | Both    | ⚠ Param Mismatch |
| GET    | /api/users/{id}/avatar        | [id]            | []              | Laravel | ✗ Missing Doc    |
| POST   | /api/users/{id}/reset-pass... | []              | [id]            | OpenAPI | ✗ Missing Impl   |
+--------+-------------------------------+-----------------+-----------------+---------+------------------+

SUMMARY
-------
Laravel Routes: 5 total, 4 covered (80.0%)
OpenAPI Endpoints: 4 total, 3 covered (75.0%)
Overall Coverage: 7/9 (77.8%)
Total Issues: 3

✗ Found 3 mismatch(es)

Issue breakdown:
  Missing documentation: 1
  Missing implementation: 1
  Parameter mismatches: 1
```

**Advanced validation with filtering:**

```bash
# Filter specific routes and show suggestions
php artisan openapi-to-laravel:validate-routes api-spec.yaml \
  --include-pattern="api/v1/*" \
  --exclude-middleware=web \
  --suggestions

# Filter by specific error types
php artisan openapi-to-laravel:validate-routes api-spec.yaml \
  --filter-type=missing-documentation \
  --filter-type=missing-implementation \
  --suggestions

# Generate multiple report formats
php artisan openapi-to-laravel:validate-routes api-spec.yaml \
  --report-format=html \
  --output-file=validation-report \
  --suggestions
```

**⚡ CI/CD Integration:** Use `--strict` flag to fail builds when routes don't match your specification, ensuring your API documentation stays accurate.

```yaml
# GitHub Actions example
- name: Validate API Routes
  run: |
    php artisan openapi-to-laravel:validate-routes openapi.yaml \
      --strict \
      --report-format=json \
      --output-file=route-validation.json
```

## Advanced Configuration

### Report Formats

The route validation command supports multiple output formats:

1. **Table Format** (default) - Clean tabular output using Laravel's native table renderer
   - Automatically adapts to terminal width
   - Shows route comparison with parameters, source, and status
   - Includes detailed coverage statistics
   - Best for interactive terminal use

2. **Console Format** - Detailed text output with sections
   - Comprehensive mismatch details
   - Structured sections for summary, mismatches, warnings, and statistics
   - Human-readable format

3. **JSON Format** - Machine-readable structured data
   - Complete validation results in JSON format
   - Perfect for programmatic processing
   - CI/CD pipeline integration

4. **HTML Format** - Browser-friendly report
   - Styled HTML output
   - Shareable reports
   - Visual presentation

### Custom Templates

You can customize the generated FormRequest templates by extending the `TemplateEngine` class:

```php
use Maan511\OpenapiToLaravel\Generator\TemplateEngine;

$templateEngine = new TemplateEngine();
$templateEngine->addTemplate('custom_request', $yourCustomTemplate);
```

### Validation Features

The library includes comprehensive validation at multiple levels:

1. **Input Validation** - Validates specification file existence and readability
2. **Specification Validation** - Checks OpenAPI spec structure and reports errors/warnings
3. **Rule Validation** - Ensures generated Laravel validation rules are syntactically correct
4. **Route Validation** - Compares Laravel routes with OpenAPI endpoints

### Coverage Statistics

Route validation provides detailed coverage metrics:

- **Laravel Routes Coverage** - Percentage of routes documented in OpenAPI spec
- **OpenAPI Endpoints Coverage** - Percentage of endpoints implemented in Laravel
- **Overall Coverage** - Combined coverage across both dimensions
- **Mismatch Breakdown** - Count by error type (missing docs, missing impl, parameter mismatches, etc.)

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