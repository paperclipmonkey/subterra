# JSON Schema Validation Implementation

This implementation adds comprehensive JSON schema validation to all API endpoints in the Subterra project.

## What Was Implemented

### 1. Schema Infrastructure
- **JsonSchemaValidator trait** (`tests/Support/JsonSchemaValidator.php`) - Provides validation methods for tests
- **Object schemas** (`tests/schemas/objects/`) - 8 reusable component schemas 
- **Endpoint schemas** (`tests/schemas/endpoints/`) - 10 endpoint-specific response schemas
- **Documentation** (`tests/schemas/README.md`) - Complete usage guide

### 2. Dependency Management
- Added `justinrainbow/json-schema` package for JSON Schema Draft 07 validation
- Updated composer autoload to include test support classes

### 3. Test Integration
Updated 5 key test classes to include schema validation:
- **UserTest** - validates `/api/users/*` endpoints
- **CaveTest** - validates `/api/caves/*` endpoints  
- **TripTest** - validates `/api/trips/*` endpoints
- **TagsTest** - validates `/api/tags` endpoint
- **ClubTest** - validates `/api/clubs` endpoint

### 4. Schema Design
- **Modular approach**: Object schemas are referenced by endpoint schemas using `$ref`
- **Type safety**: Strict validation of data types, required fields, and formats
- **Extensible**: Easy to add new schemas for additional endpoints

## Key Features

### Reusable Object Components
- `user.json` - User data structure
- `cave.json` - Cave data with system references
- `trip.json` - Trip data with participants and media
- `tag.json` - Tag categorization structure
- `club.json` - Club information structure
- `media.json` - File attachment structure

### Comprehensive Endpoint Coverage
- User management endpoints (index, show, me)
- Cave management endpoints (index, show)
- Trip management endpoints (index, show)
- Tag listing endpoint
- Club listing endpoint

### Validation Methods
```php
// Validate complete response
$this->assertResponseMatchesSchema($response, 'endpoints/users-index');

// Validate JSON data directly  
$this->assertJsonMatchesSchema($jsonData, $this->getSchemaPath('objects/user'));
```

## Testing & Validation
- Created validation script to verify schema file structure
- All 18 schema files validated successfully
- Schema references properly structured for modularity
- Ready for immediate use in test suite

## Usage Example
```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_returns_user_list()
{
    $response = $this->getJson('/api/users');
    
    $response->assertOk();
    $this->assertResponseMatchesSchema($response, 'endpoints/users-index');
}
```

This provides robust validation ensuring API responses match expected structure, types, and constraints while maintaining modularity through reusable schema components.