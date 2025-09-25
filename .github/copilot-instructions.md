# Project Overview

This is a PHP library that generates Laravel FormRequest classes from OpenAPI 3.x specifications. The tool enables API-first development by automatically creating validation rules and FormRequest classes from OpenAPI schemas.

## Folder Structure

- `/src`: Core library code
  - `/Console`: Laravel Artisan commands
  - `/Generator`: FormRequest generation logic
  - `/Parser`: OpenAPI specification parsing
  - `/Models`: Data models and value objects
- `/tests`: Test suites organized by type
  - `/contract`: Contract/API tests
  - `/integration`: Integration tests
  - `/unit`: Unit tests for individual components
  - `/Performance`: Performance benchmarks
- `/specs`: OpenAPI specification examples
- `/.github`: GitHub workflows and project configuration

## Technical Specifications

- **Language**: PHP 8.3+
- **Framework**: Laravel (components only - illuminate/support, illuminate/console, illuminate/validation)
- **OpenAPI Library**: cebe/php-openapi for specification parsing
- **Testing**: PestPHP framework
- **Code Formatting**: Laravel Pint with custom rules
- **Architecture**: Clean architecture with separated concerns

## Development Guidelines

### Code Standards
- Follow Laravel Pint formatting rules defined in `pint.json`
- Use PSR-4 autoloading with `Maan511\OpenapiToLaravel` namespace
- Alpha-sort imports and use statements
- Single space around binary operators
- Prefer explicit type declarations where beneficial

### Testing Approach
- Write tests for all new functionality
- Use PestPHP syntax and conventions
- Organize tests by type (unit/integration/contract/performance)
- Mock external dependencies in unit tests
- Use real OpenAPI specs in integration tests

### Architecture Patterns
- **Parser Layer**: Handles OpenAPI specification parsing and reference resolution
- **Generator Layer**: Creates FormRequest classes and validation rules
- **Models**: Immutable value objects representing domain concepts
- **Single Responsibility**: Each class has one clear purpose
- **Dependency Injection**: Constructor injection for dependencies

## Build and Test Commands

```bash
# Install dependencies
composer install

# Run all tests
composer test
# or
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest tests/unit
./vendor/bin/pest tests/integration
./vendor/bin/pest tests/contract
./vendor/bin/pest tests/Performance

# Format code
./vendor/bin/pint
# or
composer format

# Generate FormRequests from OpenAPI
php artisan openapi-to-laravel:make-requests path/to/spec.json
php artisan openapi-to-laravel:make-requests spec.yaml --output=app/Http/Requests/Api --namespace="App\\Http\\Requests\\Api" --force --verbose
```

## Key Implementation Guidelines

### OpenAPI Processing
- Always validate OpenAPI specifications before processing
- Handle circular references in schema objects gracefully
- Support all OpenAPI 3.x features including oneOf, anyOf, allOf
- Preserve original schema structure in generated documentation

### Laravel Integration
- Generate FormRequest classes that extend Illuminate\Foundation\Http\FormRequest
- Map OpenAPI constraints to Laravel validation rules accurately
- Support nested object validation with dot notation
- Handle array validation with appropriate rules

### Validation Rule Mapping
- String formats (email, uuid, date-time) → Laravel validation rules
- Numeric constraints (minimum, maximum) → Laravel min/max rules
- Array constraints (minItems, maxItems) → Laravel array rules
- Required properties → Laravel required rules
- Enum values → Laravel in: rule

### Error Handling
- Provide clear error messages for invalid OpenAPI specs
- Handle missing schema references gracefully
- Log warnings for unsupported OpenAPI features
- Validate output directories and permissions

### Performance Considerations
- Cache parsed OpenAPI specifications when possible
- Process schemas iteratively to handle large specifications
- Use generators for memory-efficient processing
- Benchmark performance with 100+ endpoint specifications

## File Naming Conventions

- **Classes**: PascalCase matching filename
- **Test Files**: Match class name with `Test` suffix
- **Generated FormRequests**: Convert endpoint paths to PascalCase (e.g., `/users/{id}` → `UpdateUserRequest`)
- **Configuration**: Use kebab-case for config files

## Development Workflow

1. Write failing tests first (TDD approach)
2. Implement minimal code to pass tests
3. Refactor while keeping tests green
4. Run full test suite before committing
5. Format code with Pint before committing
6. Update documentation if public API changes

## Common Patterns

- Use static factory methods for complex object creation
- Implement `__toString()` methods for debugging value objects
- Prefer composition over inheritance
- Use descriptive method and variable names
- Handle edge cases explicitly rather than relying on defaults