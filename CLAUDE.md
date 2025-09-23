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

# Generate FormRequests from OpenAPI spec
php artisan openapi:generate path/to/openapi.json
php artisan openapi:generate spec.yaml --output=app/Http/Requests/Api --namespace="App\\Http\\Requests\\Api" --force --verbose
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

4. **Console** (`src/Console/`)
   - `GenerateFormRequestsCommand`: Laravel Artisan command for CLI usage

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
- Full OpenAPI 3.x specification support
- Comprehensive validation rule mapping (string formats, constraints, arrays, objects)
- Reference resolution with circular reference detection
- Customizable output directories and namespaces
- Dry-run mode for preview
- Performance optimized for large specifications (100+ endpoints)