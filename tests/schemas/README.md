# JSON Schema Validation for API Endpoints

This document describes the JSON schema validation system implemented for API endpoints in the Subterra project.

## Overview

JSON schemas have been added to validate API responses across all endpoints. The schemas are organized into:

- **Object schemas** (`tests/schemas/objects/`): Reusable components for common data structures
- **Endpoint schemas** (`tests/schemas/endpoints/`): Complete response schemas for specific API endpoints

## Schema Structure

### Object Schemas

Located in `tests/schemas/objects/`, these define reusable data structures:

- `user.json` - User object structure
- `club.json` - Club object structure  
- `club-summary.json` - Simplified club structure for nested references
- `cave.json` - Cave object structure
- `cave-system.json` - Cave system object structure
- `trip.json` - Trip object structure
- `tag.json` - Tag object structure
- `media.json` - Media object structure

### Endpoint Schemas

Located in `tests/schemas/endpoints/`, these define complete API response structures:

- `users-index.json` - GET /api/users response
- `users-me.json` - GET /api/users/me response
- `caves-index.json` - GET /api/caves response
- `caves-show.json` - GET /api/caves/{cave} response
- `trips-index.json` - GET /api/trips response
- `trips-show.json` - GET /api/trips/{trip} response
- `tags-index.json` - GET /api/tags response
- `clubs-index.json` - GET /api/clubs response

## Usage in Tests

### Adding the Trait

To use schema validation in a test class, add the `JsonSchemaValidator` trait:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\JsonSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyApiTest extends TestCase
{
    use RefreshDatabase, JsonSchemaValidator;
    
    // ... test methods
}
```

### Validating Responses

Use the `assertResponseMatchesSchema()` method to validate API responses:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_returns_user_data()
{
    $response = $this->getJson('/api/users/me');
    
    $response->assertOk();
    $this->assertResponseMatchesSchema($response, 'endpoints/users-me');
}
```

### Custom Validation

For more complex scenarios, use the lower-level methods:

```php
// Validate JSON data directly
$this->assertJsonMatchesSchema($jsonData, $this->getSchemaPath('objects/user'));

// Get schema file path
$schemaPath = $this->getSchemaPath('endpoints/users-index');
```

## Schema References

Schemas use JSON Schema Draft 07 and support `$ref` for reusability. Object schemas are referenced in endpoint schemas using relative paths:

```json
{
  "definitions": {
    "user": {
      "$ref": "../objects/user.json"
    }
  },
  "properties": {
    "data": {
      "type": "array",
      "items": {
        "$ref": "#/definitions/user"
      }
    }
  }
}
```

## Dependencies

The system uses the `justinrainbow/json-schema` library for validation, which is included in the project's development dependencies.

## Adding New Schemas

1. **Create object schema**: Add new reusable components to `tests/schemas/objects/`
2. **Create endpoint schema**: Add endpoint-specific schemas to `tests/schemas/endpoints/`
3. **Reference objects**: Use `$ref` to reference object schemas from endpoint schemas
4. **Update tests**: Add schema validation to existing or new test methods

## Error Handling

Schema validation failures provide detailed error messages indicating:
- Which property failed validation
- The expected vs actual data type/format
- The path to the problematic data

This helps quickly identify and fix API response structure issues.