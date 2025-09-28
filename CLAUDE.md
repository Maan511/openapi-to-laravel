# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP library that generates Laravel FormRequest classes from OpenAPI 3.x specifications. The tool enables API-first development by automatically creating validation rules and FormRequest classes from OpenAPI schemas.

## Commands

### Development Commands
```bash
# Install dependencies
composer install

# Run all tests
composer test
# or
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest tests/contract      # Contract tests
./vendor/bin/pest tests/integration   # Integration tests
./vendor/bin/pest tests/unit         # Unit tests
./vendor/bin/pest tests/Performance  # Performance tests

# Code formatting with Laravel Pint
./vendor/bin/pint
# or
composer format

# Code quality validation (REQUIRED after ALL code changes)
composer test:format    # Check code formatting
composer analyse        # Run static analysis
composer test:refactor  # Run refactoring validation
composer test          # Run full test suite

# Generate FormRequests from OpenAPI spec
php artisan openapi-to-laravel:make-requests path/to/openapi.json
php artisan openapi-to-laravel:make-requests spec.yaml --output=app/Http/Requests/Api --namespace="App\\Http\\Requests\\Api" --force --verbose

# Validate routes against OpenAPI specification
php artisan openapi-to-laravel:validate-routes path/to/openapi.json
php artisan openapi-to-laravel:validate-routes spec.yaml --include-pattern="api/*" --exclude-middleware="web" --report-format=console,json --output-file=validation-report --strict --suggestions
```

## Architecture

### Core Components

The library follows a clean architecture with clear separation of concerns:

1. **Parser Layer** (`src/Parser/`)
   - `OpenApiParser`: Main parsing orchestrator
   - `SchemaExtractor`: Extracts schemas from OpenAPI specs
   - `ReferenceResolver`: Resolves $ref objects and handles circular references

2. **Generator Layer** (`src/Generator/`)
   - `FormRequestGenerator`: Generates FormRequest classes from schemas
   - `ValidationRuleMapper`: Maps OpenAPI constraints to Laravel validation rules
   - `TemplateEngine`: Handles PHP class template generation

3. **Models** (`src/Models/`)
   - `OpenApiSpecification`: Represents parsed OpenAPI spec
   - `EndpointDefinition`: Represents an API endpoint with request body
   - `SchemaObject`: Represents OpenAPI schema objects
   - `ValidationConstraints`: Represents validation constraints
   - `ValidationRule`: Represents Laravel validation rules
   - `FormRequestClass`: Represents generated FormRequest class

4. **Validation Layer** (`src/Validation/`)
   - `RouteValidator`: Main route validation orchestrator
   - `LaravelRouteCollector`: Extracts and normalizes Laravel application routes
   - `RouteComparator`: Implements efficient route comparison logic
   - `Reporters/`: Multiple output formats (Console, JSON, HTML)

5. **Console** (`src/Console/`)
   - `GenerateFormRequestsCommand`: Laravel Artisan command for FormRequest generation
   - `ValidateRoutesCommand`: Laravel Artisan command for route validation

### Data Flow
1. OpenAPI spec file → `OpenApiParser` → `OpenApiSpecification`
2. Specification → `SchemaExtractor` → `EndpointDefinition` objects
3. Endpoints → `FormRequestGenerator` → `FormRequestClass` objects
4. FormRequest classes → File system output

### Key Dependencies
- `cebe/php-openapi`: OpenAPI specification parsing
- `illuminate/support`, `illuminate/console`, `illuminate/validation`: Laravel framework integration
- `pestphp/pest`: Testing framework
- `laravel/pint`: Code formatting

## Testing Structure

Tests are organized by type in the `tests/` directory:
- `contract/`: Contract/API tests
- `integration/`: Integration tests
- `unit/`: Unit tests for individual components
- `Performance/`: Performance benchmarks

Test configuration is in `tests/Pest.php` with shared test case setup.

## Code Style

The project uses Laravel Pint for code formatting with custom rules defined in `pint.json`:
- Laravel preset as base
- Alpha-sorted imports
- Single space around binary operators
- Specific rules for concatenation and method chaining

## Key Features

### FormRequest Generation
- Full OpenAPI 3.x specification support
- Comprehensive validation rule mapping (string formats, constraints, arrays, objects)
- Reference resolution with circular reference detection
- Customizable output directories and namespaces
- Dry-run mode for preview
- Performance optimized for large specifications (100+ endpoints)

