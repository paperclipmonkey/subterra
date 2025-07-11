<?php
/**
 * Simple validation test for JSON schema setup structure
 * Run with: php tests/check-schema-files.php
 */

echo "🧪 Testing JSON Schema File Structure\n";
echo "=====================================\n\n";

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

function checkSchemaFile($path, $name) {
    if (!file_exists($path)) {
        echo "❌ $name - file not found\n";
        return false;
    }
    
    $content = file_get_contents($path);
    $json = json_decode($content);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ $name - invalid JSON: " . json_last_error_msg() . "\n";
        return false;
    }
    
    // Check for required schema properties
    if (!isset($json->{'$schema'})) {
        echo "⚠️  $name - missing \$schema property\n";
    }
    
    echo "✅ $name - valid\n";
    return true;
}

echo "📁 Checking object schemas...\n";
$objectSuccess = 0;
foreach ($objectSchemas as $schema) {
    $path = __DIR__ . "/schemas/objects/$schema";
    if (checkSchemaFile($path, $schema)) {
        $objectSuccess++;
    }
}

echo "\n📡 Checking endpoint schemas...\n";
$endpointSuccess = 0;
foreach ($endpointSchemas as $schema) {
    $path = __DIR__ . "/schemas/endpoints/$schema";
    if (checkSchemaFile($path, $schema)) {
        $endpointSuccess++;
    }
}

echo "\n📁 Checking directories...\n";
$dirs = [
    __DIR__ . '/schemas',
    __DIR__ . '/schemas/objects',
    __DIR__ . '/schemas/endpoints',
    __DIR__ . '/Support'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ " . basename($dir) . "/ directory exists\n";
    } else {
        echo "❌ " . basename($dir) . "/ directory missing\n";
    }
}

echo "\n📄 Checking support files...\n";
$supportFiles = [
    __DIR__ . '/Support/JsonSchemaValidator.php',
    __DIR__ . '/schemas/README.md'
];

foreach ($supportFiles as $file) {
    if (file_exists($file)) {
        echo "✅ " . basename($file) . " exists\n";
    } else {
        echo "❌ " . basename($file) . " missing\n";
    }
}

echo "\n📊 Summary\n";
echo "==========\n";
echo "Object schemas: $objectSuccess/" . count($objectSchemas) . " valid\n";
echo "Endpoint schemas: $endpointSuccess/" . count($endpointSchemas) . " valid\n";

if ($objectSuccess === count($objectSchemas) && $endpointSuccess === count($endpointSchemas)) {
    echo "\n🎉 All schema files are structurally valid!\n";
    echo "📋 JSON schema validation system is properly set up.\n";
    echo "🧪 To test with actual validation, install dependencies and run PHPUnit tests.\n";
    exit(0);
} else {
    echo "\n⚠️  Some schema files need attention.\n";
    exit(1);
}