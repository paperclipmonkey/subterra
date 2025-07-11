<?php
/**
 * Simple validation test for JSON schema setup
 * Run with: php tests/validate-schema-setup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

function validateSchema($jsonData, $schemaPath) {
    if (!file_exists($schemaPath)) {
        echo "❌ Schema file not found: $schemaPath\n";
        return false;
    }
    
    $schema = json_decode(file_get_contents($schemaPath));
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Invalid JSON in schema: $schemaPath\n";
        return false;
    }
    
    $validator = new Validator();
    $validator->validate($jsonData, $schema, Constraint::CHECK_MODE_COERCE_TYPES);
    
    if ($validator->isValid()) {
        echo "✅ Schema validation passed: $schemaPath\n";
        return true;
    } else {
        echo "❌ Schema validation failed: $schemaPath\n";
        foreach ($validator->getErrors() as $error) {
            echo "   - [{$error['property']}] {$error['message']}\n";
        }
        return false;
    }
}

echo "🧪 Testing JSON Schema Setup\n";
echo "============================\n\n";

// Test object schemas exist and are valid JSON
$objectSchemas = [
    'user.json',
    'club.json', 
    'club-summary.json',
    'cave.json',
    'cave-system.json',
    'trip.json',
    'tag.json',
    'media.json'
];

$endpointSchemas = [
    'users-index.json',
    'users-me.json',
    'users-show.json',
    'caves-index.json',
    'caves-show.json',
    'trips-index.json',
    'trips-show.json',
    'tags-index.json',
    'clubs-index.json',
    'clubs-show.json'
];

echo "📁 Checking object schemas...\n";
$objectSuccess = 0;
foreach ($objectSchemas as $schema) {
    $path = __DIR__ . "/schemas/objects/$schema";
    if (file_exists($path)) {
        $content = json_decode(file_get_contents($path));
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ $schema - valid JSON\n";
            $objectSuccess++;
        } else {
            echo "❌ $schema - invalid JSON\n";
        }
    } else {
        echo "❌ $schema - file not found\n";
    }
}

echo "\n📡 Checking endpoint schemas...\n";
$endpointSuccess = 0;
foreach ($endpointSchemas as $schema) {
    $path = __DIR__ . "/schemas/endpoints/$schema";
    if (file_exists($path)) {
        $content = json_decode(file_get_contents($path));
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ $schema - valid JSON\n";
            $endpointSuccess++;
        } else {
            echo "❌ $schema - invalid JSON\n";
        }
    } else {
        echo "❌ $schema - file not found\n";
    }
}

echo "\n🧪 Testing schema validation with sample data...\n";

// Test with sample user data
$sampleUser = [
    'id' => 1,
    'name' => 'Test User',
    'photo' => null,
    'clubs' => [
        [
            'name' => 'Test Club',
            'slug' => 'test-club'
        ]
    ]
];

validateSchema($sampleUser, __DIR__ . '/schemas/objects/user.json');

// Test with sample user index response
$sampleUsersIndex = [
    'data' => [$sampleUser]
];

validateSchema($sampleUsersIndex, __DIR__ . '/schemas/endpoints/users-index.json');

echo "\n📊 Summary\n";
echo "==========\n";
echo "Object schemas: $objectSuccess/" . count($objectSchemas) . " valid\n";
echo "Endpoint schemas: $endpointSuccess/" . count($endpointSchemas) . " valid\n";

if ($objectSuccess === count($objectSchemas) && $endpointSuccess === count($endpointSchemas)) {
    echo "\n🎉 All schemas are valid! JSON schema validation system is ready.\n";
    exit(0);
} else {
    echo "\n⚠️  Some schemas need attention.\n";
    exit(1);
}