### Route Validation
- Validate that Laravel routes match OpenAPI specification endpoints
- Detect missing documentation (routes not in OpenAPI spec)
- Detect missing implementation (OpenAPI endpoints not in Laravel)
- Identify method mismatches and parameter differences
- Multiple output formats: Console, JSON, HTML
- Configurable filtering by middleware, patterns, and domains
- Detailed mismatch reporting with actionable suggestions
- Performance optimized for large applications (100+ routes)

### Testing & Quality Assurance
- Generate test coverage reports using: ./vendor/bin/pest --coverage (or herd coverage ./vendor/bin/pest --coverage if using Laravel Herd)
- **CRITICAL**: ALL code changes MUST be validated with: `composer test:format`, `composer analyse`, `composer test:refactor`, and `composer test` before committing

## Route Validation

The route validation feature ensures consistency between your Laravel application routes and OpenAPI specification endpoints. This is essential for API-first development and maintaining accurate documentation.

### Basic Usage

```bash
# Validate all routes against OpenAPI specification
php artisan openapi-to-laravel:validate-routes path/to/openapi.yaml

# Validate with specific patterns
php artisan openapi-to-laravel:validate-routes spec.json --include-pattern="api/*" --include-pattern="admin/api/*"

# Exclude certain middleware groups
php artisan openapi-to-laravel:validate-routes spec.yaml --exclude-middleware="web" --exclude-middleware="guest"

# Generate multiple report formats
php artisan openapi-to-laravel:validate-routes spec.json --report-format=console,json,html --output-file=validation-report
```

### Command Options

- `--include-pattern=PATTERN`: Route URI patterns to include (supports wildcards, can be used multiple times)
- `--exclude-middleware=MIDDLEWARE`: Middleware groups to exclude from validation (can be used multiple times)
- `--ignore-route=PATTERN`: Route names/patterns to ignore (supports wildcards, can be used multiple times)
- `--report-format=FORMAT`: Report format(s): console, json, html (default: console)
- `--output-file=FILE`: Save report to file (extension determined by format)
- `--strict`: Fail command execution on any mismatches (useful for CI/CD)
- `--suggestions`: Include actionable fix suggestions in output

### Validation Types

1. **Missing Documentation**: Laravel routes that aren't documented in the OpenAPI specification
2. **Missing Implementation**: OpenAPI endpoints that aren't implemented as Laravel routes
3. **Method Mismatches**: Same path with different HTTP methods between routes and spec
4. **Parameter Mismatches**: Different parameter requirements or naming

### Example Output

**Console Format:**
```
=== Route Validation Report ===
Status: FAILED
Total mismatches: 3

=== Mismatches ===

MISSING DOCUMENTATION (2)
✗ Route 'GET:/api/users/{id}/avatar' is implemented but not documented
  Path: /api/users/{id}/avatar
  Method: GET
  Suggestions:
    • Add 'GET /api/users/{id}/avatar' to your OpenAPI specification

MISSING IMPLEMENTATION (1)
✗ Endpoint 'POST:/api/users/{id}/reset-password' is documented but not implemented
  Path: /api/users/{id}/reset-password
  Method: POST
  Suggestions:
    • Implement route 'POST /api/users/{id}/reset-password' in Laravel
```

### Integration with CI/CD

Use the `--strict` flag to make validation failures break your CI/CD pipeline:

```yaml
# GitHub Actions example
- name: Validate API Routes
  run: php artisan openapi-to-laravel:validate-routes openapi.yaml --strict --report-format=json --output-file=route-validation.json
```

### Filtering and Configuration

The validator automatically excludes common framework routes (Telescope, Horizon, Debugbar, etc.) and focuses on API routes. You can customize this behavior:

```bash
# Only validate specific API routes
php artisan openapi-to-laravel:validate-routes spec.yaml --include-pattern="api/v1/*"

# Exclude specific middleware
php artisan openapi-to-laravel:validate-routes spec.yaml --exclude-middleware="web,guest"

# Ignore specific routes
php artisan openapi-to-laravel:validate-routes spec.yaml --ignore-route="api.health-check" --ignore-route="api.metrics"
